<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Notification::fake());

/** The plain token from the invitation email sent to this address. */
function sentInvitationToken(string $email): string
{
    $token = null;
    Notification::assertSentOnDemand(InvitationNotification::class, function ($notification, $channels, $notifiable) use ($email, &$token) {
        if ($notifiable->routes['mail'] !== $email) {
            return false;
        }
        $token = $notification->token;

        return true;
    });

    return $token;
}

function invite(User $by, int $tenantId, array $data): TestResponse
{
    return test()->actingAs($by)->postJson("/api/v1/t/{$tenantId}/invitations", $data, idem());
}

it('emails an invitation with a link and stores only the token hash', function () {
    [$owner, $tenant] = member(Role::Owner);

    invite($owner, $tenant->id, ['email' => 'Dawit@Example.com', 'role' => 'manager'])->assertCreated();

    $sentToken = sentInvitationToken('dawit@example.com');

    $stored = Invitation::withoutTenancy()->sole();
    expect($stored->token_hash)->toBe(Invitation::hashToken($sentToken))
        ->and($stored->email)->toBe('dawit@example.com');
});

it('only lets people invite roles they can manage', function (Role $inviter, string $role, int $status) {
    [$user, $tenant] = member($inviter);

    invite($user, $tenant->id, ['email' => 'new@example.com', 'role' => $role])->assertStatus($status);
})->with([
    'owner invites manager' => [Role::Owner, 'manager', 201],
    'owner cannot invite owner' => [Role::Owner, 'owner', 403],
    'manager invites staff' => [Role::Manager, 'staff', 201],
    'manager cannot invite manager' => [Role::Manager, 'manager', 403],
    'staff cannot invite' => [Role::Staff, 'staff', 403],
]);

it('refuses to invite someone who is already a member', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);

    invite($owner, $tenant->id, ['email' => $staff->email, 'role' => 'staff'])->assertJsonValidationErrors('email');
});

it('accepts an invitation for the invited email address', function () {
    [$owner, $tenant] = member(Role::Owner);
    invite($owner, $tenant->id, ['email' => 'hana@example.com', 'role' => 'staff', 'can_edit_prices' => true])->assertCreated();
    $token = sentInvitationToken('hana@example.com');

    $this->getJson("/api/v1/invitations/{$token}")->assertOk()->assertJsonPath('data.restaurant', $tenant->name);

    $hana = User::factory()->create(['email' => 'hana@example.com']);
    $this->actingAs($hana)->postJson("/api/v1/invitations/{$token}/accept", [], idem())
        ->assertOk()
        ->assertJsonPath('data.membership.role', 'staff')
        ->assertJsonPath('data.membership.can_edit_prices', true);

    expect(Membership::where('tenant_id', $tenant->id)->where('user_id', $hana->id)->exists())->toBeTrue();

    // Used once only.
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

it('refuses an invitation opened by a different account', function () {
    [$owner, $tenant] = member(Role::Owner);
    invite($owner, $tenant->id, ['email' => 'hana@example.com', 'role' => 'staff'])->assertCreated();
    $token = sentInvitationToken('hana@example.com');

    $this->actingAs(User::factory()->create(['email' => 'mallory@example.com']))
        ->postJson("/api/v1/invitations/{$token}/accept", [], idem())
        ->assertForbidden();
});

it('treats expired invitations as gone', function () {
    [, $tenant] = member(Role::Owner);
    app(CurrentTenant::class)->runAs($tenant, fn () => Invitation::create([
        'email' => 'late@example.com', 'role' => Role::Staff,
        'token_hash' => Invitation::hashToken('expired-token'), 'expires_at' => now()->subMinute(),
    ]));

    $this->getJson('/api/v1/invitations/expired-token')->assertNotFound();
});

it('links the invitation email to the SPA invitation page', function () {
    [$owner, $tenant] = member(Role::Owner);

    invite($owner, $tenant->id, ['email' => 'link@example.com', 'role' => 'staff'])->assertCreated();
    $token = sentInvitationToken('link@example.com');

    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $n) use ($token) {
        return $n->toMail(new stdClass)->actionUrl === url('/auth/invite/'.$token);
    });
});
