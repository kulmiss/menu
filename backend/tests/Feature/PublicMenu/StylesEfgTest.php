<?php

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Media;
use App\Models\PricePeriod;
use App\Models\Promotion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\PageTheme;

/*
 * Styles E · Mesob, F · Night Lounge and G · Street Board, and H · Arabic to
 * L · Colourful (owner, 2026-11-18): every guest-menu feature of A–D, the
 * style's own CSS only, the right photo sizes, one dark palette for F and G,
 * and Night Lounge prices on one line. Style options of H–L: StylesHlTest.
 */
function efgPage(string $style, array $appearance = [], ?callable $extra = null): string
{
    $tenant = Tenant::factory()->create();
    [, $category, $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($style, $category, $item, $appearance, $extra) {
        $photo = fn (string $p) => Media::create([
            'base_path' => $p, 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        $item->update(['media_id' => $photo('dish/1')->id]);
        $tea = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Shai · ሻይ'], 'sort' => 5]);
        ItemVariant::create(['item_id' => $tea->id, 'name' => ['en' => 'Small'], 'price_minor' => 6000, 'sort' => 0]);
        ItemVariant::create(['item_id' => $tea->id, 'name' => ['en' => 'Large'], 'price_minor' => 8050, 'sort' => 1]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Shiro'], 'price_minor' => 22000, 'is_sold_out' => true, 'sort' => 6])
            ->tags()->attach(Tag::where('key', 'vegan')->firstOrFail()->id, ['tenant_id' => $category->tenant_id]);
        Promotion::create(['type' => 'special', 'title' => ['en' => 'Sunset set'], 'media_id' => $photo('promo/1')->id]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style,
            'logo_media_id' => $photo('logo/1')->id, 'cover_media_id' => $photo('banner/1')->id, ...$appearance]);
        if ($extra) {
            $extra($item);
        }
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

dataset('efg', ['mesob', 'lounge', 'board', 'arabic', 'islamic', 'somali', 'mono', 'colour']);

it('shows every menu feature in the style, with its own CSS only', function (string $style) {
    $html = efgPage($style);

    expect($html)->toContain('class="S-'.$style.'"')
        ->toContain('Macchiato')->toContain('Shai · ሻይ')            // other scripts in dish names
        ->toContain('class="pr"')->toContain('Sold out')->toContain('Vegan')
        ->toContain('class="spc"')->toContain('Sunset set')          // special
        ->toContain('data-x="A"')                                    // "+" My order
        ->toContain('class="lg"')->toContain('<div class="cv"')      // logo and banner
        ->toContain('id="st"')                                       // hours pill slot
        ->toContain(InlineAssets::css($style));
    foreach (array_diff(TenantAppearance::STYLES, [$style]) as $other) {
        expect($html)->not->toContain(InlineAssets::css($other));
    }
})->with('efg');

it('asks for dish photos at the size the style shows them (srcset sizes)', function (string $style) {
    $html = efgPage($style);
    $sizes = PageTheme::STYLES[$style]['photo'];

    expect($html)->toContain('sizes="'.$sizes.'"');
    // With a banner, no dish photo loads before the banner (it is the largest paint).
    expect(substr_count($html, 'fetchpriority="high"'))->toBe(2); // the banner preload and the banner itself
})->with('efg');

it('gives the banner its shape in the style and keeps the page photo-free when photos are off', function (string $style) {
    [$w, $h] = PageTheme::STYLES[$style]['banner'];
    expect(efgPage($style))->toContain('height="'.(int) round(1280 * $h / $w).'"');

    $plain = efgPage($style, ['show_photos' => false]);
    expect($plain)->toContain('class="S-'.$style.' NP"')->not->toContain('class="im"');
})->with('efg');

it('keeps one dark palette in F and G and both light and dark in E', function (string $style, bool $darkOnly) {
    $html = efgPage($style);
    preg_match('#<style>(.*?)</style>#s', $html, $css);

    // (The shared CSS keeps its own small dark-phone rules, e.g. the hours pill; the palette is what counts.)
    expect(str_contains($css[1], '@media (prefers-color-scheme:dark){:root{'))->toBe(! $darkOnly)
        ->and(str_contains($css[1], 'color-scheme:dark;'))->toBe($darkOnly);
})->with([['mesob', false], ['lounge', true], ['board', true], ['arabic', false], ['islamic', false], ['somali', false], ['mono', false], ['colour', false]]);

it('keeps Night Lounge prices on one line, old price included (happy hour)', function () {
    $css = InlineAssets::css('lounge');
    expect($css)->toContain('li>.it .pr{grid-area:pr;margin:0;white-space:nowrap')
        ->toContain('li>.it .pr s{display:block');

    $html = efgPage('lounge', [], function (Item $item) {
        $period = PricePeriod::create(['name' => ['en' => 'Happy hour'], 'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'starts_at' => '00:00', 'ends_at' => '23:59']);
        $period->items()->create(['item_id' => $item->id, 'mode' => 'percent', 'value' => 20]);
    });
    expect($html)->toMatch('#<span class="pr"><s>[^<]+</s> [^<]+</span>#');
});

it('loads nothing extra from the CSS: only inline SVG patterns and, in style H only, its own font (CSP, speed)', function (string $style) {
    $css = (string) file_get_contents(resource_path("public-menu/style-{$style}.css"));
    expect($css)->not->toContain('@import');
    // Every url() is a tiny inline SVG, or (H · Arabic) its own subset font file from /menu-assets.
    preg_match_all('/url\(\s*([^)]{0,30})/', $css, $urls);
    foreach ($urls[1] as $url) {
        expect(str_starts_with($url, '"data:image/svg+xml') || str_starts_with($url, 'font:'))->toBeTrue("{$style}: url({$url}…)");
    }
    expect(str_contains($css, '@font-face'))->toBe($style === 'arabic')
        ->and(str_contains($css, 'url(font:'))->toBe($style === 'arabic');
})->with(TenantAppearance::STYLES);

it('shows the empty-menu note and a 500-dish menu in every new style', function (string $style) {
    $tenant = Tenant::factory()->create();
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style]));
    $this->get('/'.$tenant->slug)->assertOk()->assertSee('class="nr"', false);

    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        $rows = [];
        foreach (range(1, 500) as $n) {
            $rows[] = ['tenant_id' => $category->tenant_id, 'category_id' => $category->id, 'name' => json_encode(['en' => "Dish {$n}"]), 'price_minor' => 1000 + $n, 'sort' => $n, 'created_at' => now(), 'updated_at' => now()];
        }
        Item::insert($rows);
    });
    MenuPageCache::forget($tenant);
    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect(substr_count($html, '<li data-i='))->toBe(501);
})->with('efg');
