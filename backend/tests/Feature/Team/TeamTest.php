<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Membership;

it('lets owners and managers see the team, but not staff', function (Role $role, int $status) {
    [$user, $tenant] = member($role);

    $this->actingAs($user)->getJson("/api/v1/t/{$tenant->id}/members")->assertStatus($status);
})->with([
    [Role::Owner, 200],
    [Role::Manager, 200],
    [Role::Staff, 403],
]);

it('lets the owner promote staff to manager and logs it', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $staff] = member(Role::Staff, $tenant);

    $this->actingAs($owner)
        ->patchJson("/api/v1/t/{$tenant->id}/members/{$staff->id}", ['role' => 'manager'], idem())
        ->assertOk()
        ->assertJsonPath('data.role', 'manager');

    expect(ActivityLog::where('action', 'member.updated')->where('subject_id', $staff->id)->exists())->toBeTrue();
});

it('does not let a manager touch other managers or promote anyone to manager', function () {
    [$manager, $tenant] = member(Role::Manager);
    [, , $otherManager] = member(Role::Manager, $tenant);
    [, , $staff] = member(Role::Staff, $tenant);

    $this->actingAs($manager)->patchJson("/api/v1/t/{$tenant->id}/members/{$otherManager->id}", ['role' => 'staff'], idem())->assertForbidden();
    $this->actingAs($manager)->patchJson("/api/v1/t/{$tenant->id}/members/{$staff->id}", ['role' => 'manager'], idem())->assertForbidden();
    $this->actingAs($manager)->patchJson("/api/v1/t/{$tenant->id}/members/{$staff->id}", ['can_edit_prices' => true], idem())->assertOk();
});

it('does not let staff manage anyone', function () {
    [$staff, $tenant] = member(Role::Staff);
    [, , $other] = member(Role::Staff, $tenant);

    $this->actingAs($staff)->patchJson("/api/v1/t/{$tenant->id}/members/{$other->id}", ['can_edit_prices' => true], idem())->assertForbidden();
    $this->actingAs($staff)->deleteJson("/api/v1/t/{$tenant->id}/members/{$other->id}", [], idem())->assertForbidden();
});

it('stops people changing their own role and the owner leaving', function () {
    [$owner, $tenant, $ownerMembership] = member(Role::Owner);

    $this->actingAs($owner)->patchJson("/api/v1/t/{$tenant->id}/members/{$ownerMembership->id}", ['role' => 'staff'], idem())->assertUnprocessable();
    $this->actingAs($owner)->deleteJson("/api/v1/t/{$tenant->id}/members/{$ownerMembership->id}", [], idem())->assertUnprocessable();
});

it('lets staff leave a restaurant themselves', function () {
    [$staff, $tenant, $membership] = member(Role::Staff);

    $this->actingAs($staff)->deleteJson("/api/v1/t/{$tenant->id}/members/{$membership->id}", [], idem())->assertNoContent();

    expect(Membership::find($membership->id))->toBeNull();
});

it('gives staff edit-price rights only when the flag is set', function () {
    [, , $plain] = member(Role::Staff);
    [, , $priced] = member(Role::Staff, canEditPrices: true);
    [, , $manager] = member(Role::Manager);

    expect($plain->canEditPrices())->toBeFalse()
        ->and($priced->canEditPrices())->toBeTrue()
        ->and($manager->canEditPrices())->toBeTrue();
});
