<?php

namespace App\Support\Campaigns;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who gets a campaign: the owners of the restaurants matching the filters,
 * minus owners who unsubscribed from news. One e-mail per owner and
 * restaurant (an owner of two restaurants gets two, each with its own
 * placeholders).
 *
 * Filters: plan_id, status (active | trial | expiring | expired | suspended),
 * tag_id, inactive_days (no menu views and no edits for that many days),
 * signed_up_from / signed_up_to (Y-m-d).
 */
class Audience
{
    public const STATUSES = ['active', 'trial', 'expiring', 'expired', 'suspended'];

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{tenant_id: int, user_id: int, email: string, name: string, vars: array<string, string>}>
     */
    public static function recipients(array $filters): array
    {
        $tenants = self::tenants($filters);
        if ($tenants->isEmpty()) {
            return [];
        }
        $owners = DB::table('memberships')->whereIn('tenant_id', $tenants->keys())->where('role', Role::Owner->value)->get(['tenant_id', 'user_id']);
        $users = User::whereIn('id', $owners->pluck('user_id')->unique())->whereNull('news_unsubscribed_at')->get()->keyBy('id');

        return $owners->filter(fn ($o) => isset($users[$o->user_id]))->map(function ($o) use ($tenants, $users) {
            $tenant = $tenants[$o->tenant_id];
            $user = $users[$o->user_id];

            return [
                'tenant_id' => (int) $tenant->id,
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
                'name' => (string) $user->name,
                'vars' => [
                    'restaurant_name' => $tenant->name,
                    'owner_name' => (string) $user->name,
                    'plan' => $tenant->plan ? (string) ($tenant->plan->name['en'] ?? $tenant->plan->slug) : '',
                    'menu_link' => url('/'.$tenant->slug),
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Tenant> keyed by id
     */
    private static function tenants(array $filters): Collection
    {
        $query = Tenant::query()->with('plan')->whereNull('signup_state')
            ->when($filters['plan_id'] ?? null, fn ($q, $id) => $q->where('plan_id', $id))
            ->when($filters['tag_id'] ?? null, fn ($q, $tag) => $q->whereHas('platformTags', fn ($w) => $w->whereKey($tag)))
            ->when($filters['signed_up_from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['signed_up_to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'));

        if ($days = (int) ($filters['inactive_days'] ?? 0)) {
            $since = now()->subDays($days);
            $query->where('created_at', '<', $since)
                ->whereNotExists(fn ($q) => $q->from('stats_daily')->whereColumn('stats_daily.tenant_id', 'tenants.id')->where('metric', 'menu_view')->where('date', '>=', $since->toDateString()))
                ->whereNotExists(fn ($q) => $q->from('activity_log')->whereColumn('activity_log.tenant_id', 'tenants.id')->where('created_at', '>=', $since));
        }

        $status = $filters['status'] ?? null;

        return $query->get()->filter(fn (Tenant $t) => $status === null || self::matches($t, (string) $status))->keyBy('id');
    }

    private static function matches(Tenant $t, string $status): bool
    {
        $state = $t->subscriptionState()->value;

        return match ($status) {
            'active' => $state === 'active',
            'trial' => $state === 'trial',
            'suspended' => $state === 'suspended',
            'expired' => in_array($state, ['grace', 'read_only', 'unavailable'], true),
            // Paid or trial time that ends within the next 14 days.
            'expiring' => in_array($state, ['active', 'trial'], true) && ($end = $t->paid_until ?? $t->trial_ends_at) !== null && $end->isFuture() && $end->lte(now()->addDays(14)),
            default => true,
        };
    }
}
