<?php

namespace App\Support;

use App\Models\Plan;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Plan limits (plans.limits) checked on the server whenever something is
 * created. Over the limit after a downgrade: existing data stays, creating
 * more answers 402 (QUESTIONS #21). No plan or no value = unlimited.
 */
class PlanLimits
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function limit(string $key): ?int
    {
        $plan = $this->current->get()?->plan;
        $limits = $plan instanceof Plan ? ($plan->limits ?? []) : [];
        $value = $limits[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** Throws a 402 answer when adding `$adding` more would pass the limit. */
    public function ensureRoom(string $key, int $currentCount, int $adding = 1): void
    {
        $limit = $this->limit($key);

        if ($limit !== null && $currentCount + $adding > $limit) {
            throw new HttpResponseException(response()->json([
                'message' => __('menu.limits.reached', [
                    'limit' => $limit,
                    'what' => __('menu.limits.'.$key),
                ]),
                'code' => 'limits.'.$key,
                'limit' => $limit,
                'upgrade' => UpgradeContact::payload(),
            ], 402));
        }
    }
}
