<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\QrCode;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Insights (PLAN.md screen 12) and the dashboard numbers (screen 1), read
 * from the daily counters. All dates are the restaurant's local days.
 */
class InsightsController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate([
            'days' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())],
        ]);
        $days = (int) ($data['days'] ?? 30);
        $today = now($this->current->get()?->timezone);
        $from = $today->copy()->subDays($days - 1)->toDateString();
        $base = fn () => DB::table('stats_daily')->where('tenant_id', $this->current->id())->where('date', '>=', $from)
            ->when(isset($data['branch_id']), fn ($q) => $q->where('branch_id', $data['branch_id']));

        $perDay = $base()->whereIn('metric', ['menu_view', 'qr_scan'])
            ->groupBy('date', 'metric')->get(['date', 'metric', DB::raw('SUM(count) as total')])
            ->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $rows = $perDay->get($date, collect());
            $series[] = [
                'date' => $date,
                'views' => (int) ($rows->firstWhere('metric', 'menu_view')->total ?? 0),
                'scans' => (int) ($rows->firstWhere('metric', 'qr_scan')->total ?? 0),
            ];
        }

        $topItems = $base()->where('metric', 'item_view')->groupBy('subject_id')
            ->orderByDesc(DB::raw('SUM(count)'))->limit(10)->get(['subject_id', DB::raw('SUM(count) as total')]);
        $itemNames = Item::whereIn('id', $topItems->pluck('subject_id'))->get(['id', 'name'])->keyBy('id');

        $scans = $base()->where('metric', 'qr_scan')->groupBy('subject_id')
            ->orderByDesc(DB::raw('SUM(count)'))->get(['subject_id', DB::raw('SUM(count) as total')]);
        $codes = QrCode::withTrashed()->with(['branch', 'table'])->whereIn('id', $scans->pluck('subject_id'))->get()->keyBy('id');
        $locale = $this->current->get()->default_locale;
        $text = fn (?array $v): string => (string) (($v ?? [])[$locale] ?? (reset($v) ?: ''));

        return response()->json(['data' => [
            'days' => $series,
            'totals' => [
                'views' => array_sum(array_column($series, 'views')),
                'scans' => array_sum(array_column($series, 'scans')),
                'item_views' => (int) $base()->where('metric', 'item_view')->sum('count'),
            ],
            'top_items' => $topItems->map(fn ($r) => [
                'id' => (int) $r->subject_id,
                'name' => $itemNames->has($r->subject_id) ? $text($itemNames[$r->subject_id]->name) : null,
                'count' => (int) $r->total,
            ])->values(),
            'scans' => $scans->map(function ($r) use ($codes, $text) {
                $code = $codes->get($r->subject_id);

                return [
                    'qr_id' => (int) $r->subject_id,
                    'code' => $code?->code,
                    'label' => match (true) {
                        $code === null => null,
                        $code->table !== null => __('qr.table', ['label' => $code->table->label]),
                        $code->label !== null => $code->label,
                        $code->branch !== null => $text($code->branch->name),
                        default => __('qr.restaurant'),
                    },
                    'deleted' => $code?->trashed() ?? true,
                    'count' => (int) $r->total,
                ];
            })->values(),
        ]]);
    }

    /** Today and yesterday at a glance, plus what is sold out (staff can see it too). */
    public function dashboard(): JsonResponse
    {
        Gate::authorize('menu.toggle_sold_out');
        $now = now($this->current->get()?->timezone);
        $rows = DB::table('stats_daily')->where('tenant_id', $this->current->id())
            ->whereIn('date', [$now->toDateString(), $now->copy()->subDay()->toDateString()])
            ->whereIn('metric', ['menu_view', 'qr_scan'])
            ->groupBy('date', 'metric')->get(['date', 'metric', DB::raw('SUM(count) as total')]);
        $get = fn (string $date, string $metric) => (int) ($rows->first(fn ($r) => Carbon::parse($r->date)->toDateString() === $date && $r->metric === $metric)->total ?? 0);
        $locale = $this->current->get()->default_locale;

        return response()->json(['data' => [
            'today' => ['views' => $get($now->toDateString(), 'menu_view'), 'scans' => $get($now->toDateString(), 'qr_scan')],
            'yesterday' => ['views' => $get($now->copy()->subDay()->toDateString(), 'menu_view'), 'scans' => $get($now->copy()->subDay()->toDateString(), 'qr_scan')],
            'sold_out' => Item::where('is_sold_out', true)->orderBy('name->'.$locale)->limit(50)->get(['id', 'name'])
                ->map(fn (Item $i) => ['id' => $i->id, 'name' => (object) $i->name])->values(),
        ]]);
    }
}
