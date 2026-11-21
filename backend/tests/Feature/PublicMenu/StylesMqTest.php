<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PublicMenu\CategoryIcons;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuPageCache;
use Illuminate\Support\Facades\DB;

/*
 * Styles M · Coffee House to Q · Luxe (owner, 2026-11-19, QUESTIONS #232): a header
 * drawing when there is no banner, category icons from the category's name in any
 * language, a category photo that replaces its icon, all switchable with "Decoration".
 * The features shared with A–L: StylesEfgTest (dataset "efg").
 */
dataset('mq', ['coffee', 'grill', 'garden', 'coastal', 'luxe']);

/**
 * The guest page of a restaurant in the given style.
 *
 * @param  list<array{0: array<string, string>, 1?: bool}>  $categories  name per language, and whether it has a photo
 */
function mqPage(array $appearance, array $categories = [[['en' => 'Coffee']]]): string
{
    $tenant = Tenant::factory()->create();
    asTenant($tenant, function () use ($appearance, $categories) {
        $menu = Menu::create(['name' => ['en' => 'All day'], 'slug' => 'all-day']);
        foreach ($categories as $i => $row) {
            $media = ($row[1] ?? false) ? Media::create([
                'base_path' => 'category/'.$i, 'mime' => 'image/jpeg', 'width' => 800, 'height' => 800, 'bytes' => 1,
                'variants' => ['320' => '320.webp', '640' => '640.webp', 'jpg' => '640.jpg'],
            ]) : null;
            $category = Category::create(['menu_id' => $menu->id, 'name' => $row[0], 'sort' => $i, 'media_id' => $media?->id]);
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$i], 'price_minor' => 12000]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, ...$appearance]);
    });

    return test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('draws the header art and the category icons, and the switch turns both off', function (string $style) {
    $on = mqPage(['style' => $style]);
    expect($on)->toContain(' dc"')->toContain(InlineAssets::art($style))
        ->toContain('<svg class="sx" aria-hidden="true" width="0" height="0"><symbol id="i-cup"')
        ->toContain('<svg class="ci" aria-hidden="true" width="24" height="24"><use href="#i-cup"/></svg>Coffee</h2>');
    expect(InlineAssets::art($style))->toStartWith('<svg class="ha" aria-hidden="true"')
        ->and(strlen(InlineAssets::art($style)))->toBeLessThan(1_000);   // tiny inline drawing, no request

    $off = mqPage(['style' => $style, 'show_decoration' => false]);
    expect($off)->not->toContain(' dc"')->not->toContain('class="ha"')
        ->not->toContain('class="sx"')->not->toContain('class="ci"')
        ->toContain('<h2>Coffee</h2>');
})->with('mq');

it('keeps the drawing out of the header when there is a banner', function (string $style) {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, function () use ($style) {
        $banner = Media::create(['base_path' => 'banner/1', 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
            'variants' => ['640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg']]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => $style, 'cover_media_id' => $banner->id]);
    });
    $html = test()->get('/'.$tenant->slug)->assertOk()->getContent();

    expect($html)->toContain('<div class="cv"')->not->toContain('class="ha"')
        ->toContain('<use href="#i-cup"/>');                            // icons stay
})->with('mq');

it('shows the category photo in place of its icon, lazy and small', function () {
    $html = mqPage(['style' => 'coffee'], [[['en' => 'Coffee'], true], [['en' => 'Cakes']]]);

    expect($html)->toContain('<span class="ci"')->toContain('sizes="72px"')->toContain('loading="lazy"')
        ->not->toContain('#i-cup')->not->toContain('id="i-cup"')       // photo wins, no unused symbol
        ->toContain('<use href="#i-cake"/>')->toContain('<symbol id="i-cake"');

    $noPhotos = mqPage(['style' => 'coffee', 'show_photos' => false], [[['en' => 'Coffee'], true]]);
    expect($noPhotos)->not->toContain('<span class="ci"')->toContain('<use href="#i-cup"/>');
});

it('adds no art, icons or category photos to the styles A–L', function (string $style) {
    $html = mqPage(['style' => $style], [[['en' => 'Coffee'], true]]);

    expect($html)->not->toContain('class="ha"')->not->toContain('class="ci"')
        ->not->toContain('class="sx"')->not->toContain(' dc"')
        ->and(InlineAssets::art($style))->toBe('');
})->with(array_values(array_diff(TenantAppearance::STYLES, TenantAppearance::ART_STYLES)));

it('picks the icon from the name in English, Amharic, Somali and Arabic', function (array $names, string $icon) {
    expect(CategoryIcons::pick('luxe', $names, 0))->toBe($icon);
})->with([
    [['Coffee'], 'cup'], [['ቡና'], 'cup'], [['قهوة'], 'cup'], [['Buna ceremony'], 'jebena'],
    [['Kalluun'], 'fish'], [['Catch of the day'], 'fish'], [['Prawns & calamari'], 'shrimp'],
    [['Steak'], 'flame'], [['ጥብስ'], 'flame'], [['Hilib ari'], 'flame'], [['Nyama choma'], 'skewer'],
    [['Teas'], 'leaf'], [['ፆም · Fasting'], 'leaf'], [['Fresh juices'], 'glass'], [['شاي'], 'leaf'],
    [['Bariis'], 'rice'], [['Breakfast'], 'egg'], [['Desserts'], 'cake'], [['Pizza'], 'pizza'],
    [['Tej & wine'], 'wine'], [['Chef specials'], 'star'], [['Main dishes'], 'cloche'],
    [['Soups', 'Maraq'], 'bowl'], [['Misc', 'ቡና'], 'cup'],                // any language counts
]);

it('only matches whole word starts, and turns through the style set otherwise', function () {
    expect(CategoryIcons::pick('coffee', ['Steaks'], 0))->toBe('flame')         // not "tea"
        ->and(CategoryIcons::pick('coffee', ['Instead'], 0))->toBe('cup')      // "tea" inside a word: fallback
        ->and(CategoryIcons::pick('coastal', ['Zzz'], 0))->toBe('fish')
        ->and(CategoryIcons::pick('coastal', ['Zzz'], 1))->toBe('shrimp')
        ->and(CategoryIcons::pick('coastal', ['Zzz'], 6))->toBe('fish')
        ->and(CategoryIcons::pick('unknown', ['Zzz'], 1))->toBe('cake');       // never fails

    foreach (CategoryIcons::SETS as $set) {
        foreach ($set as $icon) {
            expect(CategoryIcons::PATHS)->toHaveKey($icon);
        }
    }
    expect(CategoryIcons::symbols(['cup', 'cup', 'nope']))->toBe('<symbol id="i-cup" viewBox="0 0 24 24"><path d="'.CategoryIcons::PATHS['cup'].'"/></symbol>');
});

it('uses the icon set on the category when it is one of ours', function () {
    expect(CategoryIcons::pick('coffee', ['Coffee'], 0, 'fish'))->toBe('fish')
        ->and(CategoryIcons::pick('coffee', ['Coffee'], 0, 'rocket'))->toBe('cup')   // unknown: the guess
        ->and(CategoryIcons::pick('coffee', ['Coffee'], 0, ''))->toBe('cup');

    $tenant = Tenant::factory()->create();
    asTenant($tenant, function () {
        $menu = Menu::create(['name' => ['en' => 'All day'], 'slug' => 'all-day']);
        $category = Category::create(['menu_id' => $menu->id, 'name' => ['en' => 'Coffee'], 'icon' => 'jebena']);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Buna'], 'price_minor' => 3000]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'coffee']);
    });
    expect(test()->get('/'.$tenant->slug)->assertOk()->getContent())->toContain('<use href="#i-jebena"/></svg>Coffee');
});

it('gives neighbouring unknown categories different icons, and escapes nothing unsafe', function () {
    $html = mqPage(['style' => 'grill'], [[['en' => 'One']], [['en' => 'Two']], [['en' => '<b>Three</b>']]]);

    expect($html)->toContain('<use href="#i-flame"/></svg>One')->toContain('<use href="#i-skewer"/></svg>Two')
        ->toContain('<use href="#i-cloche"/></svg>&lt;b&gt;Three&lt;/b&gt;');
});

it('saves the Decoration switch in Appearance and previews it', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $body = ['primary_color' => '#8a3b12', 'font' => 'system', 'style' => 'coffee',
        'show_prices' => true, 'show_photos' => true, 'show_descriptions' => true, 'show_calories' => true];

    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertOk()->assertJsonPath('data.show_decoration', true);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$body, 'show_decoration' => 'maybe'], idem())->assertInvalid('show_decoration');
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...$body, 'show_decoration' => false], idem())->assertOk()
        ->assertJsonPath('data.show_decoration', false);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), $body, idem())->assertOk()   // an older app: kept
        ->assertJsonPath('data.show_decoration', false);

    MenuPageCache::forget($tenant);
    $this->get('/'.$tenant->slug)->assertOk()->assertDontSee('class="ha"', false);
    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.http_build_query(['style' => 'coffee', 'show_decoration' => '1']))
        ->assertOk()->assertSee('class="ha"', false)->assertSee(' dc"', false);
    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.http_build_query(['style' => 'coffee', 'show_decoration' => '0']))
        ->assertOk()->assertDontSee('class="ha"', false);
});

it('offers M–Q on Plus and up, not on Free (switchable per plan)', function () {
    $free = Plan::where('slug', 'free')->sole();
    $plus = Plan::where('slug', 'plus')->sole();

    $onFree = Tenant::factory()->create(['plan_id' => $free->id]);
    $onPlus = Tenant::factory()->create(['plan_id' => $plus->id]);
    Features::forget();
    expect(array_intersect(TenantAppearance::ART_STYLES, Features::styles($onFree)))->toBe([])
        ->and(array_values(array_intersect(TenantAppearance::ART_STYLES, Features::styles($onPlus))))->toBe(TenantAppearance::ART_STYLES);

    DB::table('plan_features')->where('plan_id', $free->id)->where('feature_key', 'style_grill')->update(['enabled' => true]);
    Features::forget();
    expect(Features::styles($onFree))->toContain('grill')->not->toContain('luxe');
});
