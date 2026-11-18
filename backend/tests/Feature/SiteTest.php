<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\BrandColor;
use App\Support\PlatformSettings;

it('shows the landing page in simple English: hero, sections in order, sign-up and sign-in', function () {
    PlatformSettings::save(['name' => 'Buna Menus']);

    $html = $this->get('/')->assertOk()
        ->assertSee('<title>Buna Menus — Free QR code menu for your restaurant</title>', false)
        ->assertSee('Your menu on every phone. Free to start.')
        ->assertSee('Free plan forever')
        ->assertSee('No card needed')
        ->assertSee('href="/signup" data-track="signup"', false)
        ->assertSee('href="/signin"', false)
        ->assertSee('href="/terms"', false)
        ->assertSee('href="#pricing"', false)
        ->assertSee('property="og:image" content="'.url('/landing/og.jpg').'"', false)
        ->assertSee('type="image/avif"', false)
        ->assertSeeInOrder(['Paper menus cost you time and money', 'Ready in 3 easy steps', 'Everything you need in one place', 'Paper menu or QR menu?', 'Simple prices', 'Questions and answers', 'Start your free menu today'])
        ->assertDontSee('>Menu<', false)
        ->getContent();
    // The speed test section is gone; no demo section or link while the demo restaurant doesn't exist.
    expect($html)->not->toContain('slow mobile data')->not->toContain('1.3 s')
        ->not->toContain('See a demo menu')->not->toContain('id="demo"')
        // Hidden until the Super Admin switches it on with real quotes.
        ->not->toContain('Customers say');
    // Every section ends with one button.
    expect(substr_count($html, 'data-track="signup"'))->toBeGreaterThanOrEqual(7);
});

it('uses only WebP for a screenshot whose AVIF copies are missing (GD without AVIF)', function () {
    $moved = [];
    foreach ([300, 450, 600] as $w) {
        $file = public_path("landing/hero-{$w}.avif");
        rename($file, $file.'.off');
        $moved[] = $file;
    }
    try {
        $html = $this->get('/')->assertOk()->getContent();
        expect($html)->not->toContain('landing/hero-300.avif')
            ->toContain('<link rel="preload" as="image" type="image/webp" imagesrcset="'.asset('landing/hero-300.webp').' 300w')
            // The dark one still has AVIF.
            ->toContain(asset('landing/hero-dark-300.avif').' 300w');
    } finally {
        foreach ($moved as $file) {
            rename($file.'.off', $file);
        }
    }
});

it('shows "See it on your phone" with the demo menu: a QR code on desktop, a button on phones, the other looks', function () {
    Tenant::factory()->create(['slug' => 'demo']);
    Tenant::factory()->create(['slug' => 'demo-fine']);

    $html = $this->get('/')->assertOk()
        ->assertSee('See a demo menu')
        ->assertSee('href="#demo"', false)
        ->assertSee('Try a real menu right now')
        ->assertSee('Scan with your phone')
        ->assertSee('Open the demo menu')
        ->assertSee('href="'.url('/demo').'" target="_blank" rel="noopener" data-track="demo"', false)
        ->assertSee('href="'.url('/demo-fine').'"', false)
        ->assertDontSee('href="'.url('/demo-pizza').'"', false)
        ->getContent();
    expect(substr_count($html, '<div class="scan">'))->toBe(1)
        ->and(substr_count($html, '<div class="open-demo">'))->toBe(1);
});

it('turns every sign-up button into "Contact us on WhatsApp" when sign-up is off', function () {
    PlatformSettings::save(['signup_open' => false, 'upgrade_whatsapp' => '+251 911 000 000']);

    $html = $this->get('/')->assertOk()
        ->assertDontSee('href="/signup"', false)
        ->assertDontSee('Create my free menu')
        ->assertSee('href="/signin"', false)
        ->assertSee('Contact us on WhatsApp')
        ->assertSee('Message us, and we will help you start.')
        ->getContent();
    expect(substr_count($html, 'href="https://wa.me/251911000000?text=Hello%2C%20I%20would%20like%20a%20menu%20on%20Menu."'))->toBeGreaterThanOrEqual(7);

    // No WhatsApp number: the support e-mail; neither: only "Sign in".
    PlatformSettings::save(['upgrade_whatsapp' => null, 'support_email' => 'help@example.com']);
    $this->get('/')->assertSee('Contact us by email')->assertSee('href="mailto:help@example.com?subject=', false);
    PlatformSettings::save(['support_email' => null]);
    $this->get('/')->assertOk()->assertDontSee('Contact us by email')->assertDontSee('Contact us on WhatsApp')->assertSee('href="/signin"', false)
        // No demo and no sign-up: no QR code pointing at a closed page.
        ->assertDontSee('class="qr-card', false);
});

it('shows "Questions? Chat with us" only when a WhatsApp number is set', function () {
    $this->get('/')->assertDontSee('Questions? Chat with us');

    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000 000']);
    $this->get('/')
        ->assertSee('Questions? Chat with us')
        ->assertSee('Chat with us on WhatsApp')
        ->assertSee('href="https://wa.me/251911000000?text=Hello%2C%20I%20have%20a%20question%20about%20Menu."', false);
});

it('never promises more than the Free plan gives', function () {
    $html = $this->get('/')->getContent();
    // The seeded Free plan has no promotions, happy hour or insights.
    expect($html)->toMatch('#Promotions and happy hour <span class="tag">Paid plans</span>#')
        ->toMatch('#See how many people looked <span class="tag">Paid plans</span>#')
        ->not->toMatch('#PDF menu <span class="tag">#');

    // Without a free sign-up plan: no "Free to start", "Free plan forever" or "No card needed" in the final band.
    Plan::where('slug', 'free')->update(['price_minor' => 1000]);
    $this->get('/')->assertOk()
        ->assertSee('Your menu on every phone.')
        ->assertDontSee('Free to start')
        ->assertDontSee('Free plan forever')
        ->assertDontSee('Free to start')
        ->assertSee('It takes about 5 minutes. We are here to help.');
});

it('shows the plan ladder from the database: Free first ("Start here"), then what each plan adds, a sign-up button on each', function () {
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000 000']);
    Plan::create(['name' => ['en' => 'Trial'], 'slug' => 'trial', 'price_minor' => 0, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => true, 'is_active' => true, 'sort' => 1]);
    Plan::create(['name' => ['en' => 'Secret'], 'slug' => 'secret', 'price_minor' => 9900, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => false, 'is_active' => true, 'sort' => 3]);

    $html = $this->get('/')->assertOk()
        ->assertSeeInOrder([
            'Start here', 'Free', 'Unlimited dishes, menus and QR codes', 'Up to 100 photos', '1 branch', '2 menu styles', 'PDF menu', 'Posters, table tents and stickers', 'Import and export (spreadsheet)', 'Create my free menu',
            'Plus', 'Br 490', '/ month', 'or Br 4,900 / year (2 months free)', 'Everything in Free, plus:', 'Up to 500 photos', 'All 17 menu styles', 'Specials and announcements', 'Insights and monthly report', 'Start free', 'Contact us on WhatsApp',
            'Pro', 'Br 990', 'Everything in Plus, plus:', 'Up to 2,000 photos', 'Up to 3 branches', 'Happy hour and lunch prices', 'No “Menu by Menu” line', 'Start free',
            'Business', 'Br 2,490', 'Everything in Pro, plus:', 'Unlimited photos', 'Unlimited branches', 'Start free',
        ])
        ->assertSee('https://wa.me/251911000000?text=', false)
        ->assertDontSee('Secret')
        ->getContent();
    expect(substr_count($html, 'class="card plan'))->toBe(4); // the free "Trial" plan isn't offered
    // Plus doesn't repeat what Free already has.
    $plus = substr($html, strpos($html, '>Plus</h3>'), 1500);
    expect($plus)->not->toContain('PDF menu')->not->toContain('Unlimited dishes')->not->toContain('1 branch');

    // Structured data with the same offers, and the questions and answers.
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    $ld = json_decode($m[1], true);
    $app = collect($ld['@graph'])->firstWhere('@type', 'SoftwareApplication');
    expect($app['offers'])->toHaveCount(4)->and($app['offers'][1])->toMatchArray(['price' => '490', 'priceCurrency' => 'ETB']);
    $faq = collect($ld['@graph'])->firstWhere('@type', 'FAQPage');
    expect($faq['mainEntity'])->toHaveCount(8)
        ->and($faq['mainEntity'][0])->toMatchArray(['@type' => 'Question', 'name' => 'Is it really free?'])
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Yes. The Free plan has no time limit. You never need a card.');
});

it('lets the Super Admin edit the texts, switch sections off and reorder them; "Customers say" only with real, ticked quotes', function () {
    PlatformSettings::save([
        'landing_headline' => 'QR menus for Addis',
        'landing_sections' => [...PlatformSettings::DEFAULTS['landing_sections'], 'faq' => false, 'speed' => true],
        'landing_order' => ['pricing', 'nonsense', 'how'],
        'landing_testimonials' => [
            ['quote' => 'Guests order faster.', 'name' => 'Hana', 'role' => 'Kaldi Corner', 'real' => true],
            ['quote' => 'Made-up praise.', 'name' => 'Nobody', 'role' => '', 'real' => false],
        ],
        'socials' => [...PlatformSettings::DEFAULTS['socials'], 'telegram' => 'https://t.me/menu_et', 'facebook' => 'javascript:alert(1)'],
    ]);

    // Switched off by default, even with a real quote.
    $this->get('/')->assertDontSee('Customers say');

    PlatformSettings::save(['landing_sections' => [...PlatformSettings::DEFAULTS['landing_sections'], 'faq' => false, 'testimonials' => true]]);
    $this->get('/')->assertOk()
        ->assertSee('QR menus for Addis')
        ->assertDontSee('Questions and answers')
        ->assertDontSee('href="#faq"', false)
        // Saved order first, the rest after it in their usual order; unknown keys ignored.
        ->assertSeeInOrder(['id="pricing"', 'id="how"', 'id="problem"', 'id="features"', 'id="compare"', 'id="testimonials"', 'cta-title'], false)
        ->assertSee('Guests order faster.')
        ->assertDontSee('Made-up praise.')
        ->assertSee('href="https://t.me/menu_et"', false)
        ->assertDontSee('javascript:alert', false);
});

it('serves robots.txt and the sitemap', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')
        // The Super Admin's own address is never listed (QUESTIONS #217).
        ->assertDontSee(config('menu.super_admin.path'))
        ->assertDontSee('platform')
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

it('shows friendly error pages, light and dark, without cookies (pre-launch check)', function () {
    $res = $this->get('/no-such-restaurant-here')->assertNotFound()
        ->assertSee('Page not found')->assertSee('prefers-color-scheme:dark', false)->assertSee(url('/'), false);
    expect($res->headers->getCookies())->toBe([]);
    foreach ([403, 419, 429, 500, 503] as $code) {
        $html = view("errors.{$code}")->render();
        expect($html)->toContain(__("errors.{$code}.title"))->not->toContain('errors.');
    }
    expect(view('errors.503')->render())->not->toContain('<a href'); // maintenance: no link that leads back here
});
