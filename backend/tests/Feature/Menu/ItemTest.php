<?php

use App\Enums\Role;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Tag;

it('creates an item with sizes, add-ons, tags and allergens', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);
    [$group, $vegan] = asTenant($tenant, fn () => [
        AddonGroup::create(['name' => ['en' => 'Milk']]),
        Tag::where('key', 'vegan')->sole(),
    ]);

    $this->actingAs($owner)->postJson(t($tenant, 'items'), [
        'category_id' => $category->id,
        'name' => ['en' => 'Latte', 'am' => 'ላቴ'],
        'price_minor' => 9000, // ignored: sizes carry the prices
        'variants' => [
            ['name' => ['en' => 'Small'], 'price_minor' => 8000],
            ['name' => ['en' => 'Large'], 'price_minor' => 11000, 'is_default' => true],
        ],
        'addon_group_ids' => [$group->id],
        'tag_ids' => [$vegan->id],
        'allergens' => ['milk', 'soy'],
    ], idem())
        ->assertCreated()
        ->assertJsonPath('data.price_minor', null)
        ->assertJsonPath('data.from_price_minor', 8000)
        ->assertJsonPath('data.variants.1.is_default', true)
        ->assertJsonPath('data.addon_group_ids', [$group->id])
        ->assertJsonPath('data.tag_ids', [$vegan->id])
        ->assertJsonPath('data.allergens', ['milk', 'soy']);
});

it('keeps, changes, adds and removes sizes in one save', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [$small, $large] = asTenant($tenant, fn () => [
        $item->variants()->create(['name' => ['en' => 'Small'], 'price_minor' => 8000, 'sort' => 0]),
        $item->variants()->create(['name' => ['en' => 'Large'], 'price_minor' => 11000, 'sort' => 1]),
    ]);

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), [
        'variants' => [
            ['id' => $large->id, 'name' => ['en' => 'Large'], 'price_minor' => 12000],
            ['name' => ['en' => 'Medium'], 'price_minor' => 10000],
        ],
    ], idem())->assertOk()
        ->assertJsonPath('data.variants.0.id', $large->id)
        ->assertJsonPath('data.variants.0.price_minor', 12000)
        ->assertJsonPath('data.variants.1.name.en', 'Medium');

    expect(asTenant($tenant, fn () => $item->variants()->pluck('id')->contains($small->id)))->toBeFalse();
});

it('refuses two default sizes and sizes of another item', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [, , $other] = sampleMenu($tenant);
    $foreign = asTenant($tenant, fn () => $other->variants()->create(['name' => ['en' => 'X'], 'price_minor' => 1]));

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['variants' => [
        ['name' => ['en' => 'A'], 'price_minor' => 1, 'is_default' => true],
        ['name' => ['en' => 'B'], 'price_minor' => 2, 'is_default' => true],
    ]], idem())->assertUnprocessable()->assertJsonValidationErrors('variants');

    $this->patchJson(t($tenant, "items/{$item->id}"), ['variants' => [
        ['id' => $foreign->id, 'name' => ['en' => 'A'], 'price_minor' => 1],
    ]], idem())->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');
});

it('lets every team member mark items sold out', function (Role $role) {
    [$user, $tenant] = member($role);
    [, , $item] = sampleMenu($tenant);

    $this->actingAs($user)->patchJson(t($tenant, "items/{$item->id}/sold-out"), ['is_sold_out' => true], idem())
        ->assertOk()->assertJsonPath('data.is_sold_out', true);
})->with([Role::Owner, Role::Manager, Role::Staff]);

it('lets staff change prices only when allowed, and nothing else', function () {
    [$staff, $tenant] = member(Role::Staff);
    [$pricer] = member(Role::Staff, $tenant, canEditPrices: true);
    [, , $item] = sampleMenu($tenant);

    $this->actingAs($staff)->patchJson(t($tenant, "items/{$item->id}/price"), ['price_minor' => 1], idem())->assertForbidden();
    $this->actingAs($pricer)->patchJson(t($tenant, "items/{$item->id}/price"), ['price_minor' => 13500], idem())
        ->assertOk()->assertJsonPath('data.price_minor', 13500);
    $this->actingAs($pricer)->patchJson(t($tenant, "items/{$item->id}"), ['name' => ['en' => 'Renamed']], idem())->assertForbidden();
    $this->actingAs($pricer)->postJson(t($tenant, 'items/bulk-price'), ['ids' => [$item->id], 'percent' => 10], idem())->assertOk();
});

it('raises prices by a percentage, sizes included, with rounding', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant); // 120.00
    $variant = asTenant($tenant, fn () => $item->variants()->create(['name' => ['en' => 'L'], 'price_minor' => 15500]));
    asTenant($tenant, fn () => $item->forceFill(['price_minor' => 12000])->save());

    $this->actingAs($owner)->postJson(t($tenant, 'items/bulk-price'), [
        'ids' => [$item->id], 'percent' => 7.5, 'round_to' => 500,
    ], idem())->assertOk();

    asTenant($tenant, function () use ($item, $variant) {
        expect($item->fresh()->price_minor)->toBe(13000) // 129.00 → 130.00
            ->and($variant->fresh()->price_minor)->toBe(16500); // 166.63 → 165.00
    });
});

it('moves, hides and deletes many items at once', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu, , $a] = sampleMenu($tenant);
    $target = asTenant($tenant, fn () => Category::create(['menu_id' => $menu->id, 'name' => ['en' => 'Tea']]));

    $this->actingAs($owner)->postJson(t($tenant, 'items/bulk'), ['ids' => [$a->id], 'action' => 'move', 'category_id' => $target->id], idem())->assertNoContent();
    $this->postJson(t($tenant, 'items/bulk'), ['ids' => [$a->id], 'action' => 'hide'], idem())->assertNoContent();

    asTenant($tenant, fn () => expect($a->fresh()->category_id)->toBe($target->id)->and($a->fresh()->is_visible)->toBeFalse());

    $this->postJson(t($tenant, 'items/bulk'), ['ids' => [$a->id], 'action' => 'delete'], idem())->assertNoContent();
    expect(Item::withoutTenancy()->find($a->id))->toBeNull();
});

it('lets staff use bulk sold-out but not bulk hide', function () {
    [$staff, $tenant] = member(Role::Staff);
    [, , $item] = sampleMenu($tenant);

    $this->actingAs($staff)->postJson(t($tenant, 'items/bulk'), ['ids' => [$item->id], 'action' => 'sold_out'], idem())->assertNoContent();
    $this->postJson(t($tenant, 'items/bulk'), ['ids' => [$item->id], 'action' => 'hide'], idem())->assertForbidden();
});

it('duplicates an item right after the original', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category, $first] = sampleMenu($tenant);
    $second = asTenant($tenant, fn () => Item::create(['category_id' => $category->id, 'name' => ['en' => 'Tea'], 'sort' => 1]));

    $copyId = $this->actingAs($owner)->postJson(t($tenant, "items/{$first->id}/duplicate"), [], idem())
        ->assertCreated()->assertJsonPath('data.name.en', 'Macchiato (copy)')->json('data.id');

    expect(asTenant($tenant, fn () => Item::orderBy('sort')->pluck('id')->all()))->toBe([$first->id, $copyId, $second->id]);
});

it('reorders items within a category', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category, $a] = sampleMenu($tenant);
    $b = asTenant($tenant, fn () => Item::create(['category_id' => $category->id, 'name' => ['en' => 'Tea'], 'sort' => 1]));

    $this->actingAs($owner)->postJson(t($tenant, 'items/reorder'), ['category_id' => $category->id, 'ids' => [$b->id, $a->id]], idem())->assertNoContent();

    expect(asTenant($tenant, fn () => Item::orderBy('sort')->pluck('id')->all()))->toBe([$b->id, $a->id]);
});

it('stops new items at the plan limit', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);
    $plan = Plan::create(['name' => ['en' => 'Starter'], 'slug' => 'starter', 'limits' => ['items' => 1]]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();

    $this->actingAs($owner)->postJson(t($tenant, 'items'), ['category_id' => $category->id, 'name' => ['en' => 'Tea']], idem())
        ->assertStatus(402)->assertJsonPath('code', 'limits.items');
});

it('rejects unknown allergens and out-of-range prices', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);

    $this->actingAs($owner)->postJson(t($tenant, 'items'), [
        'category_id' => $category->id, 'name' => ['en' => 'Tea'], 'allergens' => ['pollen'], 'price_minor' => -1,
    ], idem())->assertUnprocessable()->assertJsonValidationErrors(['allergens.0', 'price_minor']);
});

it('pauses changes when the subscription is read-only', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    $tenant->forceFill(['trial_ends_at' => now()->subDays(10), 'paid_until' => null])->save();

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}/sold-out"), ['is_sold_out' => true], idem())->assertStatus(402);
    $this->getJson(t($tenant, "items/{$item->id}"))->assertOk();
});
