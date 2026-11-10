<?php

use App\Enums\Role;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\Tenant;

it('creates specials and announcements and shows them on the guest page', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);

    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), [
        'type' => 'special', 'title' => ['en' => 'Macchiato Monday'], 'body' => ['en' => 'Two for one'], 'item_id' => $item->id,
    ], idem())->assertCreated()->assertJsonPath('data.type', 'special');
    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), [
        'type' => 'announcement', 'title' => ['en' => 'Live music tonight'],
    ], idem())->assertCreated();

    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('Macchiato Monday')->assertSee('Two for one')
        ->assertSee('class="spc" data-i="'.$item->id.'"', false)
        ->assertSee('Live music tonight');
});

it('embeds the dates so the page shows a promotion only while it runs', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $now = now($tenant->timezone);

    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), [
        'type' => 'announcement', 'title' => ['en' => 'Coming soon'],
        'starts_at' => $now->copy()->addDay()->format('Y-m-d H:i'), 'ends_at' => $now->copy()->addDays(2)->format('Y-m-d H:i'),
    ], idem())->assertCreated();
    asTenant($tenant, fn () => Promotion::create(['type' => 'announcement', 'title' => ['en' => 'Already over'], 'ends_at' => $now->copy()->subHour()]));
    asTenant($tenant, fn () => Promotion::create(['type' => 'announcement', 'title' => ['en' => 'Switched off'], 'is_active' => false]));

    $html = $this->get('/'.$tenant->slug)->assertOk()->assertDontSee('Already over')->assertDontSee('Switched off')->getContent();
    expect($html)->toMatch('/<p class="an" data-w="\[&quot;[0-9: -]+&quot;,&quot;[0-9: -]+&quot;\]"\s+hidden\s*><b>Coming soon/');

    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), [
        'type' => 'special', 'title' => ['en' => 'X'], 'starts_at' => '2030-01-02 10:00', 'ends_at' => '2030-01-01 10:00',
    ], idem())->assertInvalid('ends_at');
});

it('limits a promotion to one branch', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $branchId = $this->actingAs($owner)->postJson(t($tenant, 'branches'), ['name' => ['en' => 'Piassa'], 'slug' => 'piassa'], idem())->json('data.id');

    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), ['type' => 'announcement', 'title' => ['en' => 'Piassa only'], 'branch_id' => $branchId], idem())->assertCreated();

    $this->get('/'.$tenant->slug.'/piassa')->assertSee('Piassa only');
    $this->get('/'.$tenant->slug)->assertDontSee('Piassa only');
});

it('updates, reorders and deletes, removing an unused photo', function () {
    [$owner, $tenant] = member(Role::Owner);
    $photo = asTenant($tenant, fn () => Media::create(['base_path' => $tenant->id.'/p', 'mime' => 'image/jpeg', 'width' => 10, 'height' => 10, 'bytes' => 1, 'variants' => ['640' => '640.webp']]));
    $a = $this->actingAs($owner)->postJson(t($tenant, 'promotions'), ['type' => 'special', 'title' => ['en' => 'A'], 'media_id' => $photo->id], idem())->json('data.id');
    $b = $this->actingAs($owner)->postJson(t($tenant, 'promotions'), ['type' => 'special', 'title' => ['en' => 'B']], idem())->json('data.id');

    $this->actingAs($owner)->postJson(t($tenant, 'promotions/reorder'), ['ids' => [$b, $a]], idem())->assertOk()->assertJsonPath('data.0.id', $b);
    $this->actingAs($owner)->patchJson(t($tenant, "promotions/{$a}"), ['is_active' => false], idem())->assertOk()->assertJsonPath('data.is_active', false);
    $this->actingAs($owner)->deleteJson(t($tenant, "promotions/{$a}"), [], idem())->assertNoContent();

    expect(asTenant($tenant, fn () => Media::find($photo->id)))->toBeNull();
});

it('keeps promotions to owners and managers of the restaurant', function () {
    [$staff, $tenant] = member(Role::Staff);
    [$owner] = member(Role::Owner);
    $other = Tenant::factory()->create();
    $foreign = asTenant($other, fn () => Promotion::create(['type' => 'special', 'title' => ['en' => 'Theirs']]));
    [, , $theirItem] = sampleMenu($other);
    $own = $owner->memberships()->first()->tenant;

    $this->actingAs($staff)->getJson(t($tenant, 'promotions'))->assertForbidden();
    $this->actingAs($owner)->patchJson(t($own, "promotions/{$foreign->id}"), ['title' => ['en' => 'Mine']], idem())->assertNotFound();
    $this->actingAs($owner)->postJson(t($own, 'promotions'), ['type' => 'special', 'title' => ['en' => 'X'], 'item_id' => $theirItem->id], idem())->assertInvalid('item_id');
});
