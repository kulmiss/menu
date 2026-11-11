<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Super Admin dashboard widgets (owner, 2026-11-11): one small endpoint each
 * under /platform (Super Admin + two-factor, never while "logged in as"), so
 * they load in parallel and fail on their own. Platform-wide totals are
 * cached for a few minutes.
 */
class DashboardWidgetController extends Controller
{
    public const INACTIVE_DAYS = 14;

    public function __construct(private readonly ActivityLogger $activity) {}

    /** Income this month vs last month, MRR, and the last 12 months. */
    public function revenue(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('platform:w:revenue', 300, function () {
            $start = now()->startOfMonth()->subMonths(11);
            $rows = DB::table('payments')->where('paid_at', '>=', $start)
                ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount_minor) as total")
                ->groupBy('month')->pluck('total', 'month');
            $months = [];
            for ($i = 0; $i < 12; $i++) {
                $key = $start->copy()->addMonths($i)->format('Y-m');
                $months[] = ['month' => $key, 'total' => (int) ($rows[$key] ?? 0)];
            }
            // MRR: what restaurants paid up to now pay per month (yearly plans count 1/12).
            $mrr = (int) DB::table('tenants')->join('plans', 'plans.id', '=', 'tenants.plan_id')
                ->whereNull('tenants.deleted_at')->where('tenants.paid_until', '>=', now())
                ->sum(DB::raw("CASE WHEN plans.interval = 'year' THEN plans.price_minor / 12 ELSE plans.price_minor END"));

            return [
                'currency' => PlatformSettings::get('default_currency'),
                'this_month' => $months[11]['total'],
                'last_month' => $months[10]['total'],
                'mrr' => $mrr,
                'months' => $months,
            ];
        })]);
    }

    /** Trials and subscriptions ending in the next 14 days. */
    public function expiring(): JsonResponse
    {
        $until = now()->addDays(14);
        $rows = Tenant::query()->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereBetween('paid_until', [now(), $until])
                ->orWhere(fn ($q) => $q->whereNull('paid_until')->whereBetween('trial_ends_at', [now(), $until])))
            ->orderByRaw('COALESCE(paid_until, trial_ends_at)')->limit(50)
            ->get(['id', 'name', 'slug', 'trial_ends_at', 'paid_until']);

        return response()->json(['data' => $rows->map(function (Tenant $t) {
            $ends = $t->paid_until ?? $t->trial_ends_at;

            return [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
                'kind' => $t->paid_until ? 'subscription' : 'trial',
                'ends_at' => $ends?->toIso8601String(),
                'days_left' => $ends ? max(0, (int) ceil(now()->diffInDays($ends, false))) : null,
            ];
        })->values()]);
    }

    /** No menu views and no admin edits for 14+ days. */
    public function inactive(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('platform:w:inactive', 300, function () {
            $since = now()->subDays(self::INACTIVE_DAYS);
            $views = DB::table('stats_daily')->where('metric', 'menu_view')->groupBy('tenant_id')->select('tenant_id', DB::raw('MAX(date) as last_view'));
            $edits = DB::table('activity_log')->whereNotNull('tenant_id')->groupBy('tenant_id')->select('tenant_id', DB::raw('MAX(created_at) as last_edit'));
            $rows = DB::table('tenants as t')->whereNull('t.deleted_at')->whereNull('t.suspended_at')
                ->where('t.created_at', '<', $since)
                ->leftJoinSub($views, 'v', 'v.tenant_id', '=', 't.id')
                ->leftJoinSub($edits, 'e', 'e.tenant_id', '=', 't.id')
                ->where(fn ($q) => $q->whereNull('v.last_view')->orWhere('v.last_view', '<', $since->toDateString()))
                ->where(fn ($q) => $q->whereNull('e.last_edit')->orWhere('e.last_edit', '<', $since))
                ->orderBy('t.id')->limit(50)
                ->get(['t.id', 't.name', 't.slug', 't.created_at', 'v.last_view', 'e.last_edit']);

            return $rows->map(function ($r) {
                $last = collect([$r->last_view, $r->last_edit, $r->created_at])->filter()->map(fn ($d) => Carbon::parse($d))->max();

                return ['id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'last_activity' => $last?->toDateString()];
            })->values()->all();
        })]);
    }

    /** Sign-ups → menu set up (≥ 5 dishes) → first QR scan → paying, for the last 30 or 90 days. */
    public function funnel(Request $request): JsonResponse
    {
        $days = $request->integer('days') === 90 ? 90 : 30;
        $row = Cache::remember("platform:w:funnel:{$days}", 300, fn () => DB::table('tenants as t')
            ->whereNull('t.deleted_at')->where('t.created_at', '>=', now()->subDays($days))
            ->selectRaw('COUNT(*) as signups')
            ->selectRaw('SUM((SELECT COUNT(*) FROM items i WHERE i.tenant_id = t.id) >= 5) as menu')
            ->selectRaw("SUM(EXISTS (SELECT 1 FROM stats_daily s WHERE s.tenant_id = t.id AND s.metric = 'qr_scan')) as scanned")
            ->selectRaw('SUM(EXISTS (SELECT 1 FROM payments p WHERE p.tenant_id = t.id)) as paying')
            ->first());
        $steps = [];
        $previous = null;
        foreach (['signups', 'menu', 'scanned', 'paying'] as $key) {
            $count = (int) ($row->{$key} ?? 0);
            $steps[] = ['key' => $key, 'count' => $count, 'percent' => $previous === null ? null : ($previous > 0 ? (int) round($count * 100 / $previous) : 0)];
            $previous = $count;
        }

        return response()->json(['data' => ['days' => $days, 'steps' => $steps]]);
    }

    /** Most views and scans (30 days) and the restaurants on the biggest plans. */
    public function top(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('platform:w:top', 300, function () {
            $since = now()->subDays(29)->toDateString();
            $best = fn (string $metric) => DB::table('stats_daily as s')->join('tenants as t', 't.id', '=', 's.tenant_id')
                ->whereNull('t.deleted_at')->where('s.metric', $metric)->where('s.date', '>=', $since)
                ->groupBy('s.tenant_id', 't.name')->orderByDesc('total')->limit(5)
                ->get(['s.tenant_id as id', 't.name', DB::raw('SUM(s.count) as total')])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'total' => (int) $r->total])->all();
            $plans = DB::table('tenants as t')->join('plans as p', 'p.id', '=', 't.plan_id')
                ->whereNull('t.deleted_at')->where('t.paid_until', '>=', now())
                ->orderByDesc('p.price_minor')->orderBy('t.name')->limit(5)
                ->get(['t.id', 't.name', 'p.name as plan', 'p.price_minor'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'plan' => json_decode((string) $r->plan, true), 'price_minor' => (int) $r->price_minor])->all();

            return ['views' => $best('menu_view'), 'scans' => $best('qr_scan'), 'plans' => $plans];
        })]);
    }

    /** The latest restaurants and how far they got with the setup. */
    public function signups(): JsonResponse
    {
        $rows = DB::table('tenants as t')->whereNull('t.deleted_at')->orderByDesc('t.created_at')->limit(10)
            ->leftJoin('tenant_appearance as a', 'a.tenant_id', '=', 't.id')
            ->select(['t.id', 't.name', 't.slug', 't.created_at', 't.qr_downloaded_at', 'a.logo_media_id', 'a.cover_media_id'])
            ->selectRaw('(SELECT COUNT(*) FROM items i WHERE i.tenant_id = t.id) as dishes')
            ->selectRaw('(SELECT COUNT(*) FROM items i WHERE i.tenant_id = t.id AND i.media_id IS NOT NULL) as photos')
            ->selectRaw('EXISTS (SELECT 1 FROM opening_hours h WHERE h.tenant_id = t.id) as hours')
            ->get();

        return response()->json(['data' => $rows->map(function ($r) {
            $steps = [
                'logo' => $r->logo_media_id !== null,
                'banner' => $r->cover_media_id !== null,
                'dishes' => $r->dishes >= 5,
                'photos' => $r->dishes > 0 && $r->photos >= min(5, $r->dishes),
                'hours' => (bool) $r->hours,
                'qr' => $r->qr_downloaded_at !== null,
            ];

            return [
                'id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug,
                'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                'steps' => $steps,
                'percent' => (int) round(count(array_filter($steps)) * 100 / count($steps)),
            ];
        })->values()]);
    }

    /** Disk, photos, cron, mail queue, errors today and versions, with warnings. */
    public function health(): JsonResponse
    {
        $data = Cache::remember('platform:w:health', 60, function () {
            $path = storage_path();
            $free = @disk_free_space($path) ?: null;
            $total = @disk_total_space($path) ?: null;
            $cron = Cache::get('cron:last_run');
            $cronAge = $cron ? (int) Carbon::parse($cron)->diffInMinutes(now(), true) : null;
            $queued = (int) DB::table('jobs')->count();
            $failed = (int) DB::table('failed_jobs')->count();
            $errors = $this->errorsToday();
            $freePercent = $free && $total ? (int) floor($free * 100 / $total) : null;

            return [
                'disk_free' => $free, 'disk_total' => $total, 'disk_free_percent' => $freePercent,
                'photos_bytes' => (int) DB::table('media')->sum('bytes'),
                'photos_count' => (int) DB::table('media')->count(),
                'cron_last_run' => $cron, 'cron_minutes_ago' => $cronAge,
                'queued' => $queued, 'failed' => $failed, 'errors_today' => $errors,
                'php' => PHP_VERSION, 'laravel' => app()->version(),
                'warnings' => array_values(array_filter([
                    $cronAge === null || $cronAge > 10 ? 'cron' : null,
                    $freePercent !== null && $freePercent < 10 ? 'disk' : null,
                    $failed > 0 ? 'failed' : null,
                    $queued > 50 ? 'queue' : null,
                    $errors > 0 ? 'errors' : null,
                ])),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Restaurants per plan, and plan changes this month. */
    public function planMix(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('platform:w:plan-mix', 300, function () {
            $plans = DB::table('plans')->get(['id', 'name', 'price_minor'])->keyBy('id');
            $counts = DB::table('tenants')->whereNull('deleted_at')->groupBy('plan_id')
                ->get(['plan_id', DB::raw('COUNT(*) as total')]);
            $changes = DB::table('activity_log')->where('action', 'platform.restaurant_updated')
                ->where('created_at', '>=', now()->startOfMonth())->whereNotNull('changes')->pluck('changes');
            [$up, $down] = [0, 0];
            foreach ($changes as $json) {
                $c = json_decode((string) $json, true)['plan_id'] ?? null;
                if (! is_array($c)) {
                    continue;
                }
                $from = $plans[$c['from'] ?? 0]->price_minor ?? 0;
                $to = $plans[$c['to'] ?? 0]->price_minor ?? 0;
                $to > $from ? $up++ : ($to < $from ? $down++ : null);
            }

            return [
                'plans' => $counts->map(fn ($r) => [
                    'id' => $r->plan_id === null ? null : (int) $r->plan_id,
                    'name' => $r->plan_id !== null && isset($plans[$r->plan_id]) ? json_decode((string) $plans[$r->plan_id]->name, true) : null,
                    'total' => (int) $r->total,
                ])->sortByDesc('total')->values()->all(),
                'upgrades' => $up,
                'downgrades' => $down,
            ];
        })]);
    }

    /** "Remind" / "Email owner": a short message to the restaurant's owners, logged. */
    public function email(Request $request, int $tenant): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'reason' => ['required', 'in:expiring,inactive'],
        ]);
        $model = Tenant::findOrFail($tenant);
        $owners = DB::table('memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.tenant_id', $model->id)->where('m.role', 'owner')->pluck('u.email')->all();
        abort_if($owners === [], 422, __('platform.remind.no_owner'));
        try {
            Mail::raw($data['message'], fn ($m) => $m->to($owners)->subject($data['subject']));
        } catch (Throwable $e) {
            return response()->json(['message' => __('platform.mail_test.failed', ['reason' => Str::limit($e->getMessage(), 300)])], 422);
        }
        $this->activity->log('platform.owner_emailed', $model, [
            'reason' => ['to' => $data['reason']],
            'subject' => ['to' => $data['subject']],
            'recipients' => ['to' => count($owners)],
        ], $model->id);

        return response()->json(['data' => ['sent_to' => count($owners)], 'message' => trans_choice('platform.remind.sent', count($owners), ['count' => count($owners)])]);
    }

    /** ERROR lines logged today (reads at most the last 2 MB of each log file). */
    private function errorsToday(): int
    {
        $today = now()->toDateString();
        $count = 0;
        foreach (array_unique([storage_path('logs/laravel.log'), storage_path("logs/laravel-{$today}.log")]) as $file) {
            if (! is_file($file)) {
                continue;
            }
            $size = (int) filesize($file);
            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }
            fseek($handle, max(0, $size - 2_000_000));
            $tail = (string) stream_get_contents($handle);
            fclose($handle);
            $count += preg_match_all('/^\['.preg_quote($today, '/').'[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/m', $tail);
        }

        return $count;
    }
}
