<?php

use App\Enums\Role;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Item;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

it('answers 404 for another restaurant\'s menu data used through your own restaurant', function (string $method, string $path) {
    [$owner, $mine] = member(Role::Owner);
    $theirs = Tenant::factory()->create();
    [$menu, $category, $item] = sampleMenu($theirs);
    [$group, $tag] = asTenant($theirs, fn () => [
        AddonGroup::create(['name' => ['en' => 'G']]),
        Tag::create(['name' => ['en' => 'T']]),
    ]);
    $option = asTenant($theirs, fn () => $group->options()->create(['name' => ['en' => 'O']]));

    $url = strtr($path, ['{menu}' => $menu->id, '{category}' => $category->id, '{item}' => $item->id,
        '{group}' => $group->id, '{tag}' => $tag->id, '{option}' => $option->id]);

    $this->actingAs($owner)->json($method, t($mine, $url), ['name' => ['en' => 'Hacked'], 'is_sold_out' => true, 'price_minor' => 1], idem())
        ->assertNotFound();

    asTenant($theirs, fn () => expect($item->fresh()->name)->toBe(['en' => 'Macchiato'])->and($item->fresh()->is_sold_out)->toBeFalse());
})->with([
    ['GET', 'menus/{menu}'],
    ['GET', 'menus/{menu}/tree'],
    ['PATCH', 'menus/{menu}'],
    ['DELETE', 'menus/{menu}'],
    ['POST', 'menus/{menu}/duplicate'],
    ['PATCH', 'categories/{category}'],
    ['DELETE', 'categories/{category}'],
    ['POST', 'categories/{category}/duplicate'],
    ['GET', 'items/{item}'],
    ['PATCH', 'items/{item}'],
    ['PATCH', 'items/{item}/sold-out'],
    ['PATCH', 'items/{item}/price'],
    ['DELETE', 'items/{item}'],
    ['POST', 'items/{item}/duplicate'],
    ['PATCH', 'addon-groups/{group}'],
    ['DELETE', 'addon-groups/{group}'],
    ['PATCH', 'addon-options/{option}/sold-out'],
    ['PATCH', 'tags/{tag}'],
    ['DELETE', 'tags/{tag}'],
]);

it('refuses links to another restaurant\'s categories, add-ons and tags', function () {
    [$owner, $mine] = member(Role::Owner);
    [, $myCategory, $myItem] = sampleMenu($mine);
    $theirs = Tenant::factory()->create();
    [$theirMenu, $theirCategory, $theirItem] = sampleMenu($theirs);
    [$group, $tag] = asTenant($theirs, fn () => [AddonGroup::create(['name' => ['en' => 'G']]), Tag::where('key', 'vegan')->sole()]);

    $this->actingAs($owner)->postJson(t($mine, 'items'), ['category_id' => $theirCategory->id, 'name' => ['en' => 'X']], idem())
        ->assertJsonValidationErrors('category_id');
    $this->patchJson(t($mine, "items/{$myItem->id}"), ['addon_group_ids' => [$group->id], 'tag_ids' => [$tag->id]], idem())
        ->assertJsonValidationErrors(['addon_group_ids.0', 'tag_ids.0']);
    $this->postJson(t($mine, 'categories'), ['menu_id' => $theirMenu->id, 'name' => ['en' => 'X']], idem())
        ->assertJsonValidationErrors('menu_id');
    $this->postJson(t($mine, 'items/bulk'), ['ids' => [$theirItem->id], 'action' => 'move', 'category_id' => $myCategory->id], idem())
        ->assertNoContent();
    $this->postJson(t($mine, 'items/bulk-price'), ['ids' => [$theirItem->id], 'percent' => 50], idem())->assertOk()->assertJsonCount(0, 'data');

    asTenant($theirs, fn () => expect($theirItem->fresh()->category_id)->toBe($theirCategory->id)
        ->and($theirItem->fresh()->price_minor)->toBe(12000));
});

it('makes the database itself refuse an item in another restaurant\'s category', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    [, $categoryOfB] = sampleMenu($b);

    asTenant($a, fn () => Item::create(['category_id' => $categoryOfB->id, 'name' => ['en' => 'Smuggled']]));
})->throws(QueryException::class);

it('cannot list another restaurant\'s menus', function () {
    [$owner, $mine] = member(Role::Owner);
    sampleMenu(Tenant::factory()->create());

    $this->actingAs($owner)->getJson(t($mine, 'menus'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(t($mine, 'addon-groups'))->assertOk()->assertJsonCount(0, 'data');
    expect(collect($this->getJson(t($mine, 'tags'))->json('data'))->every(fn ($t) => $t['is_system']))->toBeTrue();
    expect(Category::withoutTenancy()->count())->toBe(1);
});
