<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Closure;
use App\Models\Tenant;

it('saves the profile and shows it in the guest page info section', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    $this->actingAs($owner)->putJson(t($tenant, 'profile'), [
        'description' => ['en' => 'Family café since 1998.'],
        'address' => ['en' => 'Bole Road, Addis Ababa'],
        'maps_url' => 'https://maps.app.goo.gl/abc',
        'phone' => '+251 11 555 0100',
        'whatsapp' => '+251 91 555 0100',
        'telegram' => '@bunabread',
        'socials' => ['instagram' => 'https://instagram.com/buna', 'facebook' => null],
        'wifi_name' => 'Buna-Guest',
        'wifi_password' => 'coffee123',
        'show_wifi' => true,
    ], idem())->assertOk()
        ->assertJsonPath('data.telegram', 'bunabread')
        ->assertJsonPath('data.socials', ['instagram' => 'https://instagram.com/buna']);

    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('Family café since 1998.')
        ->assertSee('Bole Road, Addis Ababa')
        ->assertSee('href="tel:+251115550100"', false)
        ->assertSee('href="https://wa.me/251915550100"', false)
        ->assertSee('href="https://t.me/bunabread"', false)
        ->assertSee('Instagram')
        ->assertSee('coffee123');
});

it('hides Wi-Fi unless switched on, and rejects bad contact details', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);

    $this->actingAs($owner)->putJson(t($tenant, 'profile'), ['wifi_name' => 'Buna', 'wifi_password' => 'secret-pass', 'show_wifi' => false], idem())->assertOk();
    $this->get('/'.$tenant->slug)->assertDontSee('secret-pass');

    $this->actingAs($owner)->putJson(t($tenant, 'profile'), [
        'maps_url' => 'http://not-https.example', 'telegram' => 'x', 'socials' => ['myspace' => 'https://myspace.com/a'], 'lat' => 9.0,
    ], idem())->assertInvalid(['maps_url', 'telegram', 'socials', 'lng']);
});

it('saves a week with breaks and past-midnight hours, and refuses overlaps', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $week = [
        ['weekday' => 1, 'opens_at' => '07:00', 'closes_at' => '11:30'],
        ['weekday' => 1, 'opens_at' => '13:00', 'closes_at' => '22:00'],
        ['weekday' => 6, 'opens_at' => '18:00', 'closes_at' => '02:00'],
    ];

    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['hours' => $week], idem())->assertOk()->assertJsonCount(3, 'data');

    $html = $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('07:00–11:30, 13:00–22:00')
        ->assertSee('18:00–02:00')
        ->getContent();
    expect($html)->toContain('"h":[[1,"07:00","11:30"],[1,"13:00","22:00"],[6,"18:00","02:00"]]');

    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['hours' => [
        ['weekday' => 2, 'opens_at' => '08:00', 'closes_at' => '12:00'],
        ['weekday' => 2, 'opens_at' => '11:00', 'closes_at' => '15:00'],
    ]], idem())->assertInvalid('hours.1.opens_at');
    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['hours' => [
        ['weekday' => 2, 'opens_at' => '25:00', 'closes_at' => '12:00'],
        ['weekday' => 3, 'opens_at' => '09:00', 'closes_at' => '09:00'],
    ]], idem())->assertInvalid(['hours.0.opens_at', 'hours.1.closes_at']);
});

it('lets a branch keep its own hours or fall back to the restaurant\'s', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $branchId = $this->actingAs($owner)->postJson(t($tenant, 'branches'), ['name' => ['en' => 'Piassa'], 'slug' => 'piassa'], idem())->json('data.id');
    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['hours' => [['weekday' => 1, 'opens_at' => '07:00', 'closes_at' => '22:00']]], idem())->assertOk();

    $this->get('/'.$tenant->slug.'/piassa')->assertSee('"h":[[1,"07:00","22:00"]]', false);

    $this->actingAs($owner)->putJson(t($tenant, 'hours'), ['branch_id' => $branchId, 'hours' => [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '18:00']]], idem())->assertOk();
    $this->actingAs($owner)->getJson(t($tenant, 'hours?branch_id='.$branchId))->assertJsonPath('data.0.opens_at', '10:00');
    $this->get('/'.$tenant->slug.'/piassa')->assertSee('"h":[[1,"10:00","18:00"]]', false);
    $this->get('/'.$tenant->slug)->assertSee('"h":[[1,"07:00","22:00"]]', false);
});

it('announces upcoming closures and embeds them for the open/closed pill', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $today = now($tenant->timezone)->toDateString();
    $soon = now($tenant->timezone)->addDays(3)->toDateString();

    $this->actingAs($owner)->postJson(t($tenant, 'closures'), ['starts_on' => $today, 'ends_on' => $soon, 'note' => ['en' => 'Timket holiday']], idem())
        ->assertCreated();
    $this->actingAs($owner)->postJson(t($tenant, 'closures'), ['starts_on' => $soon, 'ends_on' => $today], idem())->assertInvalid('ends_on');
    asTenant($tenant, fn () => Closure::create(['starts_on' => '2020-01-01', 'ends_on' => '2020-01-02']));

    $this->actingAs($owner)->getJson(t($tenant, 'closures'))->assertJsonCount(1, 'data');
    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('Timket holiday')
        ->assertSee('"x":[["'.$today.'","'.$soon.'"]]', false)
        ->assertDontSee('2020-01-01');
});

it('keeps info, hours and closures to owners and managers of the restaurant', function () {
    [$staff, $tenant] = member(Role::Staff);
    [$owner] = member(Role::Owner);
    $other = Tenant::factory()->create();
    $foreignBranch = asTenant($other, fn () => Branch::firstOrFail());
    $foreignClosure = asTenant($other, fn () => Closure::create(['starts_on' => '2030-01-01', 'ends_on' => '2030-01-01']));
    $own = $owner->memberships()->first()->tenant;

    $this->actingAs($staff)->getJson(t($tenant, 'profile'))->assertForbidden();
    $this->actingAs($staff)->putJson(t($tenant, 'hours'), ['hours' => []], idem())->assertForbidden();
    $this->actingAs($owner)->getJson(t($own, 'hours?branch_id='.$foreignBranch->id))->assertInvalid('branch_id');
    $this->actingAs($owner)->deleteJson(t($own, "closures/{$foreignClosure->id}"), [], idem())->assertNotFound();
});
