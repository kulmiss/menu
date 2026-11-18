<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\FooterVersion;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PlatformSettings;
use App\Support\Site\Footer;

/*
 * The website footer, managed in Super Admin → Footer (owner, 2026-11-18,
 * QUESTIONS #230): draft → publish, history and restore, safe links only,
 * every text escaped, on every site page and never on a guest menu.
 */

/** A valid footer body for the editor's PUT, from the defaults, with changes. */
function footerBody(array $changes = []): array
{
    return array_replace_recursive(Footer::defaults(), $changes);
}

it('is only for Super Admins', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->getJson(p('footer'))->assertNotFound();
    $this->actingAs($owner)->putJson(p('footer'), footerBody(), idem())->assertNotFound();
    $this->actingAs($owner)->postJson(p('footer/publish'), [], idem())->assertNotFound();
    $this->getJson(p('footer'))->assertNotFound();
    $this->actingAs(superAdmin())->getJson(p('footer'))->assertOk()
        ->assertJsonPath('data.changed', false)
        ->assertJsonPath('data.versions', [])
        ->assertJsonPath('data.limits', ['columns' => 6, 'links' => 12]);
});

it('starts with four columns of real pages, and every page in the list opens', function () {
    $d = Footer::defaults();
    expect(array_column($d['columns'], 'id'))->toBe(['product', 'restaurants', 'company', 'legal'])
        ->and(array_column($d['columns'][0]['links'], 'label'))->toBe(['Features', 'Plans & prices', 'See a demo menu', 'Menu styles', 'Mobile app'])
        ->and(array_column($d['columns'][1]['links'], 'label'))->toBe(['How it works', 'Sign up free', 'Sign in', 'Help centre', 'Print QR codes guide'])
        ->and(array_column($d['columns'][2]['links'], 'label'))->toBe(['About us', 'Contact us', 'Partners and resellers'])
        ->and(array_column($d['columns'][3]['links'], 'label'))->toBe(['Terms', 'Privacy', 'Cookie info', 'Delete my account']);

    Tenant::factory()->create(['slug' => 'demo']);
    foreach (Footer::PAGES as $key => $path) {
        $this->get(strtok($path, '#'))->assertOk();
    }
});

it('saves a draft that guests don\'t see until it is published, and keeps every version', function () {
    $admin = superAdmin();
    $body = footerBody(['bottom' => ['made_in' => 'Made in Addis Ababa']]);
    $body['columns'][0]['title'] = 'Our product';

    $this->actingAs($admin)->putJson(p('footer'), $body, idem())->assertOk()
        ->assertJsonPath('data.changed', true)
        ->assertJsonPath('data.draft.columns.0.title', 'Our product')
        ->assertJsonPath('data.live.columns.0.title', 'Product');
    $this->get('/terms')->assertSee('Product')->assertDontSee('Our product')->assertDontSee('Made in Addis Ababa');

    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk()
        ->assertJsonPath('data.changed', false)
        ->assertJsonPath('data.live.columns.0.title', 'Our product')
        ->assertJsonCount(1, 'data.versions');
    $this->get('/terms')->assertSee('Our product')->assertSee('Made in Addis Ababa');

    // Nothing left to publish.
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertInvalid('draft');
    expect(ActivityLog::whereIn('action', ['footer.draft_saved', 'footer.published'])->pluck('action')->all())->toBe(['footer.draft_saved', 'footer.published']);
});

it('restores an earlier version in one click, as a new version (nothing is lost)', function () {
    $admin = superAdmin();
    $first = footerBody(['bottom' => ['company' => 'First Ltd']]);
    $this->actingAs($admin)->putJson(p('footer'), $first, idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['bottom' => ['company' => 'Second Ltd']]), idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();
    $this->get('/about')->assertSee('Second Ltd');

    $old = FooterVersion::oldest('id')->firstOrFail();
    $this->actingAs($admin)->postJson(p("footer/versions/{$old->id}/restore"), [], idem())->assertOk()
        ->assertJsonCount(3, 'data.versions')
        ->assertJsonPath('data.versions.0.restored_from', $old->id)
        ->assertJsonPath('data.versions.0.live', true)
        ->assertJsonPath('data.live.bottom.company', 'First Ltd');
    $this->get('/about')->assertSee('First Ltd')->assertDontSee('Second Ltd');
    expect(ActivityLog::where('action', 'footer.restored')->count())->toBe(1);
});

it('throws a draft away', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['trust' => ['text' => 'Draft text']]), idem())->assertOk()->assertJsonPath('data.changed', true);
    $this->actingAs($admin)->deleteJson(p('footer/draft'), [], idem())->assertOk()->assertJsonPath('data.changed', false)
        ->assertJsonPath('data.draft.trust.text', Footer::defaults()['trust']['text']);
    expect(PlatformSettings::get('footer_draft'))->toBeNull();
});

it('refuses unsafe links: javascript:, data:, protocol-relative and look-alike addresses', function (string $url) {
    $body = footerBody();
    $body['columns'][2]['links'][2] = ['id' => 'partners', 'label' => 'Partners', 'page' => null, 'url' => $url, 'on' => true];
    $this->actingAs(superAdmin())->putJson(p('footer'), $body, idem())->assertInvalid('columns.2.links.2.url');
    expect(Footer::safeUrl($url))->toBeNull();
})->with([
    'javascript:alert(1)', 'JavaScript:alert(1)', ' javascript:alert(1)', "java\tscript:alert(1)", 'data:text/html,<script>alert(1)</script>',
    'vbscript:msgbox(1)', '//evil.example/path', 'https://', 'http://exa mple.com', 'mailto:not-an-email', 'ftp://example.com',
    '/about" onmouseover="alert(1)', 'tel:abc',
]);

it('allows https and http addresses, pages of this site, mailto: and tel:', function (string $url) {
    expect(Footer::safeUrl($url))->toBe($url);
    $body = footerBody();
    $body['columns'][2]['links'][2] = ['id' => 'partners', 'label' => 'Partners', 'page' => null, 'url' => $url, 'on' => true];
    $this->actingAs(superAdmin())->putJson(p('footer'), $body, idem())->assertOk();
})->with(['https://partners.example.com/apply', 'http://example.com', '/support/qr-codes', '/#pricing', 'mailto:hello@example.com', 'tel:+251912345678']);

it('checks social links, the Google Play address, phone numbers and lengths', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['socials' => ['facebook' => 'http://facebook.com/x', 'x' => 'javascript:alert(1)']]), idem())
        ->assertInvalid(['socials.facebook', 'socials.x']);
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['app' => ['play_url' => 'https://evil.example/app']]), idem())->assertInvalid('app.play_url');
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['contact' => ['whatsapp' => 'call me', 'email' => 'nope']]), idem())->assertInvalid(['contact.whatsapp', 'contact.email']);
    $long = footerBody();
    $long['columns'][0]['title'] = str_repeat('x', 61);
    $this->actingAs($admin)->putJson(p('footer'), $long, idem())->assertInvalid('columns.0.title');
    $many = footerBody();
    $many['columns'] = array_fill(0, 7, $many['columns'][0]);
    $this->actingAs($admin)->putJson(p('footer'), $many, idem())->assertInvalid('columns');
    $empty = footerBody();
    $empty['columns'][0]['links'][0] = ['id' => 'x', 'label' => 'Nowhere', 'page' => null, 'url' => '', 'on' => true];
    $this->actingAs($admin)->putJson(p('footer'), $empty, idem())->assertInvalid('columns.0.links.0.url');

    $ok = footerBody(['socials' => ['instagram' => 'https://instagram.com/menu'], 'app' => ['play_url' => 'https://play.google.com/store/apps/details?id=com.example.menu'],
        'contact' => ['whatsapp' => '+251 91 234 5678', 'phone' => '+251 11 123 4567', 'email' => 'hello@example.com', 'address' => 'Bole, Addis Ababa', 'hours' => 'Mon–Sat 08:00–18:00']]);
    $this->actingAs($admin)->putJson(p('footer'), $ok, idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();
    $html = (string) $this->get('/')->getContent();
    expect($html)->toContain('href="https://wa.me/251912345678?text=')
        ->toContain('href="tel:+251111234567"')->toContain('href="mailto:hello@example.com"')
        ->toContain('Bole, Addis Ababa')->toContain('Mon–Sat 08:00–18:00')
        ->toContain('href="https://instagram.com/menu"')
        ->toContain('<img src="/images/google-play-badge.png"')
        // Social profiles for search engines come from the live footer.
        ->toContain('"sameAs":["https://instagram.com/menu"]');
});

it('escapes every text: no HTML or script from the editor reaches the page', function () {
    $evil = '<script>alert(1)</script><img src=x onerror=alert(2)>';
    $body = footerBody(['trust' => ['on' => true, 'text' => $evil], 'bottom' => ['made_in' => $evil, 'company' => $evil], 'contact' => ['address' => $evil, 'title' => $evil]]);
    $body['columns'][0]['title'] = $evil;
    $body['columns'][0]['links'][0]['label'] = $evil;
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('footer'), $body, idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();

    auth()->guard('web')->logout(); // a guest sees the sign-in page (a signed-in user is sent on)
    $this->app['auth']->forgetGuards();

    foreach (['/', '/terms', '/signin'] as $page) {
        $html = (string) $this->get($page)->getContent();
        expect($html)->not->toContain('<script>alert(1)')->not->toContain('<img src=x')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    }
});

it('hides what is off or empty: parts, columns, links, contact, socials, badge, trust line', function () {
    $body = footerBody(['illustration' => false, 'contact' => ['on' => false, 'email' => 'hello@example.com'], 'trust' => ['on' => false], 'app' => ['on' => false, 'play_url' => 'https://play.google.com/store/apps/details?id=x'], 'bottom' => ['back_to_top' => false]]);
    $body['columns'][3]['on'] = false;
    $body['columns'][0]['links'][1]['on'] = false;
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('footer'), $body, idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();

    $html = (string) $this->get('/about')->getContent();
    expect($html)->toContain('class="sf"')
        ->not->toContain('class="sf-art"')->not->toContain('class="sf-contact"')->not->toContain('class="sf-trust"')
        ->not->toContain('google-play-badge')->not->toContain('class="sf-up"')->not->toContain('class="sf-social"')
        ->not->toContain('>Legal<')->not->toContain('>Plans &amp; prices<')
        ->toContain('>Features<');
});

it('leaves out pages that don\'t exist right now: the demo menu without a demo, sign-up when it is closed', function () {
    $html = (string) $this->get('/terms')->getContent();
    expect($html)->not->toContain('See a demo menu')->toContain('href="/signup"');
    Tenant::factory()->create(['slug' => 'demo']);
    PlatformSettings::save(['signup_open' => false]);
    $html = (string) $this->get('/terms')->getContent();
    expect($html)->toContain('href="/demo"')->not->toContain('href="/signup"');
});

it('shows the same footer on the landing page, the account pages and every site page, and never on a guest menu', function () {
    foreach (['/', '/signin', '/signup', '/forgot-password', '/terms', '/privacy', '/about', '/contact', '/cookies', '/support', '/support/menu-styles'] as $page) {
        $this->get($page)->assertOk()->assertSee('class="sf"', false)->assertSee('aria-label="Website footer"', false);
    }
    // The Super Admin's own sign-in page is plain (not linked from anywhere).
    $this->get('/'.config('menu.super_admin.path').'/sign-in')->assertOk()->assertDontSee('class="sf"', false);

    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create(TenantAppearance::DEFAULTS));
    $this->get('/'.$tenant->slug)->assertOk()->assertDontSee('class="sf"', false)->assertDontSee('Website footer');
});

it('previews the draft and the live footer, light and dark, for the editor only', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('footer'), footerBody(['bottom' => ['company' => 'Draft Co']]), idem())->assertOk();

    $this->actingAs($admin)->get(p('footer/preview?scheme=dark'))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('color-scheme:dark', false)->assertSee('Draft Co');
    $this->actingAs($admin)->get(p('footer/preview?source=live'))->assertOk()->assertDontSee('Draft Co');
    // Framed only by the admin itself.
    $csp = (string) $this->actingAs($admin)->get(p('footer/preview'))->headers->get('Content-Security-Policy');
    expect($csp)->toContain("frame-ancestors 'self'");

    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->get(p('footer/preview'))->assertNotFound();
});

it('shows About us, Contact us and Cookie info with their default text, and a restaurant that owned the address keeps it', function () {
    $this->get('/about')->assertOk()->assertSee('What we do');
    $this->get('/cookies')->assertOk()->assertSee('Menu pages and the public pages of this website set no cookies.');
    PlatformSettings::save(['contact' => "## Write to us\n\nWe answer within a day."]);
    $this->get('/contact')->assertOk()->assertSee('We answer within a day.');

    // An old restaurant at /about (before the address was reserved) still shows its menu there.
    $tenant = Tenant::factory()->create(['slug' => 'about']);
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create(TenantAppearance::DEFAULTS));
    $this->get('/about')->assertOk()->assertSee('Macchiato')->assertDontSee('What we do');
    // New restaurants can't take these addresses.
    expect(config('menu.reserved_slugs'))->toContain('about', 'contact', 'cookies');
});

it('has a Help centre for everyone with the public articles only', function () {
    $this->get('/support')->assertOk()->assertSee('Help centre')->assertSee('Delete my account')->assertSee('Menu styles A to L')
        ->assertDontSee('href="/support/dashboard"', false);
    $this->get('/support/delete-account')->assertOk()->assertSee('Only the <strong>Owner</strong>', false)
        // A link to another public article opens it; one to an admin-only article is plain text.
        ->assertSee('href="/support/contact-support"', false)->assertDontSee('help:import-export', false);
    $this->get('/support/dashboard')->assertNotFound();
    $this->get('/support/no-such-article')->assertNotFound();
    expect((string) $this->get('/sitemap.xml')->getContent())->toContain('/support/menu-styles')->toContain('/cookies');
});

it('lets a switched-off link wait for its address, but not a switched-on one', function () {
    $admin = superAdmin();
    $body = footerBody();
    $body['columns'][0]['links'][0] = ['id' => 'later', 'label' => 'Coming soon', 'page' => null, 'url' => '', 'on' => false];
    $this->actingAs($admin)->putJson(p('footer'), $body, idem())->assertOk();
    $body['columns'][0]['links'][0]['on'] = true;
    $this->actingAs($admin)->putJson(p('footer'), $body, idem())->assertInvalid(['columns.0.links.0.url' => 'Pick a page or type a web address.']);
});

it('never shows an unsafe old social link before the first publish', function () {
    // Social links saved before the footer editor existed were never checked.
    PlatformSettings::save(['socials' => [...PlatformSettings::DEFAULTS['socials'], 'facebook' => 'javascript:alert(1)', 'telegram' => 'https://t.me/menu_et']]);
    Footer::forget();

    $html = (string) $this->get('/')->getContent();
    expect($html)->not->toContain('javascript:alert')->toContain('href="https://t.me/menu_et"');
    expect(Footer::published()['socials']['facebook'])->toBe('');
});

it('looks the same on every page: its CSS sets what page styles would change', function () {
    // Found in the browser: Terms/Help pages gave the address and hours lines paragraph
    // margins, sign-in gave each column a top margin and coloured column titles.
    $css = (string) $this->get('/terms')->getContent();
    expect($css)->toContain('.sf-ci{margin:0;')
        ->toContain('.sf-col{margin:0;')
        ->toContain('.sf-col summary{color:inherit;font-weight:inherit;')
        ->toContain('.sf h2{margin:0;line-height:1.3;');
});

it('gives every site page a description for search engines', function () {
    PlatformSettings::save(['name' => 'Buna Menus']);
    foreach (['terms', 'privacy', 'about', 'contact', 'cookies'] as $page) {
        $this->get('/'.$page)->assertOk()->assertSee('<meta name="description" content="', false)->assertSee('Buna Menus', false);
    }
});

it('styles only the QR code as a white tile, not the small link icon next to it', function () {
    // Bug (owner screenshot, 2026-11-18): ".scan svg" turned the "opens in a new tab" icon into a white dot.
    $html = (string) $this->get('/')->getContent();
    expect($html)->toContain('.scan>svg{width:132px')->not->toContain('.scan svg{');
});
