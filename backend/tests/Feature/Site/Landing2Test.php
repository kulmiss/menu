<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/*
 * Landing 2 (owner, 2026-11-22): a new home page design at /landing2 (a preview: noindex,
 * canonical "/") that the Super Admin can make the home page, and switch back.
 */

it('shows Landing 2 at /landing2 as a preview: noindex, canonical to the home page', function () {
    $html = $this->get('/landing2')->assertOk()->getContent();
    expect($html)->toContain('<meta name="robots" content="noindex">')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('Menus guests love to scan.')
        ->toContain('id="how"')->toContain('id="styles"')->toContain('id="faq"')
        // No structured data while it is only a preview.
        ->not->toContain('application/ld+json');
    // Every style A–T, named, with its phone screenshot.
    expect(substr_count($html, 'class="sty"'))->toBe(20)
        ->and($html)->toContain('A · Editorial')->toContain('T · Juice &amp; Drinks')->toContain('landing/styles/juice-180.webp');
    // The site footer with the legal pages, and no cookies.
    expect($html)->toContain('href="/terms"')->toContain('href="/privacy"');
    $this->get('/landing2')->assertCookieMissing('laravel_session');
});

it('reads the plans and prices live from the plans', function () {
    $paid = Plan::where('is_public', true)->where('is_active', true)->where('price_minor', '>', 0)->orderBy('sort')->first();
    expect($paid)->not->toBeNull();
    $paid->forceFill(['price_minor' => 123_400])->save();
    $html = $this->get('/landing2')->assertOk()->getContent();
    expect($html)->toContain('id="pricing"')->toContain('1,234');

    // No public plans: no prices section (never made-up prices).
    DB::table('plans')->update(['is_public' => false]);
    expect($this->get('/landing2')->getContent())->not->toContain('id="pricing"');
});

it('keeps Landing 1 as the home page until the Super Admin picks Landing 2, and switches back', function () {
    $landing1 = $this->get('/')->assertOk()->getContent();
    expect($landing1)->not->toContain('Menus guests love to scan.');
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(p('settings'), ['home_design' => 'landing2'], idem())->assertOk()
        ->assertJsonPath('data.home_design', 'landing2');
    $home = $this->get('/')->assertOk()->getContent();
    expect($home)->toContain('Menus guests love to scan.')
        ->not->toContain('name="robots"')
        ->toContain('application/ld+json')
        ->toContain('<link rel="canonical" href="'.url('/').'">');
    expect(DB::table('activity_log')->where('action', 'platform.home_design_changed')->latest('id')->value('changes'))
        ->toBe(json_encode(['home_design' => ['from' => 'landing1', 'to' => 'landing2']]));
    // /landing2 itself stays noindex (the home page is the one to index).
    expect($this->get('/landing2')->getContent())->toContain('content="noindex"');

    $this->actingAs($admin)->putJson(p('settings'), ['home_design' => 'landing1'], idem())->assertOk();
    $back = $this->get('/')->assertOk()->getContent();
    $mask = fn (string $h) => preg_replace('/nonce="[^"]*"/', 'nonce="N"', $h);
    expect($mask($back))->toBe($mask($landing1));
    expect(DB::table('activity_log')->where('action', 'platform.home_design_changed')->count())->toBe(2);
    // Saving the same choice again is not a change.
    $this->actingAs($admin)->putJson(p('settings'), ['home_design' => 'landing1'], idem())->assertOk();
    expect(DB::table('activity_log')->where('action', 'platform.home_design_changed')->count())->toBe(2);
});

it('accepts only the two designs, from a Super Admin', function () {
    $this->actingAs(superAdmin())->putJson(p('settings'), ['home_design' => 'landing3'], idem())->assertInvalid('home_design');
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->putJson(p('settings'), ['home_design' => 'landing2'], idem())->assertNotFound();
    expect(PlatformSettings::get('home_design'))->toBe('landing1');
});

it('keeps /landing2 out of the sitemap and free as a restaurant address', function () {
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('landing2');
    expect(config('menu.reserved_slugs'))->toContain('landing2');
});

it('turns right to left for an Arabic site language', function () {
    App::setLocale('ar');
    expect($this->get('/landing2')->getContent())->toContain('dir="rtl"');
    App::setLocale('en');
    expect($this->get('/landing2')->getContent())->toContain('dir="ltr"');
});
