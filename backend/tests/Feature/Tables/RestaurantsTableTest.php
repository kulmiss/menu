<?php

use App\Enums\Role;
use App\Enums\SubscriptionState;
use App\Models\Plan;
use App\Models\PlatformTag;
use App\Models\Tenant;
use App\Support\Campaigns\Audience;

it('pages, sorts, searches and filters restaurants on the server', function () {
    $admin = superAdmin();
    $free = Plan::where('slug', 'free')->first();
    $pro = Plan::where('slug', 'pro')->first();
    foreach (range(1, 30) as $n) {
        Tenant::factory()->create(['name' => sprintf('Cafe %02d', $n), 'plan_id' => $n % 3 === 0 ? $pro->id : $free->id, 'created_at' => now()->subDays($n)]);
    }
    Tenant::factory()->create(['name' => 'Zebra Grill', 'slug' => 'zebra', 'plan_id' => $pro->id]);

    $page = $this->actingAs($admin)->getJson(p('restaurants?page=2&per=10&sort=name'))->assertOk();
    expect($page->json('meta'))->toBe(['total' => 31, 'page' => 2, 'per_page' => 10, 'pages' => 4])
        ->and($page->json('data.0.name'))->toBe('Cafe 11');
    expect($this->actingAs($admin)->getJson(p('restaurants?page=1&sort=-name'))->json('data.0.name'))->toBe('Zebra Grill');

    expect($this->actingAs($admin)->getJson(p('restaurants?page=1&q=zeb'))->json('meta.total'))->toBe(1);
    expect($this->actingAs($admin)->getJson(p("restaurants?page=1&plan={$pro->id}"))->json('meta.total'))->toBe(11);
    expect($this->actingAs($admin)->getJson(p('restaurants?page=1&q=%25'))->json('meta.total'))->toBe(0); // % is searched as itself
    $from = now()->subDays(5)->toDateString();
    expect($this->actingAs($admin)->getJson(p("restaurants?page=1&from={$from}&q=Cafe"))->json('meta.total'))->toBe(5);

    // Rejects nonsense instead of guessing.
    $this->actingAs($admin)->getJson(p('restaurants?page=1&per=7'))->assertInvalid('per');
    $this->actingAs($admin)->getJson(p('restaurants?page=0'))->assertInvalid('page');
    // Unknown sort falls back to the default (newest first).
    expect($this->actingAs($admin)->getJson(p('restaurants?page=1&sort=password'))->json('data.0.name'))->toBe('Zebra Grill');
});

it('filters by subscription state in SQL exactly as the restaurant itself computes it', function () {
    $admin = superAdmin();
    $cases = [
        ['status' => 'suspended', 'paid_until' => now()->addMonth()],
        ['signup_state' => 'unverified'],
        ['paid_until' => now()->addDays(3), 'trial_ends_at' => null],
        ['paid_until' => null, 'trial_ends_at' => null],
        ['paid_until' => null, 'trial_ends_at' => now()->addDays(2)],
        ['paid_until' => now()->subDays(2), 'trial_ends_at' => now()->addDays(2)],
        ['paid_until' => now()->subDays(3), 'trial_ends_at' => null],
        ['paid_until' => null, 'trial_ends_at' => now()->subDays(10)],
        ['paid_until' => now()->subDays(15), 'trial_ends_at' => now()->subDays(40)],
        ['paid_until' => now()->subDays(40), 'trial_ends_at' => null],
        ['paid_until' => now()->subDays(25), 'trial_ends_at' => now()->subDays(1)],
    ];
    $tenants = collect($cases)->map(fn ($attrs) => tap(Tenant::factory()->create(), fn ($t) => $t->forceFill($attrs)->save())->fresh());
    $all = Tenant::all();
    foreach (SubscriptionState::cases() as $state) {
        $php = $all->filter(fn (Tenant $t) => $t->subscriptionState() === $state)->pluck('id')->sort()->values()->all();
        $sql = Tenant::query()->inState([$state->value])->pluck('id')->sort()->values()->all();
        expect($sql)->toBe($php, $state->value);
        expect($this->actingAs($admin)->getJson(p('restaurants?page=1&per=100&state='.$state->value))->json('meta.total'))->toBe(count($php));
    }
    expect($tenants->map->subscriptionState()->unique()->count())->toBeGreaterThanOrEqual(7);
    // Several states at once.
    expect($this->actingAs($admin)->getJson(p('restaurants?page=1&per=100&state=trial,suspended'))->json('meta.total'))
        ->toBe($all->filter(fn ($t) => in_array($t->subscriptionState(), [SubscriptionState::Trial, SubscriptionState::Suspended], true))->count());
});

it('exports the filtered view as CSV, safe for spreadsheets', function () {
    $admin = superAdmin();
    Tenant::factory()->create(['name' => '=HYPERLINK("x")']);
    Tenant::factory()->create(['name' => 'Kaldi Corner']);
    $res = $this->actingAs($admin)->get(p('restaurants?format=csv&q=Kaldi'))->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/csv');
    $csv = $res->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('Kaldi Corner')->not->toContain('HYPERLINK');
    $all = $this->actingAs($admin)->get(p('restaurants?format=csv'))->streamedContent();
    expect($all)->toContain("'=HYPERLINK");
});

it('tags many restaurants at once and e-mails only the picked ones', function () {
    $admin = superAdmin();
    $tag = PlatformTag::create(['name' => 'VIP', 'color' => 'green']);
    [$owner1, $a] = member(Role::Owner);
    [, $b] = member(Role::Owner);
    [, $c] = member(Role::Owner);

    $this->actingAs($admin)->postJson(p('restaurants/bulk-tag'), ['ids' => [$a->id, $b->id], 'tag_id' => $tag->id], idem())->assertOk()->assertJsonPath('data.count', 2);
    expect($this->actingAs($admin)->getJson(p("restaurants?page=1&tag={$tag->id}"))->json('meta.total'))->toBe(2);
    $this->actingAs($admin)->postJson(p('restaurants/bulk-tag'), ['ids' => [$b->id], 'tag_id' => $tag->id, 'remove' => true], idem())->assertOk();
    expect($tag->tenants()->pluck('tenants.id')->all())->toBe([$a->id]);

    expect(collect(Audience::recipients(['restaurant_ids' => [$a->id, $c->id]]))->pluck('tenant_id')->sort()->values()->all())->toBe([$a->id, $c->id]);
    $this->actingAs($owner1)->postJson(p('restaurants/bulk-tag'), ['ids' => [$a->id], 'tag_id' => $tag->id], idem())->assertNotFound();
    $this->actingAs($owner1)->getJson(p('restaurants?page=1'))->assertNotFound();
});
