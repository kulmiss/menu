<?php

use App\Enums\Role;
use App\Models\Tenant;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('keeps reads working but blocks changes once the restaurant is read-only', function () {
    $tenant = Tenant::factory()->expired(10)->create(); // past the 7-day grace
    [$owner] = member(Role::Owner, $tenant);

    $this->actingAs($owner)->getJson("/api/v1/t/{$tenant->id}")
        ->assertOk()
        ->assertJsonPath('data.subscription.state', 'read_only');

    $this->actingAs($owner)
        ->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'], idem())
        ->assertStatus(402)
        ->assertJsonPath('code', 'subscription.read_only');
});

it('still allows changes during the grace period', function () {
    $tenant = Tenant::factory()->expired(3)->create();
    [$owner] = member(Role::Owner, $tenant);

    $this->actingAs($owner)
        ->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'], idem())
        ->assertCreated();
});
