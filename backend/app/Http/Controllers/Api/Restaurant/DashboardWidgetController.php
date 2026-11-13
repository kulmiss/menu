<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Media;
use App\Models\OpeningHour;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Support\Features;
use App\Support\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Restaurant dashboard widgets (owner, 2026-11-11). One small endpoint per
 * widget, so they load in parallel and one failing never breaks the page.
 * Staff only get the visit trend; plan, usage and money are for owners and
 * managers (restaurant.manage), enforced here.
 */
class DashboardWidgetController extends Controller
{
    public const MIN_DISHES = 5;

    /** Setup checklist: each step, whether it is done, and the screen that does it. */
    public function setup(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $look = TenantAppearance::current();
        $profile = TenantProfile::query()->first();
        $dishes = Item::count();
        $withPhoto = Item::whereNotNull('media_id')->count();
        $steps = [
            ['key' => 'logo', 'done' => $look->logo_media_id !== null, 'link' => '/admin/appearance'],
            ['key' => 'banner', 'done' => $look->cover_media_id !== null, 'link' => '/admin/appearance'],
            ['key' => 'dishes', 'done' => $dishes >= self::MIN_DISHES, 'link' => '/admin/menu'],
            // Photos: at least 5 dishes (or every dish of a smaller menu) have one.
            ['key' => 'photos', 'done' => $dishes > 0 && $withPhoto >= min(self::MIN_DISHES, $dishes), 'link' => '/admin/menu'],
            ['key' => 'hours', 'done' => OpeningHour::exists(), 'link' => '/admin/info'],
            ['key' => 'info', 'done' => $profile !== null && (array_filter((array) $profile->address) !== [] || $profile->phone || $profile->whatsapp), 'link' => '/admin/info'],
            ['key' => 'qr', 'done' => $tenant->qr_downloaded_at !== null, 'link' => '/admin/qr-codes'],
        ];
        $done = count(array_filter($steps, fn (array $s) => $s['done']));

        return response()->json(['data' => [
            'steps' => $steps,
            'percent' => (int) round($done * 100 / count($steps)),
            'complete' => $done === count($steps),
            'hidden' => $tenant->setup_hidden_at !== null,
        ]]);
    }

    public function hideSetup(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $tenant->forceFill(['setup_hidden_at' => now()])->save();

        return response()->json(['data' => ['hidden' => true]]);
    }

    /** Views and scans: today vs yesterday and the last 7 days (staff too). */
    public function trend(Tenant $tenant): JsonResponse
    {
        Gate::authorize('menu.toggle_sold_out');
        $today = now($tenant->timezone)->startOfDay();
        $data = Cache::remember("dash:{$tenant->id}:trend:{$today->toDateString()}", 120, function () use ($tenant, $today) {
            $rows = DB::table('stats_daily')->where('tenant_id', $tenant->id)
                ->whereIn('metric', ['menu_view', 'qr_scan'])
                ->whereBetween('date', [$today->copy()->subDays(6)->toDateString(), $today->toDateString()])
                ->groupBy('date', 'metric')->get(['date', 'metric', DB::raw('SUM(count) as total')]);
            $days = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = $today->copy()->subDays($i)->toDateString();
                $pick = fn (string $m) => (int) ($rows->first(fn ($r) => Carbon::parse($r->date)->toDateString() === $day && $r->metric === $m)->total ?? 0);
                $days[] = ['date' => $day, 'views' => $pick('menu_view'), 'scans' => $pick('qr_scan')];
            }

            return $days;
        });

        return response()->json(['data' => [
            'days' => $data,
            'today' => $data[6],
            'yesterday' => $data[5],
        ]]);
    }

    /** Dishes without a photo, the most viewed first (only when the menu shows photos). */
    public function missingPhotos(Tenant $tenant): JsonResponse
    {
        Gate::authorize('menu.manage');
        if (! TenantAppearance::current()->show_photos) {
            return response()->json(['data' => ['enabled' => false, 'items' => [], 'total' => 0]]);
        }
        $since = now($tenant->timezone)->subDays(30)->toDateString();
        $views = DB::table('stats_daily')->select('subject_id', DB::raw('SUM(count) as views'))
            ->where('tenant_id', $tenant->id)->where('metric', 'item_view')->where('date', '>=', $since)
            ->groupBy('subject_id');
        $query = Item::whereNull('items.media_id')->where('items.is_visible', true);
        $total = (clone $query)->count();
        $items = $query->leftJoinSub($views, 'v', 'v.subject_id', '=', 'items.id')
            ->orderByDesc(DB::raw('COALESCE(v.views, 0)'))->orderBy('items.id')
            ->limit(8)->get(['items.id', 'items.name', DB::raw('COALESCE(v.views, 0) as views')]);

        return response()->json(['data' => [
            'enabled' => true,
            'total' => $total,
            'items' => $items->map(fn (Item $i) => ['id' => $i->id, 'name' => (object) $i->name, 'views' => (int) $i->getAttribute('views')])->values(),
        ]]);
    }

    /** "My order" lists: most-added dishes and the average list shown to a waiter. */
    public function orders(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $on = Features::for($tenant)['order_list'] && TenantAppearance::current()->show_order_list;
        if (! $on) {
            return response()->json(['data' => ['enabled' => false]]);
        }
        $periods = [];
        foreach ([7, 30] as $days) {
            $periods[$days] = Cache::remember("dash:{$tenant->id}:orders:{$days}:".now()->format('YmdH'), 300, function () use ($tenant, $days) {
                $since = now($tenant->timezone)->subDays($days - 1)->toDateString();
                $base = fn () => DB::table('stats_daily')->where('tenant_id', $tenant->id)->where('date', '>=', $since);
                $top = $base()->where('metric', 'order_add')->groupBy('subject_id')
                    ->orderByDesc(DB::raw('SUM(count)'))->limit(5)->get(['subject_id', DB::raw('SUM(count) as added')]);
                $names = Item::whereIn('id', $top->pluck('subject_id'))->get(['id', 'name'])->keyBy('id');
                $shown = (int) $base()->where('metric', 'order_shown')->sum('count');
                $value = (int) $base()->where('metric', 'order_value')->sum('count');

                return [
                    'top' => $top->filter(fn ($r) => $names->has($r->subject_id))
                        ->map(fn ($r) => ['id' => (int) $r->subject_id, 'name' => $names[$r->subject_id]->name, 'added' => (int) $r->added])->values()->all(),
                    'lists_shown' => $shown,
                    'average_minor' => $shown > 0 ? intdiv($value, $shown) : null,
                ];
            });
        }

        return response()->json(['data' => ['enabled' => true, 'currency' => $tenant->currency_code, 'periods' => $periods]]);
    }

    /** Plan, days left and usage against the plan's limits. */
    public function plan(Tenant $tenant, PlanLimits $limits): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $tenant->loadMissing('plan');
        $ends = $tenant->paid_until ?? $tenant->trial_ends_at;
        $usage = [
            'items' => Item::count(),
            'branches' => Branch::count(),
            'photos' => Media::count(),
            'languages' => 1, // English only for now (owner, 2026-11-10)
            'team' => DB::table('memberships')->where('tenant_id', $tenant->id)->count(),
        ];

        return response()->json(['data' => [
            'plan' => $tenant->plan ? (object) $tenant->plan->name : null,
            'trial' => $tenant->paid_until === null && $tenant->trial_ends_at !== null,
            'ends_at' => $ends?->toIso8601String(),
            'days_left' => $ends ? max(0, (int) ceil(now()->diffInDays($ends, false))) : null,
            'usage' => collect($usage)->map(fn (int $used, string $key) => [
                'key' => $key,
                'used' => $used,
                'limit' => $limits->limit($key),
            ])->values(),
        ]]);
    }
}
