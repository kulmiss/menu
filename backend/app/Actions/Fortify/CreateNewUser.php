<?php

namespace App\Actions\Fortify;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Fortify registration, now only for invitations: with an `invitation_token`
 * (sign-up from an invitation email) it creates the user and joins the
 * inviting restaurant — no new restaurant. The email must be the invited one,
 * so it counts as verified. New restaurants use App\Support\SignUp\SignUp.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        // A new restaurant signs up through POST /api/v1/signup (owner, 2026-11-13:
        // e-mail check, Free plan, anti-spam); this route only joins invitations.
        abort_unless(filled($input['invitation_token'] ?? null), 404);

        return $this->createFromInvitation($input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createFromInvitation(array $input): User
    {
        $invitation = Invitation::withoutTenancy()
            ->whereHas('tenant')
            ->where('token_hash', Invitation::hashToken((string) $input['invitation_token']))
            ->first();

        if ($invitation === null || ! $invitation->isOpen()) {
            throw ValidationException::withMessages(['invitation_token' => __('menu.invitation.invalid')]);
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules((string) ($input['email'] ?? '')),
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ])->validate();

        if (Str::lower($data['email']) !== $invitation->email) {
            throw ValidationException::withMessages(['email' => __('menu.invitation.wrong_account')]);
        }

        return DB::transaction(function () use ($data, $invitation) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
            ]);
            // The invitation link reached this inbox, so the address is proven.
            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::create([
                'tenant_id' => $invitation->tenant_id,
                'user_id' => $user->getKey(),
                'role' => $invitation->role,
                'can_edit_prices' => $invitation->can_edit_prices,
                'invited_by' => $invitation->invited_by,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            $this->activity->log('invitation.accepted', $invitation, ['user_id' => $user->id], $invitation->tenant_id);

            return $user;
        });
    }
}
