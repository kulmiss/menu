<?php

use App\Models\User;
use App\Support\Landing\LandingTexts;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// Super Admin → Landing page (owner, 2026-11-17).

it('lists every page text with its default, and only for the Super Admin', function () {
    // Hidden from everyone else (the platform area answers 404).
    $this->actingAs(User::factory()->create())->getJson(p('landing'))->assertNotFound();

    $res = $this->actingAs(superAdmin())->getJson(p('landing'))->assertOk();
    expect($res->json('data.defaults'))
        ->toHaveKey('hero.headline', 'Your menu on every phone. Free to start.')
        ->toHaveKey('faq.items.help.a', 'Message us any time. A real person will help you.')
        ->toHaveKey('compare.rows.4.1', 'Share one link anywhere.')
        ->not->toHaveKey('pricing.feature.pdf_menu') // plan lines come from Plans
        ->not->toHaveKey('social.facebook');
    expect($res->json('data.max')['hero.headline'])->toBe(160)
        ->and($res->json('data.max')['faq.items.free.a'])->toBe(400)
        ->and($res->json('data.order'))->toBe(PlatformSettings::DEFAULTS['landing_order'])
        ->and($res->json('data.sections.testimonials'))->toBeFalse()
        ->and($res->json('data.images.hero.custom'))->toBeFalse()
        ->and($res->json('data.clicks.days'))->toHaveCount(30);
});

it('saves changed texts, sections and order; the landing page shows them everywhere', function () {
    $admin = superAdmin();
    PlatformSettings::save(['landing_headline' => 'Old headline']);

    $this->actingAs($admin)->putJson(p('landing'), [
        'texts' => [
            'hero.headline' => '  Menus for Addis  ',
            'hero.subline' => '',
            'problem.items.old.title' => 'Prices change every week',
            'faq.items.app.q' => 'Do guests need an app?',
            'compare.rows.0.1' => 'New price in one tap.',
            'meta_title' => ':name — menus for Addis',
            'how.title' => 'Ready in 3 easy steps', // same as the default: not stored
            'nonsense.key' => 'ignored',
        ],
        'sections' => ['compare' => false, 'unknown' => true],
        'order' => ['faq', 'pricing'],
    ], idem())->assertOk()
        ->assertJsonPath('data.texts', [
            'hero.headline' => 'Menus for Addis',
            'problem.items.old.title' => 'Prices change every week',
            'faq.items.app.q' => 'Do guests need an app?',
            'compare.rows.0.1' => 'New price in one tap.',
            'meta_title' => ':name — menus for Addis',
        ])
        ->assertJsonPath('data.sections.compare', false)
        ->assertJsonPath('data.order.0', 'faq')
        ->assertJsonPath('data.order.2', 'problem');
    // The first version's headline moved into the texts.
    expect(PlatformSettings::get('landing_headline'))->toBe('');

    $html = $this->get('/')->assertOk()
        ->assertSee('<title>Menu — menus for Addis</title>', false)
        ->assertSee('Menus for Addis')
        ->assertSee('Prices change every week')
        ->assertSee('Guests scan a QR code and see your menu.') // empty = default
        ->assertDontSee('id="compare"', false)
        ->assertSeeInOrder(['id="faq"', 'id="pricing"', 'id="problem"'], false)
        ->getContent();
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    $faq = collect(json_decode($m[1], true)['@graph'])->firstWhere('@type', 'FAQPage');
    expect($faq['mainEntity'][1]['name'])->toBe('Do guests need an app?');
});

it('refuses texts that are too long and unknown sections in the order', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('landing'), ['texts' => ['hero.headline' => str_repeat('a', 161)]], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('texts.hero.headline');
    $this->actingAs($admin)->putJson(p('landing'), ['texts' => ['faq.items.free.a' => str_repeat('a', 401)]], idem())
        ->assertUnprocessable();
    $this->actingAs($admin)->putJson(p('landing'), ['order' => ['speed']], idem())->assertUnprocessable();
    $this->actingAs($admin)->putJson(p('landing'), ['order' => ['faq', 'faq']], idem())->assertUnprocessable();
    expect(LandingTexts::overrides())->toBe([]);
});

it('keeps an old headline from the first version until the texts are saved', function () {
    PlatformSettings::save(['landing_headline' => 'QR menus for Addis']);
    $this->get('/')->assertSee('QR menus for Addis');
    expect(LandingTexts::overrides())->toBe(['hero.headline' => 'QR menus for Addis']);
});

it('replaces a phone screenshot or the link preview, and goes back to the built-in one', function () {
    Storage::fake('public');
    $admin = superAdmin();

    $this->actingAs($admin)->post(p('landing/images/hero'), ['image' => UploadedFile::fake()->image('shot.png', 750, 1500)], [...idem(), 'Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.images.hero.custom', true);
    $folder = PlatformSettings::get('landing_images')['hero']['folder'];
    Storage::disk('public')->assertExists(["{$folder}/300.webp", "{$folder}/450.webp", "{$folder}/600.webp"]);
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get("{$folder}/600.webp"));
    expect([$w, $h])->toBe([600, 1216]);

    $this->get('/')->assertSee(Storage::disk('public')->url("{$folder}/600.webp"), false)
        ->assertSee(Storage::disk('public')->url("{$folder}/300.webp").' 300w', false)
        // The dark one is still the built-in picture.
        ->assertSee(asset('landing/hero-dark-300.avif').' 300w', false);

    $this->actingAs($admin)->post(p('landing/images/og'), ['image' => UploadedFile::fake()->image('og.jpg', 1200, 630)], [...idem(), 'Accept' => 'application/json'])->assertOk();
    $og = PlatformSettings::get('landing_images')['og']['file'];
    $this->get('/')->assertSee('property="og:image" content="'.Storage::disk('public')->url($og).'"', false);

    // Too small, or not a picture slot.
    $this->actingAs($admin)->post(p('landing/images/demo'), ['image' => UploadedFile::fake()->image('tiny.png', 100, 100)], [...idem(), 'Accept' => 'application/json'])->assertUnprocessable();
    $this->actingAs($admin)->post(p('landing/images/logo'), ['image' => UploadedFile::fake()->image('x.png', 800, 1600)], [...idem(), 'Accept' => 'application/json'])->assertNotFound();

    $this->actingAs($admin)->deleteJson(p('landing/images/hero'), [], idem())->assertOk()->assertJsonPath('data.images.hero.custom', false);
    Storage::disk('public')->assertMissing("{$folder}/600.webp");
    $this->get('/')->assertSee(asset('landing/hero-600.webp'), false);
});

it('counts clicks on the main buttons: no cookie, no address, only a number per button and day', function () {
    $this->post('/api/v1/landing/click', ['k' => 'signup'], ['Referer' => config('app.url').'/', 'Origin' => config('app.url')])
        ->assertNoContent()->assertCookieMissing('XSRF-TOKEN')->assertCookieMissing(config('session.cookie'));
    $this->post('/api/v1/landing/click', ['k' => 'signup'])->assertNoContent();
    $this->post('/api/v1/landing/click', ['k' => 'demo'])->assertNoContent();
    $this->post('/api/v1/landing/click', ['k' => '<script>'])->assertNoContent();

    expect(DB::table('landing_clicks')->orderBy('button')->get(['button', 'count'])->map(fn ($r) => [$r->button, $r->count])->all())
        ->toBe([['demo', 1], ['signup', 2]])
        ->and(Schema::getColumnListing('landing_clicks'))->toBe(['id', 'day', 'button', 'count']);

    $this->actingAs(superAdmin())->getJson(p('landing'))
        ->assertJsonPath('data.clicks.totals', ['signup' => 2, 'google' => 0, 'demo' => 1])
        ->assertJsonPath('data.clicks.days.29.signup', 2)
        ->assertJsonPath('data.clicks.all_time.signup', 2);

    // The page marks its main buttons and sends only the button's name.
    $this->get('/')->assertSee('data-track="signup"', false)->assertSee("navigator.sendBeacon('/api/v1/landing/click'", false);
});
