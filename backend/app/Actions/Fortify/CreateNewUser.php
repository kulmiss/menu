<?php

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\ValidSlug;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Self-service sign-up: creates the owner, their restaurant (tenant) on a
 * trial, and the owner membership — all or nothing.
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
}
