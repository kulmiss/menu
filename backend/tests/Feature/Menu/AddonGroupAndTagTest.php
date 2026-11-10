<?php

use App\Enums\Role;
use App\Models\AddonGroup;
use App\Models\Tag;

it('creates a group with options and edits them in one save', function () {
    [$owner, $tenant] = member(Role::Owner);

    $res = $this->actingAs($owner)->postJson(t($tenant, 'addon-groups'), [
        'name' => ['en' => 'Extra toppings'],
        'min_choices' => 0,
        'max_choices' => 2,
        'options' => [
            ['name' => ['en' => 'Cheese'], 'price_minor' => 2000],
            ['name' => ['en' => 'Olives'], 'price_minor' => 1500],
        ],
    ], idem())->assertCreated()->assertJsonCount(2, 'data.options');

    [$cheese] = $res->json('data.options');
    $this->patchJson(t($tenant, 'addon-groups/'.$res->json('data.id')), [
        'options' => [['id' => $cheese['id'], 'name' => ['en' => 'Cheese'], 'price_minor' => 2500]],
    ], idem())->assertOk()
        ->assertJsonCount(1, 'data.options')
        ->assertJsonPath('data.options.0.price_minor', 2500);
});

it('needs max choices at least min choices', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->postJson(t($tenant, 'addon-groups'), [
        'name' => ['en' => 'Sauce'], 'min_choices' => 2, 'max_choices' => 1, 'options' => [],
    ], idem())->assertUnprocessable()->assertJsonValidationErrors('max_choices');
});

it('lets staff mark an option sold out', function () {
    [$staff, $tenant] = member(Role::Staff);
    $option = asTenant($tenant, fn () => AddonGroup::create(['name' => ['en' => 'Milk']])->options()->create(['name' => ['en' => 'Oat']]));

    $this->actingAs($staff)->patchJson(t($tenant, "addon-options/{$option->id}/sold-out"), ['is_sold_out' => true], idem())
        ->assertOk()->assertJsonPath('data.is_sold_out', true);
    $this->postJson(t($tenant, 'addon-groups'), ['name' => ['en' => 'X'], 'options' => []], idem())->assertForbidden();
});

it('gives every restaurant the built-in tags, which cannot be changed', function () {
    [$owner, $tenant] = member(Role::Owner);

    $tags = $this->actingAs($owner)->getJson(t($tenant, 'tags'))->assertOk()->json('data');
    expect(collect($tags)->pluck('key')->all())->toBe(config('menu.system_tags'));

    $this->patchJson(t($tenant, 'tags/'.$tags[0]['id']), ['name' => ['en' => 'Plant based']], idem())
        ->assertUnprocessable();
    $this->deleteJson(t($tenant, 'tags/'.$tags[0]['id']), [], idem())->assertUnprocessable();
});

it('adds, renames and removes the restaurant\'s own tags', function () {
    [$owner, $tenant] = member(Role::Owner);

    $id = $this->actingAs($owner)->postJson(t($tenant, 'tags'), ['name' => ['en' => 'Chef\'s pick']], idem())
        ->assertCreated()->assertJsonPath('data.is_system', false)->json('data.id');
    $this->patchJson(t($tenant, "tags/{$id}"), ['name' => ['en' => 'House special']], idem())->assertOk();
    $this->deleteJson(t($tenant, "tags/{$id}"), [], idem())->assertNoContent();

    expect(Tag::withoutTenancy()->find($id))->toBeNull();
});
