<?php

use App\Console\Commands\Landing2Amharic;
use App\Http\Controllers\Platform\FooterController;
use App\Models\LandingRestaurant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Landing\Landing2;
use App\Support\Landing\Landing2Texts;
use App\Support\PlatformSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
 * Landing 2 upgrade (owner, 2026-11-22): English + Amharic, an ON/OFF switch and editable
 * texts for every part, and 13 additions (style switcher, price animation, languages, real
 * restaurants, payment methods, Monthly/Yearly, light bands, hero photo, demo video, pattern,
 * QR poster, premium touches, real numbers). No made-up reviews, logos or numbers.
 */

beforeEach(fn () => Landing2Texts::flush());

/** Visible text and screen-reader text of a page, without scripts, styles, drawings and English-marked parts. */
function landing2Words(string $html): array
{
    $html = (string) preg_replace(['#<head>.*?</head>#s', '#<(script|style|noscript)\b.*?</\1>#s', '#<svg\b.*?</svg>#s'], '', $html);
    preg_match_all('/\s(?:alt|aria-label|title|placeholder|data-alt|data-empty|data-no-video)="([^"]*)"/', $html, $attrs);
    $html = (string) preg_replace('#<(\w+)[^>]*\blang="en"[^>]*>.*?</\1>#s', '', $html);
    $text = html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html).' '.implode(' ', $attrs[1]), ENT_QUOTES | ENT_HTML5);
    $text = (string) preg_replace('#https?://\S+|\S+@\S+#', '', $text);
    preg_match_all("/[A-Za-z][A-Za-z&'’]+/", $text, $m);

    return array_values(array_unique($m[0]));
}

// ---- 3. Languages -------------------------------------------------------------------------

it('has an English and an Amharic page, each with canonical and hreflang', function () {
    $en = $this->get('/landing2')->assertOk()->getContent();
    $am = $this->get('/am/landing2')->assertOk()->getContent();

    expect($en)->toContain('<html lang="en" dir="ltr">')
        ->toContain('<link rel="canonical" href="'.url('/landing2').'">')
        ->toContain('<link rel="alternate" hreflang="am" href="'.url('/am/landing2').'">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.url('/landing2').'">')
        ->toContain('Menus guests love to scan.');
    expect($am)->toContain('<html lang="am" dir="ltr">')
        ->toContain('<link rel="canonical" href="'.url('/am/landing2').'">')
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/landing2').'">')
        ->toContain('<meta property="og:locale" content="am_ET">')
        ->toContain('እንግዶች መቃኘት የሚወዱት ሜኑ።')
        // The Amharic font: preloaded, only the Ethiopic letters, swap.
        ->toContain('<link rel="preload" as="font" type="font/woff2" href="'.asset('landing/fonts/noto-sans-ethiopic-400.woff2').'" crossorigin>')
        ->toContain('font-display:swap;unicode-range:U+1200-139F');
    // No font on the English page; both previews are noindex and the language switch has no script of its own.
    expect($en)->not->toContain('noto-sans-ethiopic')->toContain('content="noindex"');
    expect($am)->toContain('content="noindex"');
    // The switch (header and footer) points to the other language and keeps the section (data-lang).
    expect($en)->toContain('class="lang" href="'.url('/am/landing2').'" lang="am" hreflang="am" data-lang')
        ->toContain('<nav class="sf-lang"');
    expect($am)->toContain('class="lang" href="'.url('/landing2').'" lang="en" hreflang="en" data-lang');
    expect(File::size(public_path('landing/fonts/noto-sans-ethiopic-400.woff2')))->toBeLessThan(20 * 1024);
});

it('uses "/" and "/am" once Landing 2 is the home page, and sends "/am" home while it is not', function () {
    $this->get('/am')->assertRedirect('/');
    PlatformSettings::save(['home_design' => 'landing2']);

    $home = $this->get('/')->assertOk()->getContent();
    $am = $this->get('/am')->assertOk()->getContent();
    expect($home)->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('<link rel="alternate" hreflang="am" href="'.url('/am').'">')
        ->not->toContain('name="robots"');
    expect($am)->toContain('<html lang="am"')
        ->toContain('<link rel="canonical" href="'.url('/am').'">')
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/').'">')
        ->toContain('application/ld+json')
        ->not->toContain('name="robots"');
    // A restaurant that had the address "am" before keeps its menu; new ones can't take it.
    expect(config('menu.reserved_slugs'))->toContain('am');
});

it('leaves no English on the Amharic page (brand and technical words aside)', function () {
    Tenant::factory()->create(['slug' => 'demo']);
    $words = landing2Words($this->get('/am/landing2')->getContent());
    $allowed = ['Menu', 'QR', 'PDF', 'PNG', 'Br', 'WhatsApp'];

    expect(array_values(array_diff($words, $allowed)))->toBe([]);
    // And the check itself works: the English page is full of English.
    expect(count(landing2Words($this->get('/landing2')->getContent())))->toBeGreaterThan(40);
});

it('has every English text in Amharic, with the same placeholders and count forms', function () {
    $en = Arr::dot((array) trans('landing2', [], 'en'));
    $am = Arr::dot((array) trans('landing2', [], 'am'));
    expect(array_keys($am))->toEqual(array_keys($en));
    foreach (Landing2Amharic::rows() as $key => $row) {
        expect(Landing2Amharic::check($key, $row['en'], $row['am'], $row['max']))->toBe([], $key);
    }
});

it('shows Amharic plan lines, prices in Br and Amharic plan names', function () {
    $am = $this->get('/am/landing2')->getContent();
    expect($am)->toContain('Br 490 <small>/ በወር</small>')
        ->toContain('<h3>ፕላስ</h3>')
        ->toContain('ያልተገደቡ ምግቦች፣ ሜኑዎችና QR ኮዶች');
    // A plan with its own Amharic name keeps it.
    Plan::where('slug', 'plus')->firstOrFail()->forceFill(['name' => ['en' => 'Plus', 'am' => 'ፕላስ ፕላን']])->save();
    expect($this->get('/am/landing2')->getContent())->toContain('<h3>ፕላስ ፕላን</h3>');
});

// ---- Every part: ON/OFF and editable texts -------------------------------------------------

it('hides any part with its switch', function (string $section, string $marker) {
    Tenant::factory()->create(['slug' => 'demo']);
    expect($this->get('/landing2')->getContent())->toContain($marker);
    PlatformSettings::save(['landing2_sections' => [$section => false]]);
    expect($this->get('/landing2')->getContent())->not->toContain($marker);
    expect($this->get('/am/landing2')->getContent())->not->toContain($marker);
})->with([
    'switcher' => ['switcher', 'id="switcher"'],
    'hero photo' => ['hero_photo', 'data:image/webp;base64'],
    'pattern' => ['pattern', '.final::before'],
    'problem' => ['problem', 'id="problem"'],
    'how' => ['how', 'id="how"'],
    'price demo' => ['price_demo', 'id="pd"'],
    'styles' => ['styles', 'id="style-row"'],
    'compare' => ['compare', 'id="compare"'],
    'qr' => ['qr', 'id="qr"'],
    'poster' => ['poster', 'id="poster"'],
    'features' => ['features', 'id="features"'],
    'pricing' => ['pricing', 'id="pricing"'],
    'billing' => ['billing', 'id="bill-y"'],
    'payments' => ['payments', 'class="pay"'],
    'faq' => ['faq', 'id="faq"'],
    'bands' => ['bands', '.band{background:var(--band)'],
    'motion' => ['motion', 'animation-timeline:view()'],
    'languages' => ['languages', 'class="sf-lang"'],
]);

it('hides the final band with its switch and keeps it out of reading order', function () {
    PlatformSettings::save(['landing2_sections' => ['final' => false]]);
    expect($this->get('/landing2')->getContent())->toContain('<section id="end" aria-labelledby="final-t"  hidden >');
});

it('lets the Super Admin change any text in English and Amharic', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('landing2'), ['texts' => [
        'en' => ['hero.title' => 'Your menu, on every phone.', 'price_demo.toast' => 'Done ✓'],
        'am' => ['hero.title' => 'ሜኑዎ በሁሉም ስልክ።', 'faq.items.free.q' => 'ነፃ ነው?'],
    ]], idem())->assertOk();
    expect(Landing2Texts::overrides('en'))->toBe(['hero.title' => 'Your menu, on every phone.', 'price_demo.toast' => 'Done ✓'])
        ->and(Landing2Texts::overrides('am'))->toBe(['hero.title' => 'ሜኑዎ በሁሉም ስልክ።', 'faq.items.free.q' => 'ነፃ ነው?']);

    expect($this->get('/landing2')->getContent())->toContain('Your menu, on every phone.')->toContain('Done ✓');
    expect($this->get('/am/landing2')->getContent())->toContain('ሜኑዎ በሁሉም ስልክ።')->toContain('ነፃ ነው?')->not->toContain('Your menu, on every phone.');
    // Too long, unknown or equal to the default: refused or not stored.
    $this->actingAs($admin)->putJson(p('landing2'), ['texts' => ['en' => ['hero.kicker' => str_repeat('x', 161)]]], idem())->assertInvalid('texts.en.hero.kicker');
    $this->actingAs($admin)->putJson(p('landing2'), ['texts' => ['am' => ['nope' => 'x', 'hero.kicker' => trans('landing2.hero.kicker', [], 'am')]]], idem())->assertOk()
        ->assertJsonPath('data.texts.am', []);
    // English stays as it was when only Amharic is saved.
    expect(Landing2Texts::overrides('en'))->toHaveKey('hero.title');
});

it('keeps Landing 1 edits of the shared English texts until Landing 2 has its own', function () {
    PlatformSettings::save(['landing_texts' => ['problem.title' => 'Paper costs you', 'faq.items.app.q' => 'An app? No.', 'hero.title' => 'Landing 1 only']]);
    $html = $this->get('/landing2')->getContent();
    expect($html)->toContain('Paper costs you')->toContain('An app? No.')->not->toContain('Landing 1 only');
    PlatformSettings::save(['landing2_texts' => ['en' => ['problem.title' => 'Landing 2 own'], 'am' => []]]);
    expect($this->get('/landing2')->getContent())->toContain('Landing 2 own')->not->toContain('Paper costs you');
    // Amharic never shows Landing 1's English edits.
    expect($this->get('/am/landing2')->getContent())->not->toContain('An app? No.');
});

it('allows the Landing 2 editor only to Super Admins', function () {
    $this->getJson(p('landing2'))->assertUnauthorized();
    [$owner] = member();
    $this->actingAs($owner)->getJson(p('landing2'))->assertNotFound();
    $this->actingAs(superAdmin())->getJson(p('landing2'))->assertOk()
        ->assertJsonPath('data.urls.am', url('/am/landing2'))
        ->assertJsonCount(count(Landing2::SECTIONS), 'data.sections');
});

// ---- 1. Style switcher, loading ----------------------------------------------------------

it('switches the hero phone between styles, loading a screen only when tapped', function () {
    Tenant::factory()->create(['slug' => 'demo']);
    $html = $this->get('/landing2')->getContent();
    // A real radio group: every style with a screen, keyboard and screen reader friendly.
    expect($html)->toContain('role="radiogroup" aria-labelledby="sw-l"')
        ->and(substr_count($html, 'name="sw"'))->toBe(20);
    // Every screen is at most 20 KB, 1x and 2x.
    foreach (glob(public_path('landing/switch/*.webp')) as $file) {
        expect(filesize($file))->toBeLessThanOrEqual(20 * 1024, basename($file));
    }
    // Nothing loads on open: the screens are in data attributes, the two layers have no picture.
    expect($html)->toContain('data-srcset="'.asset('landing/switch/editorial-1x.webp').' 1x, '.asset('landing/switch/editorial-2x.webp').' 2x"')
        ->not->toMatch('#\ssrc="'.preg_quote(asset('landing/switch/'), '#').'#')
        ->toContain('<img class="swi" alt="" width="220" height="446" decoding="async" hidden>');
    // The style pictures below load when scrolled near; without script they are in <noscript>.
    expect((string) preg_replace('#<noscript>.*?</noscript>#s', '', $html))->not->toMatch('#<img src="[^"]*landing/styles/#');
    expect($html)->toContain('<noscript><img src="'.asset('landing/styles/juice-180.webp').'"');
    // The hero picture is asked for at the size it is shown (one picture, not two).
    expect($html)->toContain('imagesizes="(min-width: 56rem) 260px, 220px"')->toContain('sizes="(min-width: 56rem) 260px, 220px" width="300" height="608"');
});

it('starts the switcher on the demo café\'s style and links to the chosen style\'s demo', function () {
    $demo = Tenant::factory()->create(['slug' => 'demo']);
    DB::table('tenant_appearance')->where('tenant_id', $demo->id)->delete();
    DB::table('tenant_appearance')->insert(['tenant_id' => $demo->id, 'style' => 'grid', 'created_at' => now(), 'updated_at' => now()]);
    Tenant::factory()->create(['slug' => 'demo-fine']);
    $html = $this->get('/landing2')->getContent();
    expect($html)->toMatch('#<input type="radio" name="sw" value="grid" data-src="" data-srcset="" data-href="'.preg_quote(url('/demo'), '#').'"[^>]*checked>#')
        ->toContain('value="editorial" data-src="'.asset('landing/switch/editorial-1x.webp').'"')
        ->toContain('data-href="'.url('/demo-fine').'"')
        ->toContain('id="sw-open" href="'.url('/demo').'"');
});

// ---- 2. "Change a price" -----------------------------------------------------------------

it('shows the price change as a still final state with reduced motion', function () {
    $html = $this->get('/landing2')->getContent();
    expect($html)->toContain('role="img" aria-label="Example: the price of a dish changes from Br 70 to Br 80')
        ->toContain('<span class="pd-o">Br 70</span><span class="pd-n">Br 80</span>')
        ->toContain('Saved ✓ · every table sees it')
        // Animated only with motion allowed, paused until on screen; otherwise the new price.
        ->toContain('.pd-o{opacity:0}')
        ->toContain('.js .pd-o{animation:pdo 8s infinite both paused}')
        ->toContain('.js .pd.play .pd-o');
});

// ---- 4. Real restaurants -----------------------------------------------------------------

it('shows restaurants only with permission, and only once three have it', function () {
    $make = fn (string $name, bool $permission = true, bool $active = true) => LandingRestaurant::create([
        'name' => $name, 'city' => 'Addis Ababa', 'quote_en' => "Quote of {$name}", 'quote_am' => "የ{$name} ቃል", 'permission' => $permission, 'is_active' => $active,
    ]);
    $make('Alpha');
    $make('Beta');
    $make('Gamma', permission: false);
    $make('Delta', active: false);
    expect($this->get('/landing2')->getContent())->not->toContain('id="restaurants"');

    $make('Epsilon');
    $en = $this->get('/landing2')->getContent();
    $am = $this->get('/am/landing2')->getContent();
    expect($en)->toContain('id="restaurants"')->toContain('“Quote of Alpha”')->toContain('“Quote of Epsilon”')
        ->not->toContain('Gamma')->not->toContain('Delta');
    expect($am)->toContain('“የAlpha ቃል”');
    // Switched off: hidden even with enough restaurants.
    PlatformSettings::save(['landing2_sections' => ['restaurants' => false]]);
    expect($this->get('/landing2')->getContent())->not->toContain('id="restaurants"');
});

it('lets the Super Admin manage restaurants, with a small logo', function () {
    Storage::fake('public');
    $admin = superAdmin();
    $this->actingAs($admin)->postJson(p('landing2/restaurants'), ['name' => 'Habesha House', 'city' => 'Adama', 'quote_en' => 'Guests love it.', 'quote_am' => '', 'permission' => true, 'is_active' => true], idem())
        ->assertOk()->assertJsonPath('data.restaurants.0.name', 'Habesha House')->assertJsonPath('data.restaurants.0.quote_am', null);
    $r = LandingRestaurant::firstOrFail();
    $this->actingAs($admin)->post(p("landing2/restaurants/{$r->id}/logo"), ['image' => UploadedFile::fake()->image('logo.png', 300, 200)], idem())->assertOk();
    $r->refresh();
    Storage::disk('public')->assertExists(["{$r->logo}/64.webp", "{$r->logo}/128.webp"]);
    expect(getimagesize(Storage::disk('public')->path("{$r->logo}/128.webp"))[0])->toBe(128);
    $this->actingAs($admin)->putJson(p("landing2/restaurants/{$r->id}"), ['name' => 'Habesha House', 'permission' => false, 'is_active' => true], idem())->assertOk()
        ->assertJsonPath('data.restaurants.0.permission', false);
    $this->actingAs($admin)->postJson(p('landing2/restaurants'), ['name' => '', 'permission' => true, 'is_active' => true], idem())->assertInvalid('name');
    $folder = $r->logo;
    $this->actingAs($admin)->deleteJson(p("landing2/restaurants/{$r->id}"), [], idem())->assertOk()->assertJsonPath('data.restaurants', []);
    Storage::disk('public')->assertMissing("{$folder}/64.webp");
});

it('seeds no restaurants: none exist unless the Super Admin adds them', function () {
    $this->seed(DatabaseSeeder::class);
    expect(LandingRestaurant::count())->toBe(0);
});

// ---- 5. Payment methods ------------------------------------------------------------------

it('lists the payment methods under the prices, editable, in both languages', function () {
    $en = $this->get('/landing2')->getContent();
    expect($en)->toContain('Pay with:')->toContain('telebirr</li>')->toContain('CBE Birr</li>')->toContain('Bank transfer</li>');
    expect($this->get('/am/landing2')->getContent())->toContain('ቴሌብር</li>')->toContain('የባንክ ዝውውር</li>');

    $this->actingAs(superAdmin())->putJson(p('landing2'), ['payments' => [
        ['label_en' => 'M-Pesa', 'label_am' => 'ኤም-ፔሳ', 'icon' => 'phone', 'on' => true],
        ['label_en' => 'Cash', 'label_am' => '', 'icon' => 'cash', 'on' => false],
    ]], idem())->assertOk();
    $en = $this->get('/landing2')->getContent();
    expect($en)->toContain('M-Pesa</li>')->not->toContain('telebirr</li>')->not->toContain('Cash</li>');
    // No method shown: no "Pay with" line at all.
    PlatformSettings::save(['landing2_payments' => []]);
    expect($this->get('/landing2')->getContent())->not->toContain('class="pay"');
    $this->actingAs(superAdmin())->putJson(p('landing2'), ['payments' => [['label_en' => 'X', 'icon' => 'logo']]], idem())->assertInvalid('payments.0.icon');
});

// ---- 6. Monthly / Yearly -----------------------------------------------------------------

it('switches Monthly and Yearly with the live prices of the plans', function () {
    $plus = Plan::where('slug', 'plus')->firstOrFail();
    $plus->forceFill(['price_minor' => 50_000, 'price_year_minor' => 500_000, 'interval' => 'month'])->save();
    $html = $this->get('/landing2')->getContent();
    // Two real radio buttons; both prices in the page, CSS shows one.
    expect($html)->toContain('<input class="bi" type="radio" name="bill" id="bill-m" checked>')
        ->toContain('<label for="bill-y">Yearly <span class="save">2 months free</span></label>')
        ->toContain('<p class="price pm">Br 500 <small>/ month</small></p>')
        ->toContain('<p class="price py">Br 5,000 <small>/ year</small></p>');
    // No yearly prices: no switch, the monthly price only.
    Plan::query()->update(['price_year_minor' => null]);
    $html = $this->get('/landing2')->getContent();
    expect($html)->not->toContain('id="bill-y"')->not->toContain('class="price py"')->toContain('Br 500 <small>/ month</small>');
});

// ---- 9. Video ----------------------------------------------------------------------------

it('shows the video only as a picture with ▶ until tapped, and hides it while there is none', function () {
    PlatformSettings::save(['landing2_video' => null]);
    $dir = public_path(Landing2::VIDEO_DIR);
    $had = is_file("{$dir}/demo.mp4");
    if (! $had) {
        expect($this->get('/landing2')->getContent())->not->toContain('id="video"');
    }
    Storage::fake('public');
    $folder = 'landing/video-test';
    Storage::disk('public')->put("{$folder}/video.mp4", 'x');
    Storage::disk('public')->put("{$folder}/poster.webp", 'x');
    Storage::disk('public')->put("{$folder}/en.vtt", "WEBVTT\n");
    PlatformSettings::save(['landing2_video' => ['folder' => $folder]]);
    $html = $this->get('/landing2')->getContent();
    expect($html)->toContain('id="video"')
        ->toContain('class="vp" id="vp" href="'.Storage::disk('public')->url("{$folder}/video.mp4").'"')
        ->toContain('data-en="'.Storage::disk('public')->url("{$folder}/en.vtt").'" data-am=""')
        ->toContain('<img data-src="'.Storage::disk('public')->url("{$folder}/poster.webp").'" alt="" width="960" height="540"')
        // No video element, no autoplay, no preload before the tap.
        ->not->toContain('<video')->not->toContain('autoplay')->not->toContain('preload="auto"');
});

it('takes a new video from the Super Admin: an MP4 under 2 MB, a first picture and WebVTT captions', function () {
    Storage::fake('public');
    $admin = superAdmin();
    $mp4 = fn (int $kb = 10) => UploadedFile::fake()->createWithContent('demo.mp4', "\0\0\0\x18ftypmp42".str_repeat("\0", $kb * 1024))->mimeType('video/mp4');
    $vtt = fn (string $text) => UploadedFile::fake()->createWithContent('c.vtt', $text);
    $poster = fn () => UploadedFile::fake()->image('poster.png', 1280, 720);

    $this->actingAs($admin)->post(p('landing2/video'), ['video' => $mp4(), 'poster' => $poster(), 'captions_en' => $vtt("WEBVTT\n\n00:00.000 --> 00:02.000\nSign up"), 'captions_am' => $vtt("WEBVTT\n\n00:00.000 --> 00:02.000\nይመዝገቡ")], idem())
        ->assertOk()->assertJsonPath('data.video.custom', true);
    $folder = PlatformSettings::get('landing2_video')['folder'];
    Storage::disk('public')->assertExists(["{$folder}/video.mp4", "{$folder}/poster.webp", "{$folder}/en.vtt", "{$folder}/am.vtt"]);
    expect(getimagesize(Storage::disk('public')->path("{$folder}/poster.webp")))->toMatchArray([0 => 960, 1 => 540]);

    // Too big, not really an MP4, captions that are not WebVTT: refused.
    $this->actingAs($admin)->post(p('landing2/video'), ['video' => $mp4(2100), 'poster' => $poster()], idem())->assertInvalid('video');
    $fake = UploadedFile::fake()->createWithContent('demo.mp4', str_repeat('a', 2000))->mimeType('video/mp4');
    $this->actingAs($admin)->post(p('landing2/video'), ['video' => $fake, 'poster' => $poster()], idem())->assertInvalid('video');
    $this->actingAs($admin)->post(p('landing2/video'), ['video' => $mp4(), 'poster' => $poster(), 'captions_am' => $vtt('hello')], idem())->assertInvalid('captions_am');
    // The first upload is still the one shown.
    expect(PlatformSettings::get('landing2_video')['folder'])->toBe($folder);

    $this->actingAs($admin)->deleteJson(p('landing2/video'), [], idem())->assertOk();
    Storage::disk('public')->assertMissing("{$folder}/video.mp4");
    expect(PlatformSettings::get('landing2_video'))->toBeNull();
});

// ---- 11. Poster ----------------------------------------------------------------------------

it('previews a QR poster in the page and asks for a free sign-up to download it', function () {
    $html = $this->get('/landing2')->getContent();
    expect($html)->toContain('id="poster"')
        ->toContain('<input type="text" id="pf-name" maxlength="40"')
        ->toContain('data-en="Scan for our menu" data-am="ሜኑያችንን ለማየት ይቃኙ"')
        ->toContain('aria-label="Example QR code"')
        ->toContain('<a class="btn btn-brand" href="/signup">Sign up free to download (PDF and PNG)</a>');
    // Made in the browser: the form sends nothing anywhere.
    expect($html)->toContain('<form class="pf" id="pf" action="#poster">')->not->toContain('name="pf-name"');
    // Sign-up closed: the main "contact us" button instead.
    PlatformSettings::save(['signup_open' => false, 'upgrade_whatsapp' => '+251911000000']);
    expect($this->get('/landing2')->getContent())->not->toContain('Sign up free to download')->toContain('Contact us on WhatsApp');
});

// ---- 13. Real numbers ----------------------------------------------------------------------

it('counts our own numbers daily without the demos and rounds them down', function () {
    Tenant::factory()->count(3)->create();
    Tenant::factory()->create(['slug' => 'demo']);
    Tenant::factory()->create(['suspended_at' => now()]);
    $this->artisan('landing2:numbers')->assertSuccessful();
    $counted = PlatformSettings::get('landing2_numbers_counted');
    expect($counted['restaurants'])->toBeGreaterThanOrEqual(3);

    expect(Landing2::roundDown(7))->toBe('7')
        ->and(Landing2::roundDown(43))->toBe('40+')
        ->and(Landing2::roundDown(1043))->toBe('1,000+')
        ->and(Landing2::roundDown(1543))->toBe('1,500+')
        ->and(Landing2::roundDown(15400))->toBe('15,000+')
        ->and(Landing2::roundDown(999))->toBe('900+');
});

it('shows a number only when on and at least its minimum, with the source of outside numbers', function () {
    PlatformSettings::save(['landing2_numbers_counted' => ['at' => now()->toIso8601String(), 'restaurants' => 1043, 'dishes' => 40, 'views' => 5000]]);
    $html = $this->get('/landing2')->getContent();
    expect($html)->toContain('<b>1,000+</b><span>restaurants use it</span>')
        ->toContain('<b>5,000+</b>')
        ->not->toContain('dishes on menus'); // below its minimum (1,000)

    $this->actingAs(superAdmin())->putJson(p('landing2'), ['numbers' => [
        'own' => ['restaurants' => ['on' => false, 'min' => 100]],
        'outside' => [['value' => 120, 'label_en' => 'reviews on Google Play', 'label_am' => 'የGoogle Play ግምገማዎች', 'source' => 'Google Play', 'date' => now()->subDays(40)->toDateString(), 'on' => true, 'min' => 50]],
    ]], idem())->assertOk()->assertJsonPath('data.stale', 1);
    $html = $this->get('/landing2')->getContent();
    expect($html)->not->toContain('restaurants use it')
        ->toContain('<b>100+</b><span>reviews on Google Play</span><small>Source: Google Play, ');
    expect($this->get('/am/landing2')->getContent())->toContain('የGoogle Play ግምገማዎች</span><small>ምንጭ፦ Google Play፣ ');
    // A number from the future is refused.
    $this->actingAs(superAdmin())->putJson(p('landing2'), ['numbers' => ['outside' => [['value' => 1, 'label_en' => 'x', 'source' => 'y', 'date' => now()->addDay()->toDateString()]]]], idem())
        ->assertInvalid('numbers.outside.0.date');
    // Nothing to show: no strip.
    PlatformSettings::save(['landing2_numbers' => ['own' => ['restaurants' => ['on' => false], 'views' => ['on' => false]], 'outside' => []]]);
    expect($this->get('/landing2')->getContent())->not->toContain('id="numbers"');
});

// ---- Footer in Amharic ---------------------------------------------------------------------

it('shows the footer in Amharic, also the texts the Super Admin typed', function () {
    $admin = superAdmin();
    $draft = $this->actingAs($admin)->getJson(p('footer'))->json('data.draft');
    $draft['columns'][0]['title'] = 'Our product';
    $draft['trust']['on'] = true;
    $draft['trust']['text'] = 'Free forever';
    $this->actingAs($admin)->putJson(p('footer'), $draft, idem())->assertOk();
    $this->actingAs($admin)->postJson(p('footer/publish'), [], idem())->assertOk();

    $am = $this->get('/am/landing2')->getContent();
    expect($am)->toContain('Our product')->toContain('ለምግብ ቤቶች'); // built-in title translated, own one not yet
    $this->actingAs($admin)->putJson(p('landing2'), ['footer_am' => ['Our product' => 'ምርታችን', 'Free forever' => 'ለዘላለም ነፃ', 'unknown' => 'x']], idem())->assertOk()
        ->assertJsonPath('data.footer.am', ['Our product' => 'ምርታችን', 'Free forever' => 'ለዘላለም ነፃ']);
    $am = $this->get('/am/landing2')->getContent();
    expect($am)->toContain('ምርታችን')->toContain('ለዘላለም ነፃ')->not->toContain('Our product');
    // The English page and Landing 1 keep the English footer.
    expect($this->get('/landing2')->getContent())->toContain('Our product');
})->skip(fn () => ! class_exists(FooterController::class), 'needs the footer editor');

// ---- Amharic review CSV ----------------------------------------------------------------------

it('exports the Amharic texts for review and imports only a file where every row is right', function () {
    $file = tempnam(sys_get_temp_dir(), 'am').'.csv';
    $this->artisan('landing2:amharic', ['--export' => $file])->assertSuccessful();
    $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_filter(explode("\n", trim(substr((string) file_get_contents($file), 3)))));
    expect($rows[0])->toBe(Landing2Amharic::HEADER)->and(count($rows) - 1)->toBe(count(Landing2Amharic::rows()));

    // A dry run checks every row and saves nothing.
    $this->artisan('landing2:amharic', ['--import' => $file, '--dry-run' => true])->assertSuccessful();
    expect(PlatformSettings::get('landing2_texts')['am'])->toBe([]);

    // One wrong row (a placeholder lost): nothing is saved, even the right rows.
    $csv = (string) file_get_contents($file);
    $bad = str_replace(['"ቀላል ዋጋዎች"', '":name፦ እንግዶች መቃኘት የሚወዱት QR ሜኑ"'], ['"ቀላል የሆኑ ዋጋዎች"', '"እንግዶች መቃኘት የሚወዱት QR ሜኑ"'], $csv);
    file_put_contents($file, $bad);
    $this->artisan('landing2:amharic', ['--import' => $file])->assertFailed();
    expect(PlatformSettings::get('landing2_texts')['am'])->toBe([]);

    // Every row right: saved like the Super Admin's own changes, and shown.
    file_put_contents($file, str_replace('"ቀላል ዋጋዎች"', '"ቀላል የሆኑ ዋጋዎች"', $csv));
    $this->artisan('landing2:amharic', ['--import' => $file])->assertSuccessful();
    expect(PlatformSettings::get('landing2_texts')['am'])->toBe(['pricing.title' => 'ቀላል የሆኑ ዋጋዎች']);
    expect($this->get('/am/landing2')->getContent())->toContain('ቀላል የሆኑ ዋጋዎች');
    @unlink($file);
});

it('checks every Amharic row: empty, too long, HTML, placeholders, count forms, no Amharic letters', function () {
    expect(Landing2Amharic::check('landing2.hero.title', 'Hello', '', 160))->toBe(['the Amharic text is empty.'])
        ->and(Landing2Amharic::check('landing2.hero.title', 'Hello', str_repeat('ሀ', 161), 160))->toHaveCount(1)
        ->and(Landing2Amharic::check('landing2.hero.title', 'Hello', 'ሰላም <b>x</b>', 160))->toContain('HTML is not allowed.')
        ->and(Landing2Amharic::check('landing2.meta_title', ':name: hi', 'ሰላም', 160)[0])->toStartWith('the placeholders must be :name')
        ->and(Landing2Amharic::check('landing2.pricing.months_free', '{1} 1 month|[2,*] :n months', ':n ወር', 160))->toContain('keep the same count forms ({1} … | [2,*] …) as the English.')
        ->and(Landing2Amharic::check('landing2.hero.title', 'Hello', 'Hello', 160))->toBe(['has no Amharic letters.'])
        ->and(Landing2Amharic::check('landing2.lang.en', 'English', 'English', 160))->toBe([]);
});

// ---- No tracking, no cookies -----------------------------------------------------------------

it('sets no cookie and loads no outside script on either language', function () {
    foreach (['/landing2', '/am/landing2'] as $url) {
        $res = $this->get($url)->assertOk();
        $res->assertCookieMissing('laravel_session');
        expect($res->getContent())->not->toMatch('#<script[^>]+src=#')->not->toContain('googletagmanager')->not->toContain('analytics');
    }
});
