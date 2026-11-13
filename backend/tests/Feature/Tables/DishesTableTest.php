<?php

use App\Enums\Role;
use App\Models\Item;

it('lists every dish with search, filters, sort and pages; CSV for owners and managers only', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    [$menu, $category, $macchiato] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        foreach (range(1, 40) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => sprintf('Dish %02d', $n)], 'price_minor' => $n * 1000, 'is_sold_out' => $n % 10 === 0, 'sort' => $n]);
        }
    });

    $res = $this->actingAs($staff)->getJson(t($tenant, 'items?page=1&per=10&sort=-price'))->assertOk();
    expect($res->json('meta.total'))->toBe(41)
        ->and($res->json('data.0.name.en'))->toBe('Dish 40')
        ->and($res->json('data.0.menu.id'))->toBe($menu->id);
    expect($this->actingAs($staff)->getJson(t($tenant, 'items?page=1&status=sold_out'))->json('meta.total'))->toBe(4);
    expect($this->actingAs($staff)->getJson(t($tenant, 'items?page=1&q=Dish%203'))->json('meta.total'))->toBe(10);
    expect($this->actingAs($staff)->getJson(t($tenant, "items?page=1&menu={$menu->id}&status=no_photo"))->json('meta.total'))->toBe(41);
    expect($this->actingAs($staff)->getJson(t($tenant, 'items?page=1&sort=name&per=10'))->json('data.0.name.en'))->toBe('Dish 01');

    $this->actingAs($staff)->get(t($tenant, 'items?format=csv'))->assertForbidden();
    $csv = $this->actingAs($owner)->get(t($tenant, 'items?format=csv&status=sold_out'))->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(5)->and($csv)->toContain('Dish 10');

    // Another restaurant sees none of these.
    [$other, $otherTenant] = member(Role::Owner);
    expect($this->actingAs($other)->getJson(t($otherTenant, 'items?page=1'))->json('meta.total'))->toBe(0);
});
