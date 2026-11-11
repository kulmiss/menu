<?php

use App\Enums\Role;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Support\PublicMenu\InlineAssets;

it('shows a "Share menu" button by default that loads the share code on demand', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();

    expect($html)->toContain('data-x="H" aria-label="Share menu"');
    // The share code is not in the inline script, only in the on-demand file.
    expect(InlineAssets::js())->not->toContain('navigator.share')->not->toContain('wa.me');
    $file = (string) file_get_contents(public_path(ltrim(InlineAssets::sheetUrl(), '/')));
    expect($file)->toContain('window.mH')->toContain('location.pathname')->toContain('https://t.me/share/url');
});

it('can switch the button off in Appearance', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $values = ['primary_color' => '#b4432a', 'font' => 'system', 'style' => 'grid', 'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true];

    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertJsonPath('data.show_share', true);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$values, 'show_share' => false], idem())->assertOk()
        ->assertJsonPath('data.show_share', false);

    $this->get('/'.$tenant->slug)->assertOk()->assertDontSee('data-x="H"', false);
});

it('gives the shared link a good preview: name, branch, description and the banner', function () {
    $tenant = Tenant::factory()->create(['name' => 'Buna & Bread']);
    sampleMenu($tenant);
    asTenant($tenant, function () {
        TenantProfile::create(['description' => ['en' => "Ethiopian coffee roasted every Monday.\nFresh bread every morning."]]);
        $cover = Media::create(['base_path' => 'c/1', 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg']]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'cover_media_id' => $cover->id]);
    });

    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('<meta property="og:title" content="Buna &amp; Bread">', false)
        ->assertSee('<meta property="og:description" content="Ethiopian coffee roasted every Monday. Fresh bread every morning.">', false)
        ->assertSee('/c/1/og.jpg"><meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

it('falls back to a plain description without info', function () {
    $tenant = Tenant::factory()->create(['name' => 'Tiny Café']);
    sampleMenu($tenant);

    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('content="The menu of Tiny Café: dishes, prices and opening hours."', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false);
});
