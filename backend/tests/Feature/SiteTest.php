<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\BrandColor;
use App\Support\PlatformSettings;

it('shows the landing page with the platform name, sign-up and sign-in', function () {
    PlatformSettings::save(['name' => 'Buna Menus']);

    $html = $this->get('/')->assertOk()
        ->assertSee('<title>Buna Menus — QR code menus for restaurants</title>', false)
        ->assertSee('Your restaurant menu on every phone, in seconds')
        ->assertSee('href="/auth/sign-up"', false)
        ->assertSee('href="/auth/sign-in"', false)
        ->assertSee('href="/terms"', false)
        ->assertSee('href="#pricing"', false)
        ->assertSee('property="og:image" content="'.url('/landing/og.jpg').'"', false)
        ->assertDontSee('>Menu<', false)
        ->getContent();
    // No live demo link when the demo restaurant doesn't exist.
    expect($html)->not->toContain('See a live demo')->and($html)->not->toContain('View live');
});

it('links the live demo and the style showcases only when they exist', function () {
    Tenant::factory()->create(['slug' => 'demo']);
    Tenant::factory()->create(['slug' => 'demo-fine']);

    $this->get('/')->assertOk()
        ->assertSee('See a live demo')
        ->assertSee('href="'.url('/demo').'" target="_blank"', false)
        ->assertSee('href="'.url('/demo-fine').'"', false)
        ->assertDontSee('href="'.url('/demo-pizza').'"', false);
});

it('hides "Create a free account" when sign-up is closed', function () {
    PlatformSettings::save(['signup_open' => false]);

    $this->get('/')->assertOk()
        ->assertDontSee('href="/auth/sign-up"', false)
        ->assertSee('href="/auth/sign-in"', false);
});

it('shows the plan ladder from the database: Free first, then what each plan adds', function () {
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000 000']);
    Plan::create(['name' => ['en' => 'Trial'], 'slug' => 'trial', 'price_minor' => 0, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => true, 'is_active' => true, 'sort' => 1]);
    Plan::create(['name' => ['en' => 'Secret'], 'slug' => 'secret', 'price_minor' => 9900, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => false, 'is_active' => true, 'sort' => 3]);

    $html = $this->get('/')->assertOk()
        ->assertSeeInOrder([
            'Free · No card needed', 'Free', 'Unlimited dishes, menus and QR codes', 'Up to 100 photos', '1 branch', '2 menu styles', 'PDF menu', 'Posters, table tents and stickers', 'Import and export (spreadsheet)', 'Create a free account',
            'Plus', 'Br 490', '/ month', 'or Br 4,900 / year (2 months free)', 'Everything in Free, plus:', 'Up to 500 photos', 'All 4 menu styles', 'Specials and announcements', 'Insights and monthly report', 'Contact us on WhatsApp',
            'Pro', 'Br 990', 'Everything in Plus, plus:', 'Up to 2,000 photos', 'Up to 3 branches', 'Happy hour and lunch prices', 'No “Menu by Menu” line',
            'Business', 'Br 2,490', 'Everything in Pro, plus:', 'Unlimited photos', 'Unlimited branches',
        ])
        ->assertSee('https://wa.me/251911000000?text=', false)
        ->assertDontSee('Secret')
        ->getContent();
    expect(substr_count($html, 'class="card plan'))->toBe(4); // the free "Trial" plan isn't offered
    // Plus doesn't repeat what Free already has.
    $plus = substr($html, strpos($html, '>Plus</h3>'), 1500);
    expect($plus)->not->toContain('PDF menu')->not->toContain('Unlimited dishes')->not->toContain('1 branch');

    // Structured data with the same offers.
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    $ld = json_decode($m[1], true);
    $app = collect($ld['@graph'])->firstWhere('@type', 'SoftwareApplication');
    expect($app['offers'])->toHaveCount(4)->and($app['offers'][1])->toMatchArray(['price' => '490', 'priceCurrency' => 'ETB']);
});

it('lets the Super Admin edit the texts and switch sections off; testimonials only when real ones exist', function () {
    $this->get('/')->assertDontSee('What restaurants say');

    PlatformSettings::save([
        'landing_headline' => 'QR menus for Addis',
        'landing_sections' => [...PlatformSettings::DEFAULTS['landing_sections'], 'faq' => false, 'speed' => false],
        'landing_testimonials' => [['quote' => 'Guests order faster.', 'name' => 'Hana', 'role' => 'Kaldi Corner']],
        'socials' => [...PlatformSettings::DEFAULTS['socials'], 'telegram' => 'https://t.me/menu_et', 'facebook' => 'javascript:alert(1)'],
    ]);

    $this->get('/')->assertOk()
        ->assertSee('QR menus for Addis')
        ->assertDontSee('Questions and answers')
        ->assertDontSee('href="#faq"', false)
        ->assertDontSee('Opens fast, even on slow mobile data')
        ->assertSee('What restaurants say')
        ->assertSee('Guests order faster.')
        ->assertSee('href="https://t.me/menu_et"', false)
        ->assertDontSee('javascript:alert', false);
});

it('serves robots.txt and the sitemap', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /platform')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.url('/').'</loc>', false)
        ->assertSee('<loc>'.url('/terms').'</loc>', false);
});

it('keeps text on the brand colour readable', function () {
    foreach (['#c2410c', '#fde047', '#1e3a8a', '#22c55e'] as $hex) {
        $p = BrandColor::palette($hex);
        expect(BrandColor::contrast($p['brand'], $p['on_brand']))->toBeGreaterThanOrEqual(4.5)
            ->and(BrandColor::contrast($p['ink'], BrandColor::LIGHT_BG))->toBeGreaterThanOrEqual(4.5)
            ->and(BrandColor::contrast($p['ink_dark'], BrandColor::DARK_BG))->toBeGreaterThanOrEqual(4.5);
    }
    expect(BrandColor::palette('red')['brand'])->toBe(BrandColor::DEFAULT);
});

it('shows Terms and Privacy as headings, lists and paragraphs, with contents and date, never as raw HTML', function () {
    PlatformSettings::save([
        'terms' => "Last updated: 2 March 2027\n\nIntro line one.\nIntro line two.\n\n## 1. Using Menu\n\n- **Fair use:** no spam.\n  - not even a little\n- Write to info@kulmiss.com or https://kulmiss.com/help.\n\n## 2. <script>alert(1)</script> & more\n\nText with <b>tags</b>.",
        'privacy' => '',
    ]);

    $this->get('/terms')->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('Last updated: 2 March 2027')
        ->assertSee('On this page')
        ->assertSee('<a href="#using-menu">1. Using Menu</a>', false)
        ->assertSee('<details class="sec" id="using-menu">', false)
        ->assertSee('<summary><h2>1. Using Menu</h2>', false)
        ->assertSee('<p>Intro line one.<br>Intro line two.</p>', false)
        ->assertSee('<li><strong>Fair use:</strong> no spam.', false)
        ->assertSee('<li>not even a little</li>', false)
        ->assertSee('<a href="mailto:info@kulmiss.com">info@kulmiss.com</a>', false)
        ->assertSee('<a href="https://kulmiss.com/help">https://kulmiss.com/help</a>.', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; &amp; more', false)
        ->assertSee('Text with &lt;b&gt;tags&lt;/b&gt;.', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<b>tags</b>', false);
    $this->get('/privacy')->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('has not been published yet');
});

it('serves public pages and guest menus without any cookie', function () {
    [, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    foreach (['/', '/terms', '/privacy', '/'.$tenant->slug, '/sitemap.xml'] as $url) {
        $res = $this->get($url)->assertOk();
        expect($res->headers->getCookies())->toBe([], $url);
    }
    // The admin still gets its session cookie.
    expect($this->get('/admin')->headers->getCookies())->not->toBe([]);
});

it('shows each section as a closed, collapsible block, with the intro always visible', function () {
    PlatformSettings::save(['privacy' => "Last updated: 1 May 2027\n\nIn short:\n\n- No cookies.\n\n## 1. First\n\nOne.\n\n## 2. Second\n\n- Two.\n\n## 3. Second\n\nSame title."]);
    $html = $this->get('/privacy')->assertOk()->getContent();

    // Intro and "In short" sit before the first section, outside any <details>.
    $first = strpos($html, '<details');
    expect(strpos($html, 'In short:'))->toBeLessThan($first)
        ->and(strpos($html, '<li>No cookies.</li>'))->toBeLessThan($first);
    // Three sections, all closed, each with its own id, the contents pointing at them.
    expect(substr_count($html, '<details class="sec" id="'))->toBe(3)
        ->and($html)->not->toContain('<details class="sec" id="first" open')
        ->and($html)->toContain('<details class="sec" id="second">')
        ->and($html)->toContain('<details class="sec" id="second-2">')
        ->and($html)->toContain('<a href="#second-2">3. Second</a>')
        ->and($html)->toContain('<summary><h2>2. Second</h2>');
    // Expand / collapse all (hidden until the script runs), printing opens everything.
    expect($html)->toContain('data-all="open" hidden>Expand all</button>')
        ->and($html)->toContain('data-all="close" hidden>Collapse all</button>')
        ->and($html)->toContain("addEventListener('beforeprint'")
        ->and($html)->toContain('prefers-reduced-motion: no-preference');
});
