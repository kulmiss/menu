<?php

use App\Enums\Role;
use App\Http\Controllers\SiteController;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\InlineAssets;
use App\Tenancy\CurrentTenant;

/*
 * The website's demo menus on a tablet or computer (owner, 2026-11-19): a bar on top to see
 * the menu in a phone frame (/phone/{slug}) or as it is. Real restaurants never get it.
 */

function demoMenu(string $slug = 'demo-firewood'): Tenant
{
    [, $tenant] = member(Role::Owner);
    $tenant->forceFill(['slug' => $slug])->save();
    sampleMenu($tenant);

    return $tenant->fresh();
}

it('puts the Phone / Desktop bar on every website demo menu, shown from tablet width up', function () {
    // The café and one per menu style (the website's "More menu styles" row).
    expect(SiteController::demoSlugs())->toContain('demo')->toContain('demo-firewood')->toHaveCount(1 + count(TenantAppearance::STYLES));
    $tenant = demoMenu();
    $html = $this->get('/demo-firewood')->assertOk()->getContent();

    expect($html)->toContain('<nav class="dvb" aria-label="How to show this demo"><b>Demo menu</b>')
        ->toContain('<a href="/phone/demo-firewood">')->toContain('Phone</a><a aria-current="page">')
        ->toContain('.dvb{display:none}@media (min-width:48rem){.dvb{display:flex');
});

it('never shows the bar on a real restaurant\'s menu, or in the admin preview of a demo', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    expect($this->get('/'.$tenant->slug)->getContent())->not->toContain('class="dvb"')->not->toContain('.dvb{');

    $demo = demoMenu('demo');
    [$demoOwner] = member(Role::Owner, $demo);
    $this->actingAs($demoOwner)->get(t($demo, 'appearance/preview'))->assertOk()->assertDontSee('class="dvb"', false);
});

it('shows a demo menu in a phone frame, with a QR code, not indexed', function () {
    demoMenu();
    $res = $this->get('/phone/demo-firewood')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $html = $res->getContent();

    expect($html)->toContain('<iframe src="/demo-firewood" title="')
        ->toContain('<a aria-current="page">')->toContain('<a href="/demo-firewood">')
        ->toContain('<link rel="canonical" href="'.url('/demo-firewood').'">')
        ->toContain('class="qr-svg qrcode"')->not->toContain('<?xml')
        ->not->toContain('<script');
    // The site policy allows framing our own pages, and nothing frames this one.
    expect((string) $res->headers->get('Content-Security-Policy'))->toContain("frame-src 'self'")->toContain("frame-ancestors 'none'");
});

it('frames a demo branch, and refuses anything that is not a website demo', function () {
    $tenant = demoMenu('demo');
    $branch = app(CurrentTenant::class)->runAs($tenant, fn () => Branch::create(['name' => ['en' => 'Piassa'], 'slug' => 'piassa', 'is_active' => true, 'is_default' => false]));
    $this->get('/phone/demo/piassa')->assertOk()->assertSee('<iframe src="/demo/piassa"', false);
    $this->get('/demo/piassa')->assertOk()->assertSee('<a href="/phone/demo/piassa">', false);
    $this->get('/phone/demo/nowhere')->assertNotFound();

    [, $real] = member(Role::Owner);
    $this->get('/phone/'.$real->slug)->assertNotFound();
    $this->get('/phone/demo-fine')->assertNotFound(); // a demo address nobody has
    expect(config('menu.reserved_slugs'))->toContain('phone');
});

it('keeps the bar out of the phone-sized speed check: no layout change below tablet width', function () {
    $css = InlineAssets::demoCss();
    // Everything that shows it sits inside the tablet-and-up block.
    expect(substr($css, 0, strpos($css, '@media')))->toBe('.dvb{display:none}');
});
