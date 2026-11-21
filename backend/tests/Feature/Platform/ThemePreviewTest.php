<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use Illuminate\Support\Facades\DB;

/*
 * Super Admin → Themes: a theme shown on a demo menu in the phone frame (owner, 2026-11-21).
 * Built in memory: nothing is saved and no restaurant changes.
 */

/** A website demo (the café "demo", or the Arabic "demo-mandi") with a dish photo. */
function themeDemo(string $slug, string $name = 'Demo Café'): Tenant
{
    [, $tenant] = member(Role::Owner);
    $tenant->forceFill(['slug' => $slug, 'name' => $name])->save();
    sampleMenu($tenant);
    asTenant($tenant, function () {
        $media = Media::create(['base_path' => 'dish/1', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', 'jpg' => '640.jpg']]);
        Item::query()->update(['media_id' => $media->id]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'bistro', 'primary_color' => '#111111']);
    });

    return $tenant->fresh();
}

function previewUrl(array $query = []): string
{
    return p('themes/preview').'?'.http_build_query([...['style' => 'coffee', 'primary' => '#2F5D50', 'font' => 'serif'], ...$query]);
}

it('shows a theme on the demo café with every style, saving nothing', function () {
    $tenant = themeDemo('demo');
    // The café's plan may not include the style: the preview still shows it.
    $before = DB::table('tenant_appearance')->get()->toArray();
    $admin = superAdmin();

    $html = $this->actingAs($admin)->get(previewUrl(['style' => 'burger']))->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex')->getContent();
    expect($html)->toContain('class="S-burger')->toContain('Macchiato')->toContain('#2f5d50');

    expect(DB::table('tenant_appearance')->get()->toArray())->toEqual($before);
    expect(asTenant($tenant, fn () => TenantAppearance::current()->style))->toBe('bistro');
});

it('switches light and dark, and photos on and off', function () {
    themeDemo('demo');
    $admin = superAdmin();
    $dark = $this->actingAs($admin)->get(previewUrl(['scheme' => 'dark']))->assertOk()->getContent();
    $light = $this->actingAs($admin)->get(previewUrl(['scheme' => 'light']))->assertOk()->getContent();
    expect($dark)->toContain('@media all{')->not->toContain('prefers-color-scheme:dark')
        ->and($light)->toContain('@media not all{')->not->toContain('prefers-color-scheme:dark');

    $photos = $this->actingAs($admin)->get(previewUrl(['photos' => 1]))->assertOk()->getContent();
    $text = $this->actingAs($admin)->get(previewUrl(['photos' => 0]))->assertOk()->getContent();
    expect($photos)->toContain('class="im"')->and($text)->not->toContain('class="im"');
});

it('shows right to left on the Arabic demo for the styles with a menu direction', function () {
    themeDemo('demo');
    themeDemo('demo-mandi', 'دار المندي');
    $admin = superAdmin();

    $rtl = $this->actingAs($admin)->get(previewUrl(['style' => 'coffee', 'dir' => 'rtl']))->assertOk()->getContent();
    expect($rtl)->toContain('dir="rtl"')->toContain('دار المندي');

    // B · Grid has no menu direction: always the café, left to right.
    $ltr = $this->actingAs($admin)->get(previewUrl(['style' => 'grid', 'dir' => 'rtl']))->assertOk()->getContent();
    expect($ltr)->toContain('Demo Café')->not->toContain('دار المندي')->not->toContain('dir="rtl"');
});

it('says how to create the demo menus when there are none', function () {
    $this->actingAs(superAdmin())->get(previewUrl())->assertOk()
        ->assertSee('No demo menu yet')->assertSee('php artisan demo:live');
});

it('checks the values and is for the Super Admin only', function () {
    themeDemo('demo');
    $this->actingAs(superAdmin())->getJson(previewUrl(['style' => 'nope', 'primary' => 'red']))
        ->assertInvalid(['style', 'primary']);

    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->get(previewUrl())->assertNotFound();
});
