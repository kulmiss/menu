<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\Menu;
use App\Models\Plan;

it('creates a menu with a unique address and logs it', function () {
    [$owner, $tenant] = member(Role::Owner);

    $first = $this->actingAs($owner)->postJson(t($tenant, 'menus'), ['name' => ['en' => 'Breakfast', 'am' => 'ቁርስ']], idem())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'breakfast')
        ->assertJsonPath('data.name.am', 'ቁርስ');
    $this->postJson(t($tenant, 'menus'), ['name' => ['en' => 'Breakfast']], idem())
        ->assertCreated()->assertJsonPath('data.slug', 'breakfast-2');
    $this->postJson(t($tenant, 'menus'), ['name' => ['en' => 'ምሳ']], idem())
        ->assertCreated()->assertJsonPath('data.slug', 'menu');

    expect(ActivityLog::where('action', 'menu.created')->count())->toBe(3)
        ->and($tenant->fresh()->cache_version)->toBeGreaterThan($tenant->cache_version);
});

it('needs the name in the main language', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->postJson(t($tenant, 'menus'), ['name' => ['am' => 'ቁርስ']], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(t($tenant, 'menus'), ['name' => 'Breakfast'], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('lets owners and managers manage menus, not staff', function (Role $role, int $status) {
    [$user, $tenant] = member($role);

    $this->actingAs($user)->postJson(t($tenant, 'menus'), ['name' => ['en' => 'Drinks']], idem())->assertStatus($status);
})->with([
    'owner' => [Role::Owner, 201],
    'manager' => [Role::Manager, 201],
    'staff' => [Role::Staff, 403],
]);

it('lets staff read menus (for sold-out)', function () {
    [$staff, $tenant] = member(Role::Staff);
    [$menu] = sampleMenu($tenant);

    $this->actingAs($staff)->getJson(t($tenant, 'menus'))->assertOk()
        ->assertJsonPath('data.0.id', $menu->id)
        ->assertJsonPath('data.0.categories_count', 1)
        ->assertJsonPath('data.0.items_count', 1);
    $this->getJson(t($tenant, "menus/{$menu->id}/tree"))->assertOk()
        ->assertJsonPath('data.categories.0.items.0.name.en', 'Macchiato')
        ->assertJsonPath('data.categories.0.items.0.from_price_minor', 12000);
});

it('stops at the plan limit with 402 and keeps existing menus', function () {
    [$owner, $tenant] = member(Role::Owner);
    $plan = Plan::create(['name' => ['en' => 'Starter'], 'slug' => 'starter', 'limits' => ['menus' => 1]]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    sampleMenu($tenant);

    $this->actingAs($owner)->postJson(t($tenant, 'menus'), ['name' => ['en' => 'Drinks']], idem())
        ->assertStatus(402)->assertJsonPath('code', 'limits.menus');

    expect(asTenant($tenant, fn () => Menu::count()))->toBe(1);
});

it('reorders with the full list only', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$a] = sampleMenu($tenant);
    [$b] = sampleMenu($tenant);

    $this->actingAs($owner)->postJson(t($tenant, 'menus/reorder'), ['ids' => [$b->id, $a->id]], idem())->assertNoContent();
    expect(asTenant($tenant, fn () => Menu::orderBy('sort')->pluck('id')->all()))->toBe([$b->id, $a->id]);

    $this->postJson(t($tenant, 'menus/reorder'), ['ids' => [$a->id]], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('ids');
});

it('duplicates a menu with everything inside', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu, , $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($item) {
        $item->variants()->create(['name' => ['en' => 'Large'], 'price_minor' => 15000]);
        ItemAllergen::create(['item_id' => $item->id, 'allergen' => 'milk']);
    });

    $copyId = $this->actingAs($owner)->postJson(t($tenant, "menus/{$menu->id}/duplicate"), [], idem())
        ->assertCreated()
        ->assertJsonPath('data.name.en', 'All day (copy)')
        ->json('data.id');

    asTenant($tenant, function () use ($copyId, $item) {
        $copiedItem = Item::whereHas('category', fn ($q) => $q->where('menu_id', $copyId))->with('variants', 'allergenRows')->sole();
        expect($copiedItem->id)->not->toBe($item->id)
            ->and($copiedItem->variants->pluck('price_minor')->all())->toBe([15000])
            ->and($copiedItem->allergens())->toBe(['milk']);
    });
});

it('saves weekly time windows, including past midnight', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu] = sampleMenu($tenant);

    $this->actingAs($owner)->putJson(t($tenant, "menus/{$menu->id}/schedules"), [
        'schedule_mode' => 'scheduled',
        'schedules' => [
            ['weekday' => 5, 'starts_at' => '22:00', 'ends_at' => '02:00'],
            ['weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '11:00'],
        ],
    ], idem())->assertOk()
        ->assertJsonPath('data.schedule_mode', 'scheduled')
        ->assertJsonPath('data.schedules.0', ['weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '11:00'])
        ->assertJsonPath('data.schedules.1.ends_at', '02:00');

    $this->putJson(t($tenant, "menus/{$menu->id}/schedules"), [
        'schedule_mode' => 'scheduled',
        'schedules' => [['weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '07:00']],
    ], idem())->assertUnprocessable()->assertJsonValidationErrors('schedules.0.ends_at');
});

it('deletes a menu with its categories and items', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu] = sampleMenu($tenant);

    $this->actingAs($owner)->deleteJson(t($tenant, "menus/{$menu->id}"), [], idem())->assertNoContent();

    expect(Category::withoutTenancy()->count())->toBe(0)
        ->and(Item::withoutTenancy()->count())->toBe(0);
});
