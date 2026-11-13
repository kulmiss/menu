<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\BrandColor;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

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

it('shows the real plans from the database, Free first and highlighted', function () {
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000 000']);
    $pro = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'price_minor' => 120000, 'currency' => 'ETB', 'interval' => 'month', 'limits' => ['items' => 300, 'branches' => 3], 'is_public' => true, 'is_active' => true, 'sort' => 2]);
    DB::table('plan_features')->insert(['plan_id' => $pro->id, 'feature_key' => 'insights', 'enabled' => true]);
    Plan::create(['name' => ['en' => 'Trial'], 'slug' => 'trial', 'price_minor' => 0, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => true, 'is_active' => true, 'sort' => 1]);
    Plan::create(['name' => ['en' => 'Secret'], 'slug' => 'secret', 'price_minor' => 9900, 'currency' => 'ETB', 'interval' => 'month', 'limits' => [], 'is_public' => false, 'is_active' => true, 'sort' => 3]);

    $html = $this->get('/')->assertOk()
        ->assertSeeInOrder(['Free · No card needed', 'Free', 'Up to 30 dishes', '1 branch', 'Create a free account', 'Pro', 'Br 1,200', '/ month', 'Up to 300 dishes', 'Unlimited photos', 'Up to 3 branches', 'Insights', 'Contact us on WhatsApp'])
        ->assertSee('https://wa.me/251911000000?text=', false)
        ->assertDontSee('Secret')
        ->getContent();
    expect(substr_count($html, 'class="card plan'))->toBe(2); // the free "Trial" plan isn't offered

    // Structured data with the same offers.
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    $ld = json_decode($m[1], true);
    $app = collect($ld['@graph'])->firstWhere('@type', 'SoftwareApplication');
    expect($app['offers'])->toHaveCount(2)->and($app['offers'][1])->toMatchArray(['price' => '1200', 'priceCurrency' => 'ETB']);
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

it('shows the Terms and Privacy texts from platform settings, escaped', function () {
    PlatformSettings::save([
        'terms' => "1. Use the menu fairly.\nNo spam.\n\n2. <script>alert(1)</script> is shown as text.",
        'privacy' => '',
    ]);

    $this->get('/terms')->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('1. Use the menu fairly.<br />', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/privacy')->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('has not been published yet');
});
