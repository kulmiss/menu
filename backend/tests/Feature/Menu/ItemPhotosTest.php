<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Media;
use App\Models\Tenant;

/** A photo row of the restaurant (files are not needed here). */
function dishPhoto(Tenant $tenant): Media
{
    return asTenant($tenant, fn () => Media::create([
        'base_path' => $tenant->id.'/p'.random_int(1, 999999), 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 853, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
    ]));
}

it('creates a dish with several photos, the first being the main one', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);
    [$a, $b, $c] = [dishPhoto($tenant), dishPhoto($tenant), dishPhoto($tenant)];

    $this->actingAs($owner)->postJson(t($tenant, 'items'), [
        'category_id' => $category->id, 'name' => ['en' => 'Shiro'], 'price_minor' => 18000,
        'photo_ids' => [$b->id, $a->id, $c->id],
    ], idem())->assertCreated()
        ->assertJsonPath('data.media_id', $b->id)
        ->assertJsonPath('data.photos.*.id', [$b->id, $a->id, $c->id]);
});

it('reorders and removes photos, deleting the ones nothing uses any more', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [$a, $b, $c] = [dishPhoto($tenant), dishPhoto($tenant), dishPhoto($tenant)];
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$a->id, $b->id, $c->id]], idem())->assertOk();

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$c->id, $a->id]], idem())->assertOk()
        ->assertJsonPath('data.media_id', $c->id)
        ->assertJsonPath('data.photos.*.id', [$c->id, $a->id]);
    expect(asTenant($tenant, fn () => Media::find($b->id)))->toBeNull();

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => []], idem())->assertOk()
        ->assertJsonPath('data.media_id', null)
        ->assertJsonPath('data.photos', []);
    expect(asTenant($tenant, fn () => Media::count()))->toBe(0);
});

it('allows at most 5 photos per dish and only the restaurant\'s own', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    $six = collect(range(1, 6))->map(fn () => dishPhoto($tenant)->id)->all();
    $foreign = dishPhoto(Tenant::factory()->create());

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => $six], idem())->assertInvalid('photo_ids');
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$foreign->id]], idem())->assertInvalid('photo_ids.0');
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$six[0], $six[0]]], idem())->assertInvalid('photo_ids.0');
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => array_slice($six, 0, 5)], idem())->assertOk()
        ->assertJsonCount(5, 'data.photos');
});

it('keeps the other photos when an older client changes only the main one', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [$a, $b, $new] = [dishPhoto($tenant), dishPhoto($tenant), dishPhoto($tenant)];
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$a->id, $b->id]], idem())->assertOk();

    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['media_id' => $new->id], idem())->assertOk()
        ->assertJsonPath('data.photos.*.id', [$new->id, $b->id]);
    expect(asTenant($tenant, fn () => Media::find($a->id)))->toBeNull();
});

it('copies photos with a duplicated dish and deletes them with the last dish using them', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [$a, $b] = [dishPhoto($tenant), dishPhoto($tenant)];
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$a->id, $b->id]], idem())->assertOk();

    $copy = $this->actingAs($owner)->postJson(t($tenant, "items/{$item->id}/duplicate"), [], idem())->assertCreated()
        ->assertJsonPath('data.photos.*.id', [$a->id, $b->id])->json('data.id');

    $this->actingAs($owner)->deleteJson(t($tenant, "items/{$item->id}"), [], idem())->assertNoContent();
    expect(asTenant($tenant, fn () => Media::count()))->toBe(2);
    $this->actingAs($owner)->deleteJson(t($tenant, "items/{$copy}"), [], idem())->assertNoContent();
    expect(asTenant($tenant, fn () => Media::count()))->toBe(0);
});

it('puts the extra photos in the guest page for the swipe gallery', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    [$a, $b, $c] = [dishPhoto($tenant), dishPhoto($tenant), dishPhoto($tenant)];
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['photo_ids' => [$a->id, $b->id, $c->id]], idem())->assertOk();

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    preg_match('#<script type="application/json" id="d">(.*?)</script>#s', $html, $m);
    $sheet = json_decode($m[1], true)['c'][$item->id];

    expect($sheet['i'])->toEndWith($a->base_path.'/1280.webp')
        ->and($sheet['g'])->toHaveCount(2)
        ->and($sheet['g'][0])->toEndWith($b->base_path.'/1280.webp');
    expect(asTenant($tenant, fn () => Item::find($item->id)->photos()->count()))->toBe(3);
});
