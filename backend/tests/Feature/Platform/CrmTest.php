<?php

use App\Enums\Role;
use App\Models\PlatformTag;
use App\Models\RestaurantNote;

/** Super Admin CRM: tags, notes, follow-ups (owner, 2026-11-13). */
it('creates, renames, colours and assigns tags, and filters the list by tag', function () {
    $this->actingAs(superAdmin());
    [, $a] = member(Role::Owner);
    [, $b] = member(Role::Owner);

    $vip = $this->postJson(p('tags'), ['name' => 'VIP', 'color' => '#DC2626'], idem())->assertCreated()->json('data');
    $risk = $this->postJson(p('tags'), ['name' => 'At risk'], idem())->assertCreated()->json('data');
    $this->postJson(p('tags'), ['name' => 'VIP'], idem())->assertUnprocessable();
    $this->postJson(p('tags'), ['name' => 'X', 'color' => 'red'], idem())->assertUnprocessable();
    $this->patchJson(p("tags/{$vip['id']}"), ['name' => 'Key account'], idem())->assertOk()->assertJsonPath('data.color', '#dc2626');

    $this->putJson(p("restaurants/{$a->id}/tags"), ['tag_ids' => [$vip['id'], $risk['id']]], idem())->assertOk()->assertJsonCount(2, 'data');
    $this->putJson(p("restaurants/{$b->id}/tags"), ['tag_ids' => [$risk['id']]], idem())->assertOk();

    $this->getJson(p("restaurants?tag_id={$vip['id']}"))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id)
        ->assertJsonPath('data.0.tags.0.name', 'At risk')->assertJsonPath('data.0.tags.1.name', 'Key account');
    $this->getJson(p("restaurants?tag_id={$risk['id']}"))->assertJsonCount(2, 'data');
    $this->getJson(p('tags'))->assertJsonPath('data.0.name', 'At risk')->assertJsonPath('data.0.restaurants', 2);

    $this->deleteJson(p("tags/{$risk['id']}"), [], idem())->assertNoContent();
    expect($a->platformTags()->count())->toBe(1);
});

it('keeps a notes timeline where each Super Admin edits and deletes only their own notes', function () {
    $me = superAdmin();
    $colleague = superAdmin();
    [, $tenant] = member(Role::Owner);
    $theirs = RestaurantNote::create(['tenant_id' => $tenant->id, 'author_id' => $colleague->id, 'body' => 'Called, no answer.']);

    $this->actingAs($me);
    $mine = $this->postJson(p("restaurants/{$tenant->id}/notes"), ['body' => '  Wants the Pro plan in January.  '], idem())->assertCreated()->json('data');
    expect($mine['body'])->toBe('Wants the Pro plan in January.')->and($mine['mine'])->toBeTrue();

    $this->getJson(p("restaurants/{$tenant->id}/notes"))->assertJsonCount(2, 'data')->assertJsonPath('data.1.mine', false);
    $this->patchJson(p("notes/{$mine['id']}"), ['body' => 'Wants Pro in February.'], idem())->assertOk()->assertJsonPath('data.body', 'Wants Pro in February.');
    $this->patchJson(p("notes/{$theirs->id}"), ['body' => 'Changed'], idem())->assertForbidden();
    $this->deleteJson(p("notes/{$theirs->id}"), [], idem())->assertForbidden();
    $this->deleteJson(p("notes/{$mine['id']}"), [], idem())->assertNoContent();
    expect(RestaurantNote::pluck('body')->all())->toBe(['Called, no answer.']);
});

it('sets follow-ups, filters "due" and lists today and overdue ones for the dashboard', function () {
    $this->actingAs(superAdmin());
    [, $today] = member(Role::Owner);
    [, $late] = member(Role::Owner);
    [, $later] = member(Role::Owner);

    $this->putJson(p("restaurants/{$today->id}/follow-up"), ['date' => now()->toDateString(), 'reason' => 'Renewal call'], idem())->assertOk();
    $this->putJson(p("restaurants/{$late->id}/follow-up"), ['date' => now()->subDays(3)->toDateString()], idem())->assertOk();
    $this->putJson(p("restaurants/{$later->id}/follow-up"), ['date' => now()->addWeek()->toDateString(), 'reason' => 'x'], idem())->assertOk();

    $this->getJson(p('restaurants?follow_up=due'))->assertJsonCount(2, 'data');
    $this->getJson(p('widgets/follow-ups'))->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $late->id)->assertJsonPath('data.0.overdue', true)
        ->assertJsonPath('data.1.reason', 'Renewal call')->assertJsonPath('data.1.overdue', false);

    $this->putJson(p("restaurants/{$today->id}/follow-up"), ['date' => null, 'reason' => 'ignored'], idem())->assertOk()
        ->assertJsonPath('data.follow_up_on', null)->assertJsonPath('data.follow_up_reason', null);
});

it('is never visible to restaurants', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tag = PlatformTag::create(['name' => 'Hot lead']);
    $tenant->platformTags()->attach($tag);
    RestaurantNote::create(['tenant_id' => $tenant->id, 'body' => 'Secret']);
    $tenant->forceFill(['follow_up_on' => now(), 'follow_up_reason' => 'Upsell'])->save();

    $this->actingAs($owner);
    foreach (['tags', "restaurants/{$tenant->id}/notes", 'widgets/follow-ups'] as $path) {
        $this->getJson(p($path))->assertNotFound();
    }
    $json = json_encode([$this->getJson('/api/v1/me')->json(), $this->getJson(t($tenant))->json()]);
    expect($json)->not->toContain('Hot lead')->not->toContain('Upsell')->not->toContain('follow_up');
});
