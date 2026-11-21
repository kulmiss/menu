<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Contrast;
use App\Support\Features;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuDirection;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\PageTheme;
use App\Support\Security\Csp;
use Illuminate\Support\Facades\DB;

/*
 * Styles H · Arabic, I · Islamic, J · Somali, K · Black & White and
 * L · Colourful (owner, 2026-11-18, QUESTIONS #229): their options (menu
 * direction, Halal badge, Ramadan mode, black-and-white photos, accent), the
 * category colours of L, the Arabic font of H, plans and the Appearance API.
 * Every A–D feature in each of them: StylesEfgTest.
 */

/** A restaurant with a menu in the given names and the given appearance; returns the guest page. */
function hlPage(array $appearance, string $restaurant = 'Cafe Uno', array $categories = ['Lunch' => ['Rice', 'Tea']]): string
{
    $tenant = Tenant::factory()->create(['name' => $restaurant]);
    asTenant($tenant, function () use ($appearance, $categories) {
        $menu = Menu::create(['name' => ['en' => 'Menu'], 'slug' => 'menu']);
        $c = 0;
        foreach ($categories as $category => $items) {
            $cat = Category::create(['menu_id' => $menu->id, 'name' => ['en' => $category], 'sort' => $c++]);
            foreach ($items as $i => $name) {
                Item::create(['category_id' => $cat->id, 'name' => ['en' => $name], 'price_minor' => 1000 + $i, 'sort' => $i]);
            }
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, ...$appearance]);
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('decides the direction from the menu\'s letters, ignoring digits and punctuation', function (string $text, bool $rtl) {
    expect(MenuDirection::isRtl($text))->toBe($rtl);
})->with([
    ['مندي دجاج', true],
    ['Chicken mandi', false],
    ['مندي دجاج · Chicken', true],           // 8 Arabic letters, 7 Latin
    ['Chicken mandi · مندي', false],
    ['123 · 456 !', false],                  // no letters at all
    ['', false],
    ['שקשוקה', true],                        // Hebrew is right-to-left too
    ['ዶሮ ወጥ', false],                       // Amharic is left-to-right
]);

it('shows style H right-to-left when the menu is in Arabic (automatic), and as set otherwise', function () {
    $arabic = ['المندي' => ['مندي دجاج', 'لحم مندي'], 'مقبلات' => ['حمص', 'فتوش']];

    expect(hlPage(['style' => 'arabic'], 'دار المندي', $arabic))->toContain('<html lang="en" dir="rtl">');
    expect(hlPage(['style' => 'arabic']))->toContain('<html lang="en" dir="ltr">');
    expect(hlPage(['style' => 'arabic', 'menu_direction' => 'rtl']))->toContain('<html lang="en" dir="rtl">');
    expect(hlPage(['style' => 'arabic', 'menu_direction' => 'ltr'], 'دار المندي', $arabic))->toContain('<html lang="en" dir="ltr">');
});

it('keeps every other style left-to-right, whatever the saved direction', function (string $style) {
    $html = hlPage(['style' => $style, 'menu_direction' => 'rtl'], 'دار المندي', ['المندي' => ['مندي دجاج']]);
    expect($html)->toContain('<html lang="en" dir="ltr">');
})->with(array_values(array_diff(TenantAppearance::STYLES, TenantAppearance::DIRECTION_STYLES)));

it('offers the menu direction in M–Q too (Arabic menus by the sea, in a coffee house…)', function (string $style) {
    $arabic = ['المندي' => ['مندي دجاج', 'لحم مندي'], 'مقبلات' => ['حمص', 'فتوش']];
    expect(hlPage(['style' => $style], 'دار المندي', $arabic))->toContain('<html lang="en" dir="rtl">')
        ->and(hlPage(['style' => $style]))->toContain('<html lang="en" dir="ltr">')
        ->and(hlPage(['style' => $style, 'menu_direction' => 'rtl']))->toContain('<html lang="en" dir="rtl">')
        ->and(hlPage(['style' => $style, 'menu_direction' => 'ltr'], 'دار المندي', $arabic))->toContain('<html lang="en" dir="ltr">');
})->with(TenantAppearance::ART_STYLES);

it('shows the Halal badge and Ramadan mode only in style I, and only when switched on', function () {
    $off = hlPage(['style' => 'islamic']);
    expect($off)->not->toContain('class="hb"')->not->toMatch('/<body class="[^"]*\brm\b/');

    $on = hlPage(['style' => 'islamic', 'show_halal' => true, 'ramadan_mode' => true]);
    expect($on)->toContain('class="hb"')->toContain('>Halal</p>')->toMatch('/<body class="S-islamic[^"]* rm"/');

    $elsewhere = hlPage(['style' => 'grid', 'show_halal' => true, 'ramadan_mode' => true]);
    expect($elsewhere)->not->toContain('class="hb"')->not->toMatch('/<body class="[^"]*\brm\b/');
});

it('has no religious text and no pictures of people or animals in style I (decoration only)', function () {
    $css = (string) file_get_contents(resource_path('public-menu/style-islamic.css'));
    // The only text the style adds is none: no `content:"…"` with words, only empty decorations.
    preg_match_all('/content:"([^"]*)"/', $css, $m);
    expect(array_unique($m[1]))->toBe(['']);
    expect($css)->not->toMatch('/\p{Arabic}/u');
});

it('shows black-and-white photos and the accent only in style K, and only when switched on', function () {
    $mono = hlPage(['style' => 'mono', 'primary_color' => '#1d4ed8']);
    expect($mono)->not->toMatch('/<body class="[^"]*\bgs\b/')
        ->toContain(':root{--a:#111111;--af:#ffffff;')                    // ink, not the brand colour
        ->toContain('@media (prefers-color-scheme:dark){:root{--a:#f2f2f2;--af:#111111')
        ->toContain('<meta name="theme-color" content="#111111">');

    $accent = hlPage(['style' => 'mono', 'primary_color' => '#1d4ed8', 'mono_accent' => true, 'photos_greyscale' => true]);
    expect($accent)->toMatch('/<body class="S-mono[^"]* gs"/')->toContain(':root{--a:#1d4ed8;');

    $grid = hlPage(['style' => 'grid', 'photos_greyscale' => true]);
    expect($grid)->not->toMatch('/<body class="[^"]*\bgs\b/');
});

it('gives each category of style L one of six colours, the same in the bar and on the page', function () {
    $html = hlPage(['style' => 'colour'], 'Juicy', ['Juices' => ['Mango'], 'Smoothies' => ['Berry'], 'Ice cream' => ['Cup'],
        'Waffles' => ['Waffle'], 'Snacks' => ['Fries'], 'Kids' => ['Mini'], 'Coffee' => ['Latte']]);
    preg_match_all('#<a href="\#c\d+" class="(k\d)">#', $html, $bar);
    preg_match_all('#<section id="c\d+" class="(k\d)">#', $html, $page);
    expect($bar[1])->toBe(['k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k1'])->and($page[1])->toBe($bar[1]);

    expect(hlPage(['style' => 'grid']))->not->toMatch('#<section id="c\d+" class="k#');
});

it('makes L\'s colours from the brand colour, with readable text on every one, light and dark', function (string $brand) {
    foreach (['light', 'dark'] as $mode) {
        preg_match_all('/--c(\d):(#[0-9a-f]{6});--t\d:(#[0-9a-f]{6});--o\d:(#[0-9a-f]{6})/', PageTheme::palette($brand, $mode), $m, PREG_SET_ORDER);
        expect($m)->toHaveCount(6);
        foreach ($m as [, $n, $colour, $tint, $text]) {
            expect(Contrast::ratio($colour, $text))->toBeGreaterThanOrEqual(Contrast::MIN, "{$brand} {$mode} c{$n}");
            // The tint is pale in light mode and dark in dark mode, so the page's own text reads on it.
            expect(Contrast::luminance($tint))->{$mode === 'light' ? 'toBeGreaterThan' : 'toBeLessThan'}($mode === 'light' ? 0.7 : 0.08);
        }
        // The six colours are different, and the first is the brand colour (or a shade of it when its text needed it).
        expect(array_unique(array_column($m, 2)))->toHaveCount(6);
    }
    expect(PageTheme::palette($brand, 'light'))->toContain('--c1:'.(Contrast::ratio($brand, Contrast::textOn($brand)) >= Contrast::MIN ? $brand : ''));
})->with(['#b4432a', '#e8437f', '#fde047', '#0b1f3a', '#5cc9a7', '#777777', '#000000', '#ffffff', '#2ce58a']);

it('loads H\'s own font from /menu-assets, only in style H, allowed by the CSP and cached by name', function () {
    $css = InlineAssets::css('arabic');
    preg_match_all('#url\((/menu-assets/(kufi-arabic-700)\.([0-9a-f]{10})\.woff2)\)#', $css, $m, PREG_SET_ORDER);
    expect($m)->toHaveCount(1)->and($css)->toContain('font-display:optional');
    // Only Arabic letters use it: a menu in Latin letters downloads no font (CI speed, #231).
    expect(substr_count($css, '@font-face'))->toBe(1)
        ->and($css)->toMatch('/unicode-range:U\+0600-06FF[^;}]*/')->not->toContain('U+0020-007E')->not->toContain('kufi-latin');
    foreach ($m as [, $url, $name, $hash]) {
        $source = (string) file_get_contents(resource_path("public-menu/fonts/{$name}.woff2"));
        expect(substr(md5($source), 0, 10))->toBe($hash)
            ->and(file_get_contents(public_path(ltrim($url, '/'))))->toBe($source)
            ->and(strlen($source))->toBeLessThan(12_000);              // a small subset each
    }
    expect(Csp::menu())->toContain("font-src 'self'");
    foreach (array_diff(TenantAppearance::STYLES, ['arabic']) as $other) {
        expect(InlineAssets::css($other))->not->toContain('@font-face');
    }
    expect(file_exists(resource_path('public-menu/fonts/OFL.txt')))->toBeTrue();
});

it('saves the style options in Appearance and checks the direction', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $body = ['primary_color' => '#0f5c4a', 'font' => 'system', 'style' => 'arabic',
        'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true];

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$body, 'menu_direction' => 'sideways'], idem())->assertInvalid('menu_direction');
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$body, 'menu_direction' => 'rtl', 'show_halal' => true, 'ramadan_mode' => true,
        'photos_greyscale' => true, 'mono_accent' => true], idem())->assertOk()
        ->assertJsonPath('data.menu_direction', 'rtl')->assertJsonPath('data.show_halal', true)->assertJsonPath('data.ramadan_mode', true)
        ->assertJsonPath('data.photos_greyscale', true)->assertJsonPath('data.mono_accent', true);
    // An older app sends none of them: they keep their values.
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), $body, idem())->assertOk()
        ->assertJsonPath('data.menu_direction', 'rtl')->assertJsonPath('data.show_halal', true);

    MenuPageCache::forget($tenant);
    $this->get('/'.$tenant->slug)->assertOk()->assertSee('dir="rtl"', false);
});

it('previews the style options before they are saved', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $query = http_build_query(['style' => 'islamic', 'show_halal' => '1', 'ramadan_mode' => '1']);
    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.$query)->assertOk()
        ->assertSee('class="hb"', false)->assertSee(' rm"', false);
    $query = http_build_query(['style' => 'arabic', 'menu_direction' => 'rtl']);
    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.$query)->assertOk()->assertSee('dir="rtl"', false);
});

it('offers H–L on Plus and up, not on Free (switchable per plan)', function () {
    $free = Plan::where('slug', 'free')->sole();
    $plus = Plan::where('slug', 'plus')->sole();
    $new = ['arabic', 'islamic', 'somali', 'mono', 'colour'];

    $onFree = Tenant::factory()->create(['plan_id' => $free->id]);
    $onPlus = Tenant::factory()->create(['plan_id' => $plus->id]);
    Features::forget();
    expect(array_intersect($new, Features::styles($onFree)))->toBe([])
        ->and(array_values(array_intersect($new, Features::styles($onPlus))))->toBe($new);

    DB::table('plan_features')->where('plan_id', $free->id)->where('feature_key', 'style_somali')->update(['enabled' => true]);
    Features::forget();
    expect(Features::styles($onFree))->toContain('somali');
});
