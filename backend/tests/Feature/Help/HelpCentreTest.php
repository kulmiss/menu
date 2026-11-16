<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Features;
use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpRenderer;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

/** Help centre (owner, 2026-11-16, QUESTIONS #194). */
function helpOf(Tenant $tenant): array
{
    return collect(test()->getJson(t($tenant, 'help'))->assertOk()->json('data.articles'))->keyBy('slug')->all();
}

/** Turn a feature off for one restaurant. */
function featureOff(Tenant $tenant, string $key): void
{
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => $key, 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
    Features::forget();
}

beforeEach(fn () => HelpLibrary::forget());

it('ships default articles that all parse, with known topics, features and roles', function () {
    foreach (HelpLibrary::AUDIENCES as $audience) {
        $articles = HelpLibrary::defaults($audience);
        expect(count($articles))->toBeGreaterThan(10);
        foreach ($articles as $slug => $a) {
            expect($slug)->toMatch('/^[a-z0-9][a-z0-9\-]+$/');
            expect(in_array($a['topic'], HelpLibrary::topics($audience), true))->toBeTrue("$audience/$slug: unknown topic");
            expect($a['title'])->not->toBe('');
            foreach ($a['features'] as $f) {
                expect(array_keys(Features::global()))->toContain(ltrim($f, '!'));
            }
            foreach ($a['roles'] as $r) {
                expect(HelpLibrary::ROLES)->toContain($r);
            }
        }
    }
});

it('resolves every placeholder and condition, and never leaves raw tags', function () {
    [, $tenant] = member();
    foreach (HelpLibrary::AUDIENCES as $audience) {
        $render = new HelpRenderer($tenant, 'owner');
        foreach (HelpLibrary::defaults($audience) as $slug => $a) {
            expect($render->unknown($a['body']))->toBe([], "$audience/$slug has unknown placeholders");
            $out = (string) preg_replace('/```.*?```|`[^`\n]+`/s', '', $render->markdown($a['body']));
            expect($out)->not->toContain('{{')->not->toContain('<!--')->not->toContain('shot:');
        }
    }
});

it('links only to articles the same reader can open', function () {
    [, $all] = member();
    [, $none] = member();
    foreach (['branches', 'promotions', 'insights', 'price_periods', 'sold_out', 'import_export', 'pdf_menu', 'order_list', 'print_materials', 'share'] as $key) {
        featureOff($none, $key);
    }
    foreach ([$all, $none] as $tenant) {
        foreach (HelpLibrary::ROLES as $role) {
            $visible = HelpLibrary::visible('restaurant', $tenant, $role);
            $render = new HelpRenderer($tenant, $role);
            foreach ($visible as $slug => $a) {
                preg_match_all('/\(help:([a-z0-9\-]+)\)/', $render->markdown($a['body']), $m);
                foreach ($m[1] as $target) {
                    expect(array_key_exists($target, HelpLibrary::defaults('restaurant')))->toBeTrue("$slug links to a missing article $target");
                    // A link to an article the reader can't open is a broken link for them.
                    $targetFeatures = HelpLibrary::defaults('restaurant')[$target]['features'];
                    expect(HelpLibrary::featuresOn($targetFeatures, $tenant))->toBeTrue("$slug links to $target, which is off for this restaurant ($role)");
                }
            }
        }
    }
    foreach (HelpLibrary::defaults('platform') as $slug => $a) {
        preg_match_all('/\(help:([a-z0-9\-]+)\)/', $a['body'], $m);
        foreach ($m[1] as $target) {
            expect(array_key_exists($target, HelpLibrary::defaults('platform')))->toBeTrue("platform/$slug links to a missing article $target");
        }
    }
});

it('has every screenshot an article uses', function () {
    foreach (HelpLibrary::AUDIENCES as $audience) {
        foreach (HelpLibrary::defaults($audience) as $slug => $a) {
            foreach (HelpRenderer::shots($a['body']) as $file) {
                expect(public_path('help/shots/'.$file))->toBeFile("$audience/$slug uses a missing screenshot $file: run scripts/help-screenshots.mjs");
            }
        }
    }
});

it('has an article behind every "?" button of the web admin sidebar', function () {
    $sidebar = (string) file_get_contents(dirname(base_path()).'/frontend/src/components/layout/data/sidebar-data.ts');
    preg_match_all("/url: '(\\/admin[^']*)'/", $sidebar, $m);
    $pages = collect(HelpLibrary::defaults('restaurant'))->flatMap(fn ($a) => $a['pages'])->unique()->all();
    foreach ([...$m[1], '/admin/print-materials', '/admin/settings/billing'] as $url) {
        expect(in_array($url, $pages, true))->toBeTrue("No help article has `pages: $url`");
    }
});

it('reads plan prices and limits live, never typed', function () {
    $free = Plan::where('slug', 'free')->firstOrFail();
    $pro = Plan::where('slug', 'pro')->firstOrFail();
    $free->update(['limits' => [...(array) $free->limits, 'photos' => 77]]);
    $pro->update(['price_minor' => 123_400]);
    [$owner, $tenant] = member();

    $this->actingAs($owner);
    $help = helpOf($tenant);
    expect($help['plans-compared']['body'])->toContain('77 photos')->toContain('1,234');
    expect($help['dish-photos']['body'])->toContain('77 photos in total');
    expect($help['faq']['body'])->toContain('77 on the Free plan');

    // The numbers in the defaults are never typed: no article names a plan price or photo limit itself.
    foreach (HelpLibrary::defaults('restaurant') as $slug => $a) {
        expect($a['body'])->not->toMatch('/(?<!at least )\b\d[\d,]* (photos|dishes|branches)\b/', "$slug types a limit: use a placeholder");
        expect($a['body'])->not->toMatch('/\b(Br|ETB|USD|\$)\s?\d/', "$slug types a price: use a placeholder");
    }
});

it('hides articles and paragraphs of switched-off features: restaurant, plan and global', function () {
    [$owner, $tenant] = member();
    $this->actingAs($owner);
    $help = helpOf($tenant);
    expect($help)->toHaveKey('sold-out');
    expect($help['dashboard']['body'])->toContain('Sold out now');

    featureOff($tenant, 'sold_out');
    $help = helpOf($tenant);
    expect($help)->not->toHaveKey('sold-out');
    expect($help['dashboard']['body'])->not->toContain('Sold out now');
    expect($help['all-dishes']['body'])->not->toContain('Sold out');

    // Global OFF (Settings → Features) wins everywhere.
    PlatformSettings::save(['features_global' => ['print_materials' => false]]);
    Features::forget();
    expect(helpOf($tenant))->not->toHaveKey('print-materials');

    // Plan: the restaurant's plan without branches.
    $plan = Plan::where('slug', 'free')->firstOrFail();
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();
    $help = helpOf($tenant);
    expect($help)->not->toHaveKey('branches')->not->toHaveKey('branch-prices');
    expect($help['faq']['body'])->toContain('Not on your plan');
});

it('shows staff only what staff can do', function () {
    [$staff, $tenant] = member(Role::Staff);
    $this->actingAs($staff);
    $help = helpOf($tenant);
    expect($help)->toHaveKey('sold-out')->toHaveKey('prices')->not->toHaveKey('team')->not->toHaveKey('upgrade');
});

it('serves sign-in help signed out, and nothing else', function () {
    $articles = collect($this->getJson('/api/v1/help/public')->assertOk()->json('data.articles'))->pluck('slug');
    expect($articles)->toContain('cant-sign-in')->toContain('contact-support')->not->toContain('team')->not->toContain('insights');
});

it('answers 304 when nothing changed', function () {
    [$owner, $tenant] = member();
    $this->actingAs($owner);
    $etag = $this->getJson(t($tenant, 'help'))->assertOk()->headers->get('ETag');
    $this->getJson(t($tenant, 'help'), ['If-None-Match' => $etag])->assertStatus(304);
});

it('counts "Was this helpful?" once per person, for the Super Admin', function () {
    [$owner, $tenant] = member();
    [$other] = member(Role::Owner, $tenant);
    $this->actingAs($owner)->postJson('/api/v1/help/feedback', ['audience' => 'restaurant', 'slug' => 'add-a-dish', 'helpful' => false], idem())->assertNoContent();
    $this->postJson('/api/v1/help/feedback', ['audience' => 'restaurant', 'slug' => 'add-a-dish', 'helpful' => true], idem())->assertNoContent();
    $this->actingAs($other)->postJson('/api/v1/help/feedback', ['audience' => 'restaurant', 'slug' => 'add-a-dish', 'helpful' => true], idem())->assertNoContent();
    $this->postJson('/api/v1/help/feedback', ['audience' => 'restaurant', 'slug' => 'nope', 'helpful' => true], idem())->assertNotFound();
    $this->postJson('/api/v1/help/feedback', ['audience' => 'platform', 'slug' => 'go-live-checklist', 'helpful' => true], idem())->assertForbidden();
    expect(helpOf($tenant))->toHaveKey('add-a-dish');
    expect($this->getJson(t($tenant, 'help'))->json('data.voted.add-a-dish'))->toBeTrue();

    $this->actingAs(superAdmin());
    $row = collect($this->getJson(p('help-articles?audience=restaurant'))->assertOk()->json('data'))->firstWhere('slug', 'add-a-dish');
    expect($row)->toMatchArray(['yes' => 2, 'no' => 0, 'source' => 'default']);
});

it('lets the Super Admin change, preview, add, hide and reset articles', function () {
    [$owner, $tenant] = member();
    $sa = superAdmin();
    $this->actingAs($sa);
    $default = $this->getJson(p('help-articles/restaurant/add-a-dish'))->assertOk()->json('data');
    expect($default['source'])->toBe('default')->and($default['default']['body'])->toBe($default['body']);

    $this->postJson(p('help-articles/preview'), ['body' => 'Photos: {{photos_per_dish}}. <!-- if sold_out -->SO<!-- endif -->', 'tenant_id' => $tenant->id], idem())
        ->assertOk()->assertJsonPath('data.body', 'Photos: 5. SO')->assertJsonPath('data.unknown', []);
    $this->putJson(p('help-articles/restaurant/add-a-dish'), [...$default, 'body' => 'Hi {{nope}}'], idem())->assertUnprocessable()->assertJsonValidationErrors('body');

    $this->putJson(p('help-articles/restaurant/add-a-dish'), [...$default, 'title' => 'Add a new dish', 'body' => "Our own words.\n"], idem())
        ->assertOk()->assertJsonPath('data.source', 'changed')->assertJsonPath('data.title', 'Add a new dish');
    $this->putJson(p('help-articles/restaurant/wifi-for-guests'), ['title' => 'Wi-Fi for guests', 'topic' => 'look', 'body' => 'Ask the waiter.', 'features' => ['!share']], idem())
        ->assertOk()->assertJsonPath('data.source', 'custom');
    $this->putJson(p('help-articles/restaurant/dashboard'), [...$this->getJson(p('help-articles/restaurant/dashboard'))->json('data'), 'hidden' => true], idem())->assertOk();
    $this->putJson(p('help-articles/restaurant/xx'), ['title' => 'X', 'topic' => 'nope', 'body' => 'y'], idem())->assertUnprocessable();

    $this->app['auth']->forgetGuards();
    $this->actingAs($owner);
    $help = helpOf($tenant);
    expect($help['add-a-dish']['title'])->toBe('Add a new dish');
    expect($help)->not->toHaveKey('wifi-for-guests')->not->toHaveKey('dashboard'); // share is on; hidden
    $this->putJson(p('help-articles/restaurant/add-a-dish'), $default, idem())->assertNotFound();

    $this->actingAs($sa);
    $this->deleteJson(p('help-articles/restaurant/add-a-dish'), [], idem())->assertNoContent();
    $this->deleteJson(p('help-articles/restaurant/wifi-for-guests'), [], idem())->assertNoContent();
    expect($this->getJson(p('help-articles/restaurant/add-a-dish'))->json('data.source'))->toBe('default');
    $this->getJson(p('help-articles/restaurant/wifi-for-guests'))->assertNotFound();
});

it('gives the Super Admin guide only to Super Admins', function () {
    [$owner] = member();
    $this->actingAs($owner)->getJson(p('help'))->assertNotFound(); // the platform area answers 404 to others
    $this->actingAs(superAdmin());
    $slugs = collect($this->getJson(p('help'))->assertOk()->json('data.articles'))->pluck('slug');
    expect($slugs)->toContain('go-live-checklist');
});
