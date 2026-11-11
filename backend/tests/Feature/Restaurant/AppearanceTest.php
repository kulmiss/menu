<?php

use App\Enums\Role;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\Theme;
use App\Support\Contrast;

/** A media row for the restaurant (files are not needed here). */
function fakeMedia(Tenant $tenant): Media
{
    return asTenant($tenant, fn () => Media::create([
        'base_path' => $tenant->id.'/x'.random_int(1, 99999), 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
    ]));
}

function appearanceValues(array $overrides = []): array
{
    return [
        'primary_color' => '#1d4ed8', 'font' => 'serif', 'style' => 'grid',
        'show_prices' => true, 'show_photos' => true, 'show_descriptions' => false, 'show_calories' => true,
        ...$overrides,
    ];
}

it('returns the defaults, the theme presets and no warnings for a new restaurant', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertOk()
        ->assertJsonPath('data.primary_color', '#b4432a')
        ->assertJsonPath('data.style', 'grid')
        ->assertJsonPath('data.styles', ['editorial', 'grid', 'bistro'])
        ->assertJsonPath('data.warnings', [])
        ->assertJsonCount(Theme::count(), 'data.themes')
        ->assertJsonPath('data.themes.0.name', 'Classic');
});

it('saves the settings, warns about low contrast and clears the static page', function () {
    [$owner, $tenant] = member(Role::Owner);
    $version = $tenant->cache_version;

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), appearanceValues(['primary_color' => '#fde047']), idem())
        ->assertOk()
        ->assertJsonPath('data.style', 'grid')
        ->assertJsonPath('data.warnings.0.key', 'appearance.contrast.light');

    expect($tenant->fresh()->cache_version)->toBeGreaterThan($version);
    expect(asTenant($tenant, fn () => TenantAppearance::first()->font))->toBe('serif');
});

it('lets owners and managers change the look, but not staff', function (Role $role, int $status) {
    [$user, $tenant] = member($role);

    $this->actingAs($user)->putJson(t($tenant, 'appearance'), appearanceValues(), idem())->assertStatus($status);
})->with([[Role::Owner, 200], [Role::Manager, 200], [Role::Staff, 403]]);

it('rejects bad values and another restaurant\'s photo', function () {
    [$owner, $tenant] = member(Role::Owner);
    $foreign = fakeMedia(Tenant::factory()->create());

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), appearanceValues([
        'primary_color' => 'red', 'font' => 'comic', 'style' => 'cards', 'logo_media_id' => $foreign->id,
    ]), idem())->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color', 'font', 'style', 'logo_media_id']);
});

it('deletes a replaced logo that nothing else uses', function () {
    [$owner, $tenant] = member(Role::Owner);
    $first = fakeMedia($tenant);
    $second = fakeMedia($tenant);

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), appearanceValues(['logo_media_id' => $first->id]), idem())->assertOk();
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), appearanceValues(['logo_media_id' => $second->id]), idem())
        ->assertOk()->assertJsonPath('data.logo.id', $second->id);

    expect(asTenant($tenant, fn () => Media::find($first->id)))->toBeNull();
});

it('previews unsaved settings without saving or caching them', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.http_build_query([
        'primary_color' => '#1d4ed8', 'style' => 'editorial', 'font' => 'serif', 'show_prices' => '0',
    ]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('S-editorial', false)
        ->assertSee('--a:#1d4ed8', false)
        ->assertSee('Palatino', false)
        ->assertSee('"p":1', false)
        ->assertSee('Macchiato')->assertDontSee('Br 120');

    expect(asTenant($tenant, fn () => TenantAppearance::count()))->toBe(0);
    $this->actingAs($owner)->getJson(t($tenant, 'appearance/preview').'?style=table')->assertInvalid('style');
});

it('shows the saved look on the guest page', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    $logo = fakeMedia($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, ...appearanceValues([
        'primary_color' => '#fde047', 'show_prices' => false, 'logo_media_id' => $logo->id,
    ])]));

    $html = $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('S-grid', false)
        ->assertSee('class="lg"', false)
        ->assertDontSee('Br 120')
        ->getContent();

    // Yellow is unreadable on white: the page uses a darker shade that passes 4.5:1.
    preg_match('/--a:(#[0-9a-f]{6});--af/', (string) $html, $m);
    expect($m[1])->not->toBe('#fde047')
        ->and(Contrast::ratio($m[1], '#f5f7f6'))->toBeGreaterThanOrEqual(4.5);
});
