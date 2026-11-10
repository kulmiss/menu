<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;

/** An open invitation; returns [plain token, tenant]. */
function openInvitation(string $email = 'hana@example.com', Role $role = Role::Staff, bool $expired = false): array
{
    $tenant = Tenant::factory()->create();
    $token = 'invite-token-'.Str::random(20);

    $invitation = new Invitation([
        'email' => $email,
        'role' => $role,
        'can_edit_prices' => true,
        'token_hash' => Invitation::hashToken($token),
        'expires_at' => $expired ? now()->subDay() : now()->addDays(7),
    ]);
    $invitation->tenant_id = $tenant->id;
    $invitation->saveQuietly();

    return [$token, $tenant];
}

function invitedSignUp(string $token, array $overrides = [])
{
    return test()->postJson('/api/v1/auth/register', array_merge([
        'name' => 'Hana Girma',
        'email' => 'hana@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'invitation_token' => $token,
    ], $overrides), idem());
}

it('joins the inviting restaurant without creating a new one', function () {
    [$token, $tenant] = openInvitation();

    invitedSignUp($token)->assertCreated();

    $user = User::where('email', 'hana@example.com')->firstOrFail();
    $membership = Membership::where('user_id', $user->id)->sole();

    expect($membership->tenant_id)->toBe($tenant->id)
        ->and($membership->role)->toBe(Role::Staff)
        ->and($membership->can_edit_prices)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Tenant::count())->toBe(1)
        ->and(Invitation::withoutTenancy()->sole()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('only accepts the invited email address', function () {
    [$token] = openInvitation();

    invitedSignUp($token, ['email' => 'someone-else@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(User::count())->toBe(0);
});

it('refuses expired or unknown invitations', function () {
    [$token] = openInvitation(expired: true);

    invitedSignUp($token)->assertUnprocessable()->assertJsonValidationErrors('invitation_token');
    invitedSignUp('not-a-real-token')->assertUnprocessable()->assertJsonValidationErrors('invitation_token');

    expect(User::count())->toBe(0);
});
