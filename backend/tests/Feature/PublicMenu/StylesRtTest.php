<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PublicMenu\CategoryIcons;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\PageTheme;

/*
 * Styles R · Burger & Pizza, S · Injera & Tibs and T · Juice & Drinks (owner,
 * 2026-11-21, QUESTIONS #243): artwork like M–Q (header drawing, category icons,
 * the "Decoration" switch), their own layout, Plus and up.
 */
dataset('rt', [
    'burger' => ['burger', 'Burgers', 'burger'],
    'injera' => ['injera', 'Wot · ወጥ', 'pot'],
    'juice' => ['juice', 'Fresh juices', 'citrus'],
]);

function rtPage(string $style, string $category, array $appearance = []): string
{
    $tenant = Tenant::factory()->create();
    asTenant($tenant, function () use ($style, $category, $appearance) {
        $menu = Menu::create(['name' => ['en' => 'All day'], 'slug' => 'all-day']);
        $c = Category::create(['menu_id' => $menu->id, 'name' => ['en' => $category], 'sort' => 0]);
        Item::create(['category_id' => $c->id, 'name' => ['en' => 'Dish'], 'price_minor' => 12000]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style, ...$appearance]);
    });

    return test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('is one of the styles, with its own CSS, artwork and options', function (string $style) {
    expect(TenantAppearance::STYLES)->toContain($style)
        ->and(TenantAppearance::ART_STYLES)->toContain($style)
        ->and(TenantAppearance::DIRECTION_STYLES)->toContain($style)
        // Its own CSS, on the surface the brand colour is checked against (PageTheme::STYLES).
        ->and(InlineAssets::css($style))->toContain('--bg:'.PageTheme::STYLES[$style]['light'])
        ->and(InlineAssets::art($style))->toStartWith('<svg class="ha" aria-hidden="true"')
        ->and(strlen(InlineAssets::art($style)))->toBeLessThan(1_400);   // tiny inline drawing, no request
})->with(['burger', 'injera', 'juice']);

it('draws the header art and its category icons, and the switch turns both off', function (string $style, string $category, string $icon) {
    $on = rtPage($style, $category);
    expect($on)->toContain('class="S-'.$style)->toContain(' dc"')->toContain(InlineAssets::art($style))
        ->toContain('<symbol id="i-'.$icon.'"')
        ->toContain('<use href="#i-'.$icon.'"/></svg>'.e($category).'</h2>');

    $off = rtPage($style, $category, ['show_decoration' => false]);
    expect($off)->not->toContain(' dc"')->not->toContain('class="ha"')->not->toContain('class="ci"')
        ->toContain('<h2>'.e($category).'</h2>');
})->with('rt');

it('gives the new styles their own words without changing any icon of the styles before them', function () {
    expect(CategoryIcons::pick('burger', ['Chicken wings'], 0))->toBe('drumstick')
        ->and(CategoryIcons::pick('burger', ['Sides'], 0))->toBe('fries')
        ->and(CategoryIcons::pick('injera', ['Beyaynetu · በያይነቱ'], 0))->toBe('injera')
        ->and(CategoryIcons::pick('juice', ['Spris'], 0))->toBe('tallglass')
        // Word starts only: "Water" is not "wat", "Tenderloin" not chicken.
        ->and(CategoryIcons::pick('injera', ['Water'], 2))->toBe('flame')
        ->and(CategoryIcons::pick('burger', ['Tenderloin'], 0))->not->toBe('drumstick')
        // N · Grill keeps a flame for burgers, O · Garden a glass for fresh juice, the fallback turn is unchanged.
        ->and(CategoryIcons::pick('grill', ['Burgers'], 0))->toBe('flame')
        ->and(CategoryIcons::pick('garden', ['Fresh juices'], 0))->toBe('glass')
        ->and(CategoryIcons::pick('coffee', ['Wot'], 1))->toBe('cake')
        ->and(CategoryIcons::pick('luxe', ['Fasting'], 0))->toBe('leaf');
});

it('has its own layout: R and T photo cards in two columns, S a list with round photos', function () {
    expect(PageTheme::STYLES['burger']['layout'])->toBe('grid')
        ->and(PageTheme::STYLES['injera']['layout'])->toBe('list')
        ->and(PageTheme::STYLES['juice']['layout'])->toBe('grid');
});

it('is for Plus and up: off on Free, on for the paid plans', function () {
    Features::forget();
    $styles = fn (string $plan) => Features::styles(Tenant::factory()->create(['plan_id' => Plan::where('slug', $plan)->sole()->id]));

    expect(array_intersect(['burger', 'injera', 'juice'], $styles('free')))->toBe([])
        ->and(array_values(array_intersect(['burger', 'injera', 'juice'], $styles('plus'))))->toBe(['burger', 'injera', 'juice']);
});
