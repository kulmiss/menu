<?php

use App\Enums\Role;
use App\Http\Controllers\SiteController;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuPageCache;
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

/** A demo menu with a dish photo (the layout switch needs photos). */
function demoWithPhoto(string $slug = 'demo-firewood', string $style = 'grill'): Tenant
{
    $tenant = demoMenu($slug);
    asTenant($tenant, function () use ($style) {
        $media = Media::create(['base_path' => 'dish/1', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', 'jpg' => '640.jpg']]);
        Item::query()->update(['media_id' => $media->id]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style]);
    });

    return $tenant;
}

// Owner, 2026-11-21: on the website demos, try the style's other layouts (QUESTIONS #242).
it('lets a visitor try Grid, List or Text on a demo, from the bar, without saving or caching it', function () {
    $tenant = demoWithPhoto();
    $own = $this->get('/demo-firewood')->assertOk()->getContent();
    // N · Grill's own layout is the list: marked; the others are links.
    expect($own)->toContain('<span class="dvs dvl" role="group" aria-label="Layout"><a href="/demo-firewood?layout=grid">Grid</a><a aria-current="true">List</a><a href="/demo-firewood?layout=text">Text</a></span>');

    $grid = $this->get('/demo-firewood?layout=grid')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')->assertHeader('Cache-Control', 'max-age=300, public')->getContent();
    expect($grid)->toContain('<body class="S-grill L-grid')
        // Back to the own layout goes to the plain (static) address; the phone frame keeps the layout.
        ->toContain('<a href="/demo-firewood">List</a>')->toContain('<a aria-current="true">Grid</a>')
        ->toContain('<a href="/phone/demo-firewood?layout=grid">');
    $text = $this->get('/demo-firewood?layout=text')->assertOk()->getContent();
    expect($text)->toContain('<body class="S-grill NP')->not->toContain('dish/1/')->toContain('<a aria-current="true">Text</a>');

    // Nothing saved; the static copy and the plain address keep the demo's own look.
    expect(asTenant($tenant, fn () => TenantAppearance::first()->menu_layout))->toBeNull()
        ->and(asTenant($tenant, fn () => TenantAppearance::first()->show_photos))->toBeTrue();
    expect($this->get('/demo-firewood')->getContent())->toContain('<body class="S-grill dc"')->toContain('dish/1/');
    $cached = MenuPageCache::path('demo-firewood', null);
    if (is_file($cached)) {
        expect((string) file_get_contents($cached))->not->toContain('L-grid');
    }
});

it('ignores ?layout on a real restaurant, an unknown value, and hides the switch on a demo without photos', function () {
    [, $real] = member(Role::Owner);
    sampleMenu($real);
    // A real restaurant (style B, no photos): the address is ignored, its own page shows.
    $this->get('/'.$real->slug.'?layout=list')->assertOk()->assertSee('<body class="S-grid NP">', false)->assertDontSee('class="dvb"', false);

    demoWithPhoto();
    $this->get('/demo-firewood?layout=cards')->assertOk()->assertSee('<body class="S-grill dc">', false)->assertSee('<a aria-current="true">List</a>', false);

    demoMenu('demo-nine-wells-coffee');   // no photos at all
    $this->get('/demo-nine-wells-coffee')->assertOk()->assertSee('class="dvb"', false)->assertDontSee('class="dvs dvl"', false);
});

it('switches the layout in the phone frame too', function () {
    demoWithPhoto();
    $this->get('/phone/demo-firewood')->assertOk()
        ->assertSee('<iframe src="/demo-firewood" ', false)
        ->assertSee('<a href="/phone/demo-firewood?layout=grid">Grid</a><a aria-current="true">List</a>', false);
    $this->get('/phone/demo-firewood?layout=grid')->assertOk()
        ->assertSee('<iframe src="/demo-firewood?layout=grid" ', false)
        ->assertSee('<a href="/phone/demo-firewood">List</a>', false)
        ->assertSee('<a href="/demo-firewood?layout=grid">', false);
});
