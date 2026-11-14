<?php

namespace App\Support\SignUp;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SignupNotification;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use App\Support\Push\Push;
use App\Support\Qr\QrMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Self sign-up (owner, 2026-11-13): a restaurant registers itself, verifies
 * its e-mail, gets the sign-up plan (Free by default, no expiry) and goes
 * live — or waits for the Super Admin's approval when that is switched on.
 */
class SignUp
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /** The plan new sign-ups get: the one chosen in Settings → Sign-up, else "free". */
    public static function plan(): ?Plan
    {
        $id = PlatformSettings::get('signup_plan_id');

        return ($id ? Plan::find($id) : null) ?? Plan::where('slug', 'free')->first();
    }

    /**
     * @param  array{name: string, email: string, password: string, restaurant_name: string, slug: string, currency: string, locale?: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
            ]);

            $plan = self::plan();
            // No trial or paid-until date: active for as long as the plan is free.
            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['restaurant_name'],
                'plan_id' => $plan?->getKey(),
                'default_locale' => $data['locale'] ?? 'en',
                'currency_code' => $data['currency'],
                'signup_state' => Tenant::SIGNUP_UNVERIFIED,
            ]);

            Membership::create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'role' => Role::Owner]);

            $this->activity->log('signup.created', $tenant, ['slug' => ['to' => $tenant->slug], 'plan' => ['to' => $plan?->slug]], $tenant->getKey());

            return $user;
        });
    }

    /** After the owner clicked the link in the verification e-mail. */
    public function verified(User $user): void
    {
        foreach ($this->ownedPending($user, Tenant::SIGNUP_UNVERIFIED) as $tenant) {
            $approval = (bool) PlatformSettings::get('signup_approval');
            $tenant->forceFill(['signup_state' => $approval ? Tenant::SIGNUP_PENDING : null])->save();
            $tenant->bumpCacheVersion();
            $this->activity->log('signup.verified', $tenant, ['approval' => ['to' => $approval]], $tenant->id);

            $user->notify(new SignupNotification($approval ? 'pending' : 'welcome', $tenant));
            $this->notifyAdmins($tenant, $user, $approval);
        }
    }

    public function approve(Tenant $tenant): void
    {
        $tenant->forceFill(['signup_state' => null])->save();
        $tenant->bumpCacheVersion();
        $this->activity->log('signup.approved', $tenant, null, $tenant->id);
        $this->owner($tenant)?->notify(new SignupNotification('approved', $tenant));
    }

    /** Rejected: the owner is told why, then the restaurant (and the owner, if alone) is deleted, freeing the slug. */
    public function reject(Tenant $tenant, string $reason): void
    {
        $owner = $this->owner($tenant);
        if ($owner !== null) {
            Notification::route('mail', $owner->email)->notify(new SignupNotification('rejected', $tenant, ['reason' => $reason, 'name' => $owner->name]));
        }
        $this->activity->log('signup.rejected', null, ['restaurant' => ['to' => $tenant->name], 'slug' => ['to' => $tenant->slug], 'email' => ['to' => $owner?->email], 'reason' => ['to' => $reason]], null);
        $this->remove($tenant);
    }

    /**
     * Restaurants whose owner never verified within $days: deleted with their
     * owner (when that owner has no other restaurant), so the slug is free again.
     */
    public function prune(int $days = 7): int
    {
        $count = 0;
        Tenant::where('signup_state', Tenant::SIGNUP_UNVERIFIED)->where('created_at', '<', now()->subDays($days))->get()
            ->each(function (Tenant $tenant) use (&$count) {
                $owner = $this->owner($tenant);
                if ($owner?->hasVerifiedEmail()) {
                    return;
                }
                $this->activity->log('signup.pruned', null, ['restaurant' => ['to' => $tenant->name], 'slug' => ['to' => $tenant->slug], 'email' => ['to' => $owner?->email]], null);
                $this->remove($tenant);
                $count++;
            });

        return $count;
    }

    private function remove(Tenant $tenant): void
    {
        $owners = $tenant->memberships()->with('user')->get()->pluck('user')->filter();
        DB::transaction(function () use ($tenant, $owners) {
            $tenant->forceDelete();
            foreach ($owners as $user) {
                if (! Membership::where('user_id', $user->id)->exists() && ! $user->is_super_admin) {
                    $user->delete();
                }
            }
        });
        QrMap::touch();
    }

    private function owner(Tenant $tenant): ?User
    {
        return $tenant->memberships()->where('role', Role::Owner)->with('user')->first()?->user;
    }

    /** @return iterable<Tenant> */
    private function ownedPending(User $user, string $state): iterable
    {
        return Tenant::where('signup_state', $state)
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('role', Role::Owner))
            ->get();
    }

    private function notifyAdmins(Tenant $tenant, User $owner, bool $approval): void
    {
        // Phones of the Super Admins (QUESTIONS #189); each can switch these off in the app.
        $type = $approval ? 'signup_pending' : 'signup_new';
        Push::send(Push::superAdmins(), $type,
            __("push.{$type}.title", ['restaurant' => $tenant->name]),
            __("push.{$type}.body", ['owner' => $owner->name, 'email' => $owner->email]),
            $approval ? '/p/approvals' : "/p/restaurants/{$tenant->id}");
        if (! PlatformSettings::get('signup_notify')) {
            return;
        }
        $admins = User::where('is_super_admin', true)->get();
        Notification::send($admins, new SignupNotification('admin', $tenant, ['owner' => $owner->name, 'email' => $owner->email, 'approval' => $approval]));
    }
}
