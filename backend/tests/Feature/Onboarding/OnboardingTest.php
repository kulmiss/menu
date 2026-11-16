<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Schema;

/** First-time guidance (owner, 2026-11-16, QUESTIONS #195). */
it('shows the welcome tour once per person and surface, and it can be reset', function () {
    [$owner] = member();
    $this->actingAs($owner);
    expect($this->getJson('/api/v1/me')->json('data.onboarding'))->toBe(['web_tour' => false, 'app_tour' => false, 'setup_needed' => false]);

    $this->putJson('/api/v1/me/onboarding', ['tour' => 'web_tour', 'seen' => true], idem())->assertNoContent();
    expect($this->getJson('/api/v1/me')->json('data.onboarding.web_tour'))->toBeTrue();
    expect($this->getJson('/api/v1/me')->json('data.onboarding.app_tour'))->toBeFalse();

    $this->putJson('/api/v1/me/onboarding', ['tour' => 'web_tour', 'seen' => false], idem())->assertNoContent();
    expect($this->getJson('/api/v1/me')->json('data.onboarding.web_tour'))->toBeFalse();
    $this->putJson('/api/v1/me/onboarding', ['tour' => 'nope', 'seen' => true], idem())->assertUnprocessable();
});

it('opens the setup wizard on a new install only, until it is finished', function () {
    $sa = superAdmin();
    $this->actingAs($sa);
    expect($this->getJson('/api/v1/me')->json('data.onboarding.setup_needed'))->toBeTrue();

    $state = $this->getJson(p('setup'))->assertOk()->json('data');
    expect($state['done'])->toBeFalse();
    $checks = collect($state['checks'])->keyBy('key');
    expect($checks['two_factor']['done'])->toBeTrue()
        ->and($checks['contact']['done'])->toBeFalse()
        ->and($checks['plans']['done'])->toBeFalse()
        ->and($checks->pluck('step')->max())->toBeLessThanOrEqual(5);

    $this->putJson(p('settings'), ['support_email' => 'hello@menu.example'], idem())->assertOk();
    $this->putJson(p('setup'), ['checked' => 'plans'], idem())->assertOk();
    $checks = collect($this->getJson(p('setup'))->json('data.checks'))->keyBy('key');
    expect($checks['contact']['done'])->toBeTrue()->and($checks['plans']['done'])->toBeTrue();

    $this->putJson(p('setup'), ['done' => true], idem())->assertOk()->assertJsonPath('data.done', true);
    expect($this->getJson('/api/v1/me')->json('data.onboarding.setup_needed'))->toBeFalse();

    // The dashboard checklist can be closed, and reopened from Settings.
    $this->putJson(p('setup'), ['card_hidden' => true], idem())->assertJsonPath('data.card_hidden', true);
    $this->putJson(p('setup'), ['done' => false, 'card_hidden' => false], idem())->assertJsonPath('data.done', false);
    expect($this->getJson('/api/v1/me')->json('data.onboarding.setup_needed'))->toBeTrue();
});

it('is only for Super Admins', function () {
    [$owner] = member();
    $this->actingAs($owner)->getJson(p('setup'))->assertNotFound();
    $this->putJson(p('setup'), ['done' => true], idem())->assertNotFound();
});

it('finds and deletes the demo restaurants, never a real one', function () {
    $demo = Tenant::factory()->create(['slug' => 'demo-x']);
    Membership::factory()->for($demo)->for(User::factory()->create(['email' => 'owner.demo-x@example.com']))->create(['role' => Role::Owner]);
    [$realOwner, $real] = member();
    $realOwner->forceFill(['email' => 'owner@realcafe.et'])->save();
    $mixed = Tenant::factory()->create();
    Membership::factory()->for($mixed)->for(User::factory()->create(['email' => 'staff@example.com']))->create(['role' => Role::Staff]);
    Membership::factory()->for($mixed)->for(User::factory()->create(['email' => 'owner@cafe.et']))->create(['role' => Role::Owner]);

    $this->actingAs(superAdmin());
    expect($this->getJson(p('setup'))->json('data.demo_restaurants'))->toBe(1);
    $this->postJson(p('setup/demo/delete'), [], idem())->assertUnprocessable();
    $this->postJson(p('setup/demo/delete'), ['confirm' => true], idem())->assertOk()->assertJsonPath('data.deleted', 1);

    expect(Tenant::find($demo->id))->toBeNull()
        ->and(Tenant::withTrashed()->find($demo->id))->not->toBeNull() // restorable for 30 days
        ->and(Tenant::find($real->id))->not->toBeNull()
        ->and(Tenant::find($mixed->id))->not->toBeNull();
    expect(collect($this->getJson(p('setup'))->json('data.checks'))->firstWhere('key', 'demo')['done'])->toBeTrue();
});

it('marks a platform with restaurants as set up when the migration runs', function () {
    // The migration ran on an empty database in tests; a live site with restaurants gets done_at.
    expect(PlatformSettings::get('setup')['done_at'] ?? null)->toBeNull();
    Tenant::factory()->create();
    $migration = require database_path('migrations/2026_11_16_200002_onboarding.php');
    Schema::table('users', fn ($t) => $t->dropColumn('onboarding'));
    $migration->up();
    expect(PlatformSettings::get('setup')['done_at'])->not->toBeNull();
});
