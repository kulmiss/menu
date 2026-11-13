<?php

namespace App\Support\Insights;

use App\Models\Branch;
use App\Models\Item;
use App\Models\QrCode;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Insights export and the monthly report show, for one
 * period (restaurant-local days, both ends included) and optionally one
 * branch, plus the same totals for the period just before it. Runs inside
 * the restaurant's tenant context.
 */
class InsightsReport
{
    public const METRICS = ['menu_view' => 'views', 'qr_scan' => 'scans', 'item_view' => 'item_views', 'order_add' => 'order_adds'];

    /**
     * @return array{
     *     from: string, to: string, branch: string|null,
     *     days: list<array{date: string, views: int, scans: int, item_views: int, order_adds: int}>,
     *     totals: array{views: int, scans: int, item_views: int, order_adds: int},
     *     previous: array{views: int, scans: int, item_views: int, order_adds: int},
     *     dishes: list<array{id: int, name: string, category: string, views: int, adds: int}>,
     *     codes: list<array{id: int, label: string, kind: string, branch: string, scans: int, deleted: bool}>
     * }
     */
    public static function build(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $locale = $tenant->default_locale;
        $text = fn (?array $v): string => (string) (($v ?? [])[$locale] ?? (reset($v) ?: ''));
        $base = fn (CarbonImmutable $a, CarbonImmutable $b) => DB::table('stats_daily')->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$a->toDateString(), $b->toDateString()])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId));

        $perDay = $base($from, $to)->whereIn('metric', array_keys(self::METRICS))
            ->groupBy('date', 'metric')->get(['date', 'metric', DB::raw('SUM(count) as total')])
            ->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $rows = $perDay->get($d->toDateString(), collect());
            $row = ['date' => $d->toDateString()];
            foreach (self::METRICS as $metric => $key) {
                $row[$key] = (int) ($rows->firstWhere('metric', $metric)->total ?? 0);
            }
            $days[] = $row;
        }
        $totals = [];
        foreach (self::METRICS as $key) {
            $totals[$key] = array_sum(array_column($days, $key));
        }

        $length = (int) $from->diffInDays($to) + 1;
        $prev = $base($from->subDays($length), $from->subDay())->whereIn('metric', array_keys(self::METRICS))
            ->groupBy('metric')->pluck(DB::raw('SUM(count)'), 'metric');
        $previous = [];
        foreach (self::METRICS as $metric => $key) {
            $previous[$key] = (int) ($prev[$metric] ?? 0);
        }

        $perDish = $base($from, $to)->whereIn('metric', ['item_view', 'order_add'])
            ->groupBy('subject_id', 'metric')->get(['subject_id', 'metric', DB::raw('SUM(count) as total')])
            ->groupBy('subject_id');
        $items = Item::with('category')->whereIn('id', $perDish->keys())->get()->keyBy('id');
        $dishes = $perDish->map(function ($rows, $id) use ($items, $text) {
            $item = $items->get($id);

            return [
                'id' => (int) $id,
                'name' => $item ? $text($item->name) : __('insights.export.deleted_dish'),
                'category' => $item?->category ? $text($item->category->name) : '',
                'views' => (int) ($rows->firstWhere('metric', 'item_view')->total ?? 0),
                'adds' => (int) ($rows->firstWhere('metric', 'order_add')->total ?? 0),
            ];
        })->sortBy([['views', 'desc'], ['adds', 'desc'], ['name', 'asc']])->values()->all();

        $scans = $base($from, $to)->where('metric', 'qr_scan')->groupBy('subject_id')
            ->orderByDesc(DB::raw('SUM(count)'))->get(['subject_id', DB::raw('SUM(count) as total')]);
        $qr = QrCode::withTrashed()->with(['branch', 'table.branch'])->whereIn('id', $scans->pluck('subject_id'))->get()->keyBy('id');
        $main = Branch::orderByDesc('is_default')->orderBy('sort')->orderBy('id')->first();
        $codes = $scans->map(function ($r) use ($qr, $text, $main) {
            $code = $qr->get($r->subject_id);

            return [
                'id' => (int) $r->subject_id,
                'label' => match (true) {
                    $code === null => __('insights.export.deleted_code'),
                    $code->table !== null => __('qr.table', ['label' => $code->table->label]),
                    $code->label !== null => $code->label,
                    $code->branch !== null => $text($code->branch->name),
                    default => __('qr.restaurant'),
                },
                'kind' => match (true) {
                    $code === null => 'deleted',
                    $code->dining_table_id !== null => 'table',
                    $code->label !== null => 'extra',
                    $code->branch_id !== null => 'branch',
                    default => 'restaurant',
                },
                'branch' => $text(($code === null ? $main : ($code->branch ?? $code->table->branch ?? $main))?->name),
                'scans' => (int) $r->total,
                'deleted' => $code === null || $code->trashed(),
            ];
        })->values()->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branch' => $branchId !== null ? $text(Branch::find($branchId)?->name) : null,
            'days' => $days,
            'totals' => $totals,
            'previous' => $previous,
            'dishes' => $dishes,
            'codes' => $codes,
        ];
    }

    /** Change against the previous period as a whole percentage; null when there was nothing before. */
    public static function change(int $now, int $before): ?int
    {
        return $before === 0 ? null : (int) round(($now - $before) / $before * 100);
    }
}
