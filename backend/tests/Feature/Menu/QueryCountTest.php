<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/** Queries a request runs. */
function countQueries(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** Adds categories with items (with sizes and tags) to the sample menu. */
function grow(Tenant $tenant, int $menuId, int $categories, int $items): void
{
    asTenant($tenant, function () use ($menuId, $categories, $items) {
        for ($c = 0; $c < $categories; $c++) {
            $category = Category::create(['menu_id' => $menuId, 'name' => ['en' => "C{$c}"]]);
            for ($i = 0; $i < $items; $i++) {
                $item = Item::create(['category_id' => $category->id, 'name' => ['en' => "I{$c}-{$i}"], 'price_minor' => 100]);
                $item->variants()->create(['name' => ['en' => 'L'], 'price_minor' => 200]);
            }
        }
    });
}

it('loads the editor menu with a fixed number of queries, however big the menu (no N+1)', function (string $path) {
    [$owner, $tenant] = member(Role::Owner);
    [$menu] = sampleMenu($tenant);
    $url = t($tenant, str_replace('{menu}', (string) $menu->id, $path));

    $small = countQueries(fn () => $this->actingAs($owner)->getJson($url)->assertOk());
    grow($tenant, $menu->id, 4, 10);
    $big = countQueries(fn () => $this->actingAs($owner)->getJson($url)->assertOk());

    expect($big)->toBe($small);
})->with(['menus/{menu}/tree', 'menus', 'addon-groups', 'tags']);

it('exports any size of menu with a fixed number of queries', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu] = sampleMenu($tenant);

    $small = countQueries(fn () => $this->actingAs($owner)->get(t($tenant, 'export?format=csv'))->assertOk());
    grow($tenant, $menu->id, 3, 8);
    $big = countQueries(fn () => $this->actingAs($owner)->get(t($tenant, 'export?format=csv'))->assertOk());

    expect($big)->toBe($small);
});
