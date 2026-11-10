<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;

function signUp(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/register', array_merge([
        'name' => 'Selam Tesfaye',
        'email' => 'selam@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'restaurant_name' => 'Buna & Bread',
        'slug' => 'buna-and-bread',
    ], $overrides), idem());
}

it('creates the owner, the restaurant on a trial and the owner membership', function () {
    signUp()->assertCreated();

    $user = User::where('email', 'selam@example.com')->firstOrFail();
    $tenant = Tenant::where('slug', 'buna-and-bread')->firstOrFail();
    $membership = Membership::where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail();

    expect($membership->role)->toBe(Role::Owner)
        ->and($tenant->currency_code)->toBe('ETB')
        ->and($tenant->trial_ends_at->isFuture())->toBeTrue()
        ->and(ActivityLog::where('action', 'tenant.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('rejects reserved and badly formed slugs', function (string $slug) {
    signUp(['slug' => $slug])->assertUnprocessable()->assertJsonValidationErrors('slug');
})->with(['admin', 'api', 'q', 'Has-Caps', 'a', '-start', 'end-', 'double--dash']);

it('rejects a slug another restaurant uses or used before', function () {
    $tenant = Tenant::factory()->create(['slug' => 'taken-slug']);
    $tenant->slugHistory()->create(['old_slug' => 'old-taken']);

    signUp(['slug' => 'taken-slug'])->assertJsonValidationErrors('slug');
    signUp(['slug' => 'old-taken', 'email' => 'other@example.com'])->assertJsonValidationErrors('slug');
});

it('creates nothing when validation fails', function () {
    signUp(['password_confirmation' => 'different'])->assertUnprocessable();

    expect(User::count())->toBe(0)->and(Tenant::count())->toBe(0);
});
