<?php

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\ValidSlug;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Self-service sign-up, all or nothing:
 * - normally creates the owner, their restaurant (tenant) on a trial and the
 *   owner membership;
 * - with an `invitation_token` (sign-up from an invitation email) creates the
 *   user and joins the inviting restaurant instead — no new restaurant. The
 *   email must be the invited one, so it counts as verified.
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
        if (filled($input['invitation_token'] ?? null)) {
            return $this->createFromInvitation($input);
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'restaurant_name' => ['required', 'string', 'max:120'],
            'slug' => ['required', new ValidSlug],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
            ]);

            $plan = Plan::where('slug', 'trial')->first();
            $trialDays = $plan->trial_days ?? (int) config('menu.trial_days');

            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['restaurant_name'],
                'plan_id' => $plan?->getKey(),
                'trial_ends_at' => now()->addDays($trialDays),
                'default_locale' => $data['locale'] ?? 'en',
                'currency_code' => config('menu.default_currency', 'ETB'),
            ]);

            Membership::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'role' => Role::Owner,
            ]);

            $this->activity->log('tenant.created', $tenant, ['slug' => $tenant->slug], $tenant->getKey());

            return $user;
        });
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
            'password' => $this->passwordRules(),
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
