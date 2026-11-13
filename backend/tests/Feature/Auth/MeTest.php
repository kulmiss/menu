<?php

use App\Enums\Role;

it('lists the restaurants the user belongs to with their role', function () {
    [$user, $tenant] = member(Role::Manager);
    member(Role::Owner); // someone else's restaurant

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(1, 'data.tenants')
        ->assertJsonPath('data.tenants.0.id', $tenant->id)
        ->assertJsonPath('data.tenants.0.membership.role', 'manager');
});

it('requires sign-in', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
