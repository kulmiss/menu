<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\PageTheme;

/**
 * Layouts (owner, 2026-11-21): each style keeps its own layout by default; a
 * restaurant may pick photo cards in two columns (grid) or a list with small
 * photos (list) on Plus and up; the text layout ("Show photos" off) is on
 * every plan.
 */
function layoutPage(array $appearance = [], ?Tenant $tenant = null): string
{
    $tenant ??= Tenant::factory()->create();
    [, $category, $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $item, $appearance) {
        $photo = fn (string $p) => Media::create([
            'base_path' => $p, 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 1200, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        $item->update(['media_id' => $photo('dish/1')->id]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Tea'], 'price_minor' => 3000, 'media_id' => $photo('dish/2')->id, 'sort' => 5]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, ...$appearance]);
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

function bodyClass(string $html): string
{
    preg_match('#<body class="([^"]*)"#', $html, $m);

    return $m[1] ?? '';
}

it('keeps every style\'s own layout when none is picked', function (string $style) {
    $class = bodyClass(layoutPage(['style' => $style]));

    expect($class)->toStartWith('S-'.$style)->not->toContain('L-');
})->with(fn () => TenantAppearance::STYLES);

it('gives each style its own layout: grid, list or text', function () {
    $own = array_map(fn (array $s) => $s['layout'], PageTheme::STYLES);

    expect(array_keys($own))->toEqualCanonicalizing(TenantAppearance::STYLES)
        ->and(array_keys(array_filter($own, fn ($l) => $l === 'grid')))->toBe(['editorial', 'grid', 'islamic', 'burger', 'juice'])
        ->and(array_keys(array_filter($own, fn ($l) => $l === 'text')))->toBe(['minimal']);
});

it('shows photo cards in two columns on a list style', function () {
    $html = layoutPage(['style' => 'bistro', 'menu_layout' => 'grid']);

    expect(bodyClass($html))->toBe('S-bistro L-grid')
        // The photos are picked for half the screen, not the 104 px side photo.
        ->and($html)->toContain('sizes="(min-width:44rem) 330px, 50vw"')->not->toContain('sizes="104px"')
        ->toContain('.L-grid section ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))');
});

it('shows a list with small photos on a grid style', function (string $style) {
    $html = layoutPage(['style' => $style, 'menu_layout' => 'list']);

    expect(bodyClass($html))->toBe('S-'.$style.' L-list')
        ->and($html)->toContain('sizes="76px"')
        ->toContain('.L-list li>.it{');
})->with(['grid', 'islamic', 'editorial']);

it('adds no class when the picked layout is the style\'s own', function () {
    expect(bodyClass(layoutPage(['style' => 'grid', 'menu_layout' => 'grid'])))->toBe('S-grid')
        ->and(bodyClass(layoutPage(['style' => 'bistro', 'menu_layout' => 'list'])))->toBe('S-bistro');
});

it('uses the text layout with photos off, whatever layout is picked', function () {
    $html = layoutPage(['style' => 'bistro', 'menu_layout' => 'grid', 'show_photos' => false]);

    expect(bodyClass($html))->toBe('S-bistro NP')
        ->and($html)->not->toContain('dish/1')->not->toContain('<picture');
});

it('lets style D show photos in a grid or list (Plus and up)', function (string $layout) {
    $html = layoutPage(['style' => 'minimal', 'menu_layout' => $layout]);

    expect(bodyClass($html))->toBe('S-minimal L-'.$layout)
        ->and($html)->toContain('dish/1/');
})->with(['grid', 'list']);

it('keeps the style\'s own layout on the Free plan', function () {
    $free = Plan::where('slug', 'free')->sole();
    Features::forget();

    $grid = layoutPage(['style' => 'grid', 'menu_layout' => 'list'], Tenant::factory()->create(['plan_id' => $free->id]));
    $minimal = layoutPage(['style' => 'minimal', 'menu_layout' => 'grid'], Tenant::factory()->create(['plan_id' => $free->id]));

    expect(bodyClass($grid))->toBe('S-grid')->and($grid)->toContain('dish/1/')
        ->and(bodyClass($minimal))->toBe('S-minimal NP')->and($minimal)->not->toContain('dish/1');
});

it('is off on Free and on for the paid plans', function () {
    Features::forget();
    $on = fn (string $plan) => Features::enabled(Tenant::factory()->create(['plan_id' => Plan::where('slug', $plan)->sole()->id]), 'layouts');

    expect($on('free'))->toBeFalse()->and($on('plus'))->toBeTrue()->and($on('pro'))->toBeTrue()->and($on('business'))->toBeTrue()
        ->and(Features::DEFAULTS['layouts'])->toBeTrue();
});

it('saves the layout and tells the admin what guests see', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'style' => 'bistro', 'menu_layout' => 'grid'], idem())
        ->assertOk()
        ->assertJsonPath('data.menu_layout', 'grid')
        ->assertJsonPath('data.layout', 'grid')
        ->assertJsonPath('data.layouts', true)
        ->assertJsonPath('data.style_layouts.bistro', 'list')
        ->assertJsonPath('data.style_layouts.minimal', 'text');

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'style' => 'bistro', 'menu_layout' => 'grid', 'show_photos' => false], idem())
        ->assertOk()->assertJsonPath('data.layout', 'text');
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'menu_layout' => null], idem())
        ->assertOk()->assertJsonPath('data.menu_layout', null)->assertJsonPath('data.layout', 'grid');
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'menu_layout' => 'text'], idem())
        ->assertInvalid('menu_layout');
});

it('tells a restaurant on Free that only the style\'s own layout shows', function () {
    $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'free')->sole()->id]);
    [$owner] = member(Role::Owner, $tenant);
    Features::forget();

    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'style' => 'grid', 'menu_layout' => 'list'], idem())
        ->assertOk()->assertJsonPath('data.layouts', false)->assertJsonPath('data.layout', 'grid');
});

it('previews a layout without saving it', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.http_build_query(['style' => 'bistro', 'menu_layout' => 'grid']))
        ->assertOk()->assertSee('<body class="S-bistro', false);
    expect(asTenant($tenant, fn () => TenantAppearance::count()))->toBe(0);
    $this->actingAs($owner)->getJson(t($tenant, 'appearance/preview').'?menu_layout=cards')->assertInvalid('menu_layout');
});

it('previews the menu as on a phone in light or dark mode', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $preview = fn (array $q) => (string) $this->actingAs($owner)->get(t($tenant, 'appearance/preview').'?'.http_build_query(['style' => 'grid', ...$q]))->assertOk()->getContent();

    // As the phone decides (the default): the dark rules wait for a dark phone.
    expect($preview([]))->toContain('@media (prefers-color-scheme:dark){:root{--bg:#0f1412');
    // Dark: the dark rules always apply; light: never.
    expect($preview(['scheme' => 'dark']))->not->toContain('prefers-color-scheme:dark')->toContain('@media all{:root{--bg:#0f1412')
        ->and($preview(['scheme' => 'light']))->not->toContain('prefers-color-scheme:dark')->toContain('@media not all{:root{--bg:#0f1412');
    $this->actingAs($owner)->getJson(t($tenant, 'appearance/preview').'?scheme=sepia')->assertInvalid('scheme');
});

it('ships the layout rules once, and only to a page in another layout', function () {
    // Shared (base.css), not one copy per style.
    expect(substr_count(InlineAssets::css('bistro', true, true), '.L-grid section ul{'))->toBe(1)
        ->and(substr_count(InlineAssets::css('grid', true, true), '.L-list section ul{'))->toBe(1);
    // A page in its style's own layout (nearly every restaurant) carries none of them: as small as before.
    expect(InlineAssets::css('bistro'))->not->toContain('.L-grid')->not->toContain('.L-list')
        ->and(strlen(InlineAssets::css('bistro')))->toBeLessThan(strlen(InlineAssets::css('bistro', true, true)) - 2_000);
    expect(layoutPage(['style' => 'bistro']))->not->toContain('.L-grid')
        ->and(layoutPage(['style' => 'bistro', 'menu_layout' => 'grid']))->toContain('.L-grid section ul{');
});
