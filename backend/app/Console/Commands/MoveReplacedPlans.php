<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\Features;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Restaurants on an old plan that was replaced (QUESTIONS #184) keep it while
 * their paid time or trial runs; once it has ended they move to the new plan.
 * Runs hourly. Logged per restaurant.
 */
#[Signature('plans:move-replaced')]
#[Description('Move restaurants from replaced plans to the new plan once their paid time has ended')]
class MoveReplacedPlans extends Command
{
    public function handle(ActivityLogger $activity): int
    {
        $now = now();
        $moved = 0;
        $replaced = Plan::whereNotNull('replaced_by_plan_id')->get()->keyBy('id');
        Tenant::whereIn('plan_id', $replaced->keys())
            ->where(fn ($q) => $q->whereNull('paid_until')->orWhere('paid_until', '<=', $now))
            ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', $now))
            ->each(function (Tenant $tenant) use ($replaced, $activity, &$moved) {
                // Follow the chain if a replacement was itself replaced later.
                $to = $replaced[$tenant->plan_id]->replaced_by_plan_id;
                for ($i = 0; $i < 5 && isset($replaced[$to]); $i++) {
                    $to = $replaced[$to]->replaced_by_plan_id;
                }
                $from = $tenant->plan_id;
                $tenant->forceFill(['plan_id' => $to])->save();
                Features::forget($tenant->id);
                $tenant->bumpCacheVersion();
                $activity->log('platform.plan_moved', $tenant, ['plan_id' => ['from' => $from, 'to' => $to]], $tenant->id);
                $moved++;
            });
        $this->info("Moved {$moved} restaurant(s) to their new plan.");

        return self::SUCCESS;
    }
}
