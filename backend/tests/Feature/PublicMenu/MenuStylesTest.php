<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Contrast;
use App\Support\Features;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\PageTheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** A rule that only one style's CSS contains. */
const STYLE_MARKERS = [
    'editorial' => '.lg{width:4.75rem',
    'grid' => 'grid-template-columns:1fr 1fr',
    'bistro' => 'section h2{font-size:1.45rem',
    'minimal' => '.lg{width:3rem',
];

function pageWithStyle(string $style): string
{
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style]));

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('inlines only the CSS of the chosen style', function (string $style) {
    $html = pageWithStyle($style);

    expect($html)->toContain('class="S-'.$style)->toContain(STYLE_MARKERS[$style]);
    foreach (array_diff_key(STYLE_MARKERS, [$style => true]) as $other) {
        expect($html)->not->toContain($other);
    }
})->with(TenantAppearance::STYLES);

it('has a light and a dark palette in every style, and the brand colour stays readable in both', function (string $style, string $brand) {
    $html = pageWithStyle($style);
    expect(substr_count($html, '@media (prefers-color-scheme:dark){:root{--bg:'))->toBe(1);

    $a = new TenantAppearance([...TenantAppearance::DEFAULTS, 'style' => $style, 'primary_color' => $brand]);
    preg_match('/--a:(#[0-9a-f]{6}).*prefers-color-scheme:dark\){:root\{--a:(#[0-9a-f]{6})/', PageTheme::from($a)['css'], $m);
    expect(Contrast::ratio($m[1], PageTheme::STYLES[$style]['light']))->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($m[2], PageTheme::STYLES[$style]['dark']))->toBeGreaterThanOrEqual(4.5);
})->with(TenantAppearance::STYLES)->with(['#b4432a', '#fde047', '#0b1f3a', '#5cc9a7']);

it('keeps PageTheme surfaces in step with the style stylesheets', function (string $style) {
    $css = (string) file_get_contents(resource_path("public-menu/style-{$style}.css"));
    preg_match_all('/--(bg|card):(#[0-9a-f]{3,6})/', $css, $m);
    $light = array_slice($m[2], 0, 2);
    $dark = array_slice($m[2], 2, 2);
    // The surface used for the contrast check is the least contrasting of bg and card.
    $worstLight = collect($light)->sortBy(fn ($c) => Contrast::luminance(strlen($c) === 4 ? '#'.str_repeat($c[1], 2).str_repeat($c[2], 2).str_repeat($c[3], 2) : $c))->first();
    $worstDark = collect($dark)->sortByDesc(fn ($c) => Contrast::luminance($c))->first();
    expect(PageTheme::STYLES[$style]['light'])->toBe($worstLight)->and(PageTheme::STYLES[$style]['dark'])->toBe($worstDark);
})->with(TenantAppearance::STYLES);

it('uses the style heading font unless the restaurant picked a font', function () {
    $css = fn (string $style, string $font) => PageTheme::from(new TenantAppearance([...TenantAppearance::DEFAULTS, 'style' => $style, 'font' => $font]))['css'];

    expect($css('editorial', 'system'))->toMatch('/--fh:"Iowan/')
        ->and($css('grid', 'system'))->toMatch('/--fh:system-ui/')
        ->and($css('bistro', 'rounded'))->toMatch('/--fh:ui-rounded/');
});

it('rebuilds the saved page when the style changes', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    File::deleteDirectory(public_path('menu-cache'));
    config(['menu.static_pages' => true]);

    $this->get('/'.$tenant->slug)->assertOk()->assertSee('S-grid', false);
    expect(MenuPageCache::path($tenant->slug))->toBeFile();

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [
        'primary_color' => '#b4432a', 'font' => 'system', 'style' => 'bistro',
        'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true,
    ], idem())->assertOk()->assertJsonPath('data.style', 'bistro');

    expect(MenuPageCache::path($tenant->slug))->not->toBeFile();
    $this->get('/'.$tenant->slug)->assertOk()->assertSee('S-bistro', false)->assertDontSee('S-grid', false);
    expect((string) file_get_contents(MenuPageCache::path($tenant->slug)))->toContain('S-bistro');

    File::deleteDirectory(public_path('menu-cache'));
});

it('offers only the styles the plan allows, and a restaurant override wins', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $plan = Plan::create(['name' => ['en' => 'Basic'], 'slug' => 'basic', 'limits' => []]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'style_editorial', 'enabled' => false]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();

    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertJsonPath('data.styles', ['grid', 'bistro', 'minimal']);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [
        'primary_color' => '#b4432a', 'font' => 'system', 'style' => 'editorial',
        'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true,
    ], idem())->assertInvalid('style');

    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'style_editorial', 'enabled' => true]);
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertJsonPath('data.styles', ['editorial', 'grid', 'bistro', 'minimal']);
});

it('falls back to an allowed style when the saved one is switched off', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'bistro']));
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'style_bistro', 'enabled' => false]);
    Features::forget();

    $this->get('/'.$tenant->slug)->assertOk()->assertSee('S-grid', false);
    expect(TenantAppearance::effectiveStyle('grid', ['bistro']))->toBe('bistro')
        ->and(TenantAppearance::effectiveStyle('x', []))->toBe('grid');
});

it('defaults new restaurants to the fresh grid style', function () {
    expect(TenantAppearance::DEFAULTS['style'])->toBe('grid');
});

it('without a banner, preloads the first dish photo and loads only it at once', function (string $style, string $sizes) {
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $style) {
        foreach (range(1, 8) as $n) {
            $media = Media::create([
                'base_path' => 'x/'.$n, 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
                'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
            ]);
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$n], 'price_minor' => 1000, 'media_id' => $media->id, 'sort' => $n]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style]);
    });

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();

    // The first dish photo is the largest paint (LCP, owner 2026-11-12): fetched from the <head>, high priority,
    // in the size picked for it; the other seven wait for load (same size or smaller, so never a later LCP).
    expect($html)->toMatch('#<link rel="preload" as="image" fetchpriority="high" type="image/webp" imagesrcset="[^"]*x/1/320.webp 320w[^"]*" imagesizes="'.preg_quote($sizes, '#').'">#')
        ->toMatch('#<source type="image/webp" srcset="[^"]*x/1/320.webp[^"]*" sizes="'.preg_quote($sizes, '#').'">\s*<img src="[^"]*x/1/640.jpg"[^>]*fetchpriority="high"#')
        ->and(substr_count($html, 'fetchpriority="high"'))->toBe(2)
        ->and(substr_count($html, 'loading="lazy"'))->toBe(7);
})->with([
    ['editorial', '(min-width:44rem) 672px, 44vw'], // the 320 px file on a phone (owner, 2026-11-12)
    ['grid', '(min-width:40rem) 220px, 40vw'],
    ['bistro', '104px'],
]);

it('with a banner, loads the banner first and every dish photo after load', function (string $style, int $eager) {
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $style) {
        $photo = fn (string $p) => Media::create([
            'base_path' => $p, 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        foreach (range(1, 8) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$n], 'price_minor' => 1000, 'media_id' => $photo('x/'.$n)->id, 'sort' => $n]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style, 'cover_media_id' => $photo('b/1')->id]);
    });

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();

    expect(substr_count($html, 'fetchpriority="auto"'))->toBe($eager)
        ->and(substr_count($html, 'loading="lazy"'))->toBe(8 - $eager)
        ->and($html)->toMatch('#<link rel="preload" as="image" fetchpriority="high"[^>]*b/1/800.webp#');
})->with([['editorial', 0], ['grid', 0], ['bistro', 0]]);

it('without a banner, the first special is the largest paint: preloaded, at once, every card the same height', function () {
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        $photo = fn (string $p) => Media::create([
            'base_path' => $p, 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish'], 'price_minor' => 1000, 'media_id' => $photo('x/1')->id]);
        Promotion::create(['type' => 'special', 'title' => ['en' => 'Deal 1'], 'media_id' => $photo('s/1')->id, 'sort' => 1]);
        Promotion::create(['type' => 'special', 'title' => ['en' => 'Deal 2'], 'media_id' => $photo('s/2')->id, 'sort' => 2]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'bistro']);
    });

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();

    expect($html)->toMatch('#<link rel="preload" as="image" fetchpriority="high" type="image/webp" imagesrcset="[^"]*s/1/320.webp[^"]*" imagesizes="\(min-width:44rem\) 152px, 34vw">#')
        ->toMatch('#<img src="[^"]*s/1/640.jpg"[^>]*fetchpriority="high"#')
        ->toMatch('#<img data-s="[^"]*s/2/640.jpg"[^>]*loading="lazy"#')
        ->toMatch('#<img data-s="[^"]*x/1/640.jpg"#')
        ->and(substr_count($html, 'fetchpriority="high"'))->toBe(2);
    // Equal cards: a later special photo is never larger than the first, so it can't become the LCP.
    expect(InlineAssets::css('bistro'))->toContain('.sp{min-height:9.5rem;align-items:stretch;column-gap:0}');
});

it('gives photos below the first screen their address only after load, and blurs only the first four', function () {
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        $media = fn (int $n) => Media::create([
            'base_path' => 'x/'.$n, 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1, 'placeholder' => 'data:image/webp;base64,BLUR', 'dominant_color' => '#123456',
            'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        foreach (range(1, 9) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$n], 'price_minor' => 1000, 'media_id' => $media($n)->id, 'sort' => $n]);
        }
        foreach ([1, 2] as $n) {
            Promotion::create(['type' => 'special', 'title' => ['en' => 'Deal '.$n], 'media_id' => $media(10 + $n)->id, 'sort' => $n]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'grid']);
    });

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();

    // grid without a banner: the first special is the largest paint and loads at once; all nine dishes
    // and the second special wait for load.
    expect(substr_count($html, 'img data-s="'))->toBe(10)
        ->and(substr_count($html, 'data-ss="'))->toBe(10)
        ->and(substr_count($html, 'background-image:url(data:image/webp;base64,BLUR)'))->toBe(4)
        ->and(substr_count($html, 'background-color:#123456'))->toBe(11);
    // Specials are compact cards with a small responsive photo.
    expect($html)->toMatch('#<source type="image/webp" srcset="[^"]*x/11/320.webp 320w[^"]*" sizes="\(min-width:44rem\) 152px, 34vw">#')
        ->toMatch('#<source type="image/webp" data-ss="[^"]*x/12/320.webp 320w[^"]*" sizes="\(min-width:44rem\) 152px, 34vw">#');
    // …and the page script swaps the addresses in on load.
    expect(InlineAssets::js())->toContain('data-ss')->toContain('img[data-s]');
});

/** The demo-like page with a banner photo. */
function pageWithBanner(string $style, ?string $bannerText = null): string
{
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, function () use ($style, $bannerText) {
        $cover = Media::create([
            'base_path' => 'b/1', 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
            'placeholder' => 'data:image/webp;base64,AAAA', 'dominant_color' => '#884422',
        ]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style, 'banner_text' => $bannerText, 'cover_media_id' => $cover->id]);
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('preloads the banner in the right sizes and keeps its shape', function (string $style, string $height, string $sizes) {
    $html = pageWithBanner($style);

    // Fetched from the <head>, before the CSS is parsed (LCP).
    expect($html)->toMatch('#<link rel="preload" as="image" fetchpriority="high" type="image/webp" imagesrcset="[^"]*/800.webp 800w, [^"]*/1280.webp 1280w" imagesizes="'.preg_quote($sizes, '#').'">#')
        ->toContain('width="1280" height="'.$height.'" fetchpriority="high"')
        // The space is reserved by the style's aspect ratio and filled by the blurred preview.
        ->toContain('background-image:url(data:image/webp;base64,AAAA);background-color:#884422');
    expect(InlineAssets::css($style))->toMatch('/\.cv\{aspect-ratio:[0-9\/]+[;}]/');
})->with([
    ['editorial', '720', '(min-width:44rem) 704px, 100vw'],
    ['grid', '640', '(min-width:44rem) 680px, calc(100vw - 1.5rem)'],
    ['bistro', '512', '(min-width:44rem) 704px, 100vw'],
    ['minimal', '512', '(min-width:44rem) 704px, 100vw'],
]);

it('puts the logo and name on or below the banner', function () {
    expect(pageWithBanner('grid'))->toContain('class="top hc ov"')            // B: on the banner by default
        ->and(pageWithBanner('editorial'))->toContain('class="top hc"')       // A: below by default
        ->and(pageWithBanner('editorial', 'over'))->toContain('class="top hc ov"')
        ->and(pageWithBanner('grid', 'under'))->toContain('class="top hc"');
});

it('saves where the logo and name go', function () {
    [$owner, $tenant] = member(Role::Owner);
    $values = ['primary_color' => '#b4432a', 'font' => 'system', 'style' => 'grid', 'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true];

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$values, 'banner_text' => 'under'], idem())->assertOk()
        ->assertJsonPath('data.banner_text', 'under')
        ->assertJsonPath('data.banner.grid', ['ratio' => [2, 1], 'text' => 'over']);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$values, 'banner_text' => 'left'], idem())->assertInvalid('banner_text');
});

/** A menu with photos on every dish; $showPhotos is the Appearance switch. */
function menuWithPhotos(string $style, bool $showPhotos): string
{
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $style, $showPhotos) {
        foreach (range(1, 6) as $n) {
            $media = Media::create([
                'base_path' => 'p/'.$n, 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
                'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
                'placeholder' => 'data:image/webp;base64,'.str_repeat('A', 200),
            ]);
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$n], 'price_minor' => 1000, 'media_id' => $media->id, 'sort' => $n]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style, 'show_photos' => $showPhotos]);
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('shows a clean, lighter page when photos are switched off', function (string $style) {
    $with = menuWithPhotos($style, true);
    $without = menuWithPhotos($style, false);

    expect($with)->toContain('class="im"');
    // No photo boxes, no image requests, no social preview image: every dish is a text row.
    expect($without)->not->toContain('class="im"')
        ->not->toContain('<img')
        // The link preview is the platform default, never a dish photo (and the page doesn't load it).
        ->not->toContain('og.jpg')
        ->not->toContain('loading="lazy"')
        ->and(substr_count($without, 'class="it np'))->toBe(7)
        ->and($without)->toContain('class="S-'.$style.' NP"')
        ->and($with)->toContain('class="S-'.$style.'"');
    expect(strlen(gzencode($without, 9)))->toBeLessThan(strlen(gzencode($with, 9)));
})->with(['editorial', 'grid', 'bistro']); // D never shows dish photos (StyleMinimalTest)

it('lays out dishes without photos as text rows in every style', function (string $style) {
    $css = InlineAssets::css($style);
    // Each style has rules for dishes or pages without photos, so no empty photo box is drawn.
    expect($css)->toMatch('/\.(np|NP)\b/');
})->with(TenantAppearance::STYLES);
