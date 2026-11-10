<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Headers for a create/change request: a fresh Idempotency-Key. */
function idem(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid(), 'Accept' => 'application/json'];
}

/**
 * A user with the given role in a (new or given) restaurant.
 *
 * @return array{0: User, 1: Tenant, 2: Membership}
 */
function member(Role $role = Role::Owner, ?Tenant $tenant = null, bool $canEditPrices = false): array
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->for($tenant)->for($user)->create([
        'role' => $role,
        'can_edit_prices' => $canEditPrices,
    ]);

    return [$user, $tenant, $membership];
}
