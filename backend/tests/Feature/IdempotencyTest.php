<?php

use App\Enums\Role;
use App\Models\IdempotencyKey;
use App\Models\Invitation;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('refuses a change request without an Idempotency-Key', function () {
    [$user, $tenant] = member(Role::Owner);

    $this->actingAs($user)
        ->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'])
        ->assertStatus(428)
        ->assertJsonPath('code', 'idempotency.missing');
});

it('replays the first response for a repeated request and does the work once', function () {
    [$user, $tenant] = member(Role::Owner);
    $headers = idem('double-tap-key-123');
    $payload = ['email' => 'dawit@example.com', 'role' => 'staff'];

    $first = $this->actingAs($user)->postJson("/api/v1/t/{$tenant->id}/invitations", $payload, $headers)->assertCreated();
    $second = $this->actingAs($user)->postJson("/api/v1/t/{$tenant->id}/invitations", $payload, $headers)->assertCreated();

    expect($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Invitation::withoutTenancy()->count())->toBe(1);
});

it('rejects the same key with a different request body', function () {
    [$user, $tenant] = member(Role::Owner);
    $headers = idem('reused-key-4567');

    $this->actingAs($user)->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'], $headers)->assertCreated();
    $this->actingAs($user)->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'b@example.com', 'role' => 'staff'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency.mismatch');
});

it('keeps keys separate per user', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$otherOwner, $otherTenant] = member(Role::Owner);
    $headers = idem('shared-key-0001');

    $this->actingAs($owner)->postJson("/api/v1/t/{$tenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'], $headers)->assertCreated();
    $this->actingAs($otherOwner)->postJson("/api/v1/t/{$otherTenant->id}/invitations", ['email' => 'a@example.com', 'role' => 'staff'], $headers)->assertCreated();

    expect(IdempotencyKey::count())->toBe(2);
});

it('does not require a key for reads', function () {
    [$user, $tenant] = member(Role::Owner);

    $this->actingAs($user)->getJson("/api/v1/t/{$tenant->id}")->assertOk();
});
