<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\Item;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daily counters (stats_daily) that look like a real restaurant: busier on
 * weekends, growing over time, popular dishes viewed more, a share of guests
 * scanning table codes and building a "My order" list. The counters are
 * daily, so lunch peaks can't show; weekends and growth do. Today is filled
 * up to the current hour so "today vs yesterday" looks right.
 */
class Insights
{
    /** @var list<array<string, int|string>> */
    private array $rows = [];

    public function __construct(private readonly CurrentTenant $current) {}

    /**
     * @param  int  $perDay  menu views on an ordinary weekday at the end of the period
     * @param  CarbonImmutable|null  $until  last day with visits (inactive restaurants stop earlier)
     */
    public function seed(Tenant $tenant, int $days, int $perDay, ?CarbonImmutable $until = null, bool $dishes = true): void
    {
        $this->current->runAs($tenant, function () use ($tenant, $days, $perDay, $until, $dishes) {
            $branches = Branch::orderBy('sort')->orderBy('id')->get();
            $codes = QrCode::whereNotNull('dining_table_id')->get()->groupBy('branch_id');
            $restaurantCodes = QrCode::whereNull('dining_table_id')->get();
            $items = $dishes ? Item::orderBy('id')->get(['id', 'is_popular', 'is_sold_out', 'price_minor']) : collect();
            $today = CarbonImmutable::now($tenant->timezone)->startOfDay();
            $hourShare = max(0.15, (CarbonImmutable::now($tenant->timezone)->hour + 1) / 24);
            $start = CarbonImmutable::parse($tenant->created_at)->startOfDay()->max($today->subDays($days - 1));
            mt_srand(crc32($tenant->slug));

            for ($day = $start; $day->lte($until ?? $today); $day = $day->addDay()) {
                $age = $day->diffInDays($today, true);
                $growth = 1 - min(0.45, $age / ($days * 2.2));                       // ~45 % fewer visits 90 days ago
                $weekend = in_array($day->dayOfWeek, [5, 6, 0], true) ? [5 => 1.25, 6 => 1.6, 0 => 1.45][$day->dayOfWeek] : 1.0;
                $share = $day->eq($today) ? $hourShare : 1.0;
                foreach ($branches as $bi => $branch) {
                    $views = (int) round($perDay * ($bi === 0 ? 1 : 0.55) * $growth * $weekend * $share * (mt_rand(85, 115) / 100));
                    if ($views <= 0) {
                        continue;
                    }
                    $date = $day->toDateString();
                    $this->add($tenant->id, $date, $branch->id, 'menu_view', 0, $views);
                    $tableCodes = $codes->get($branch->id, $bi === 0 ? $restaurantCodes : collect());
                    foreach ($tableCodes as $ci => $code) {
                        $scans = (int) round($views * 0.55 / max(1, $tableCodes->count()) * (1.4 - $ci / max(1, $tableCodes->count())) * (mt_rand(60, 140) / 100));
                        $this->add($tenant->id, $date, $branch->id, 'qr_scan', $code->id, $scans);
                    }
                    if ($items->isEmpty()) {
                        continue;
                    }
                    $lists = 0;
                    foreach ($items as $ii => $item) {
                        $weight = ($item->is_popular ? 3.0 : 1.0) * (1.6 - min(1.2, $ii / max(1, $items->count()) * 1.2));
                        $viewsOfDish = (int) round($views * 1.4 * $weight / $items->count() * (mt_rand(50, 150) / 100));
                        $this->add($tenant->id, $date, $branch->id, 'item_view', $item->id, $viewsOfDish);
                        if (! $item->is_sold_out) {
                            $added = (int) round($viewsOfDish * 0.14 * (mt_rand(40, 160) / 100));
                            $this->add($tenant->id, $date, $branch->id, 'order_add', $item->id, $added);
                            $lists += $added;
                        }
                    }
                    $shown = (int) round($lists * 0.35);
                    $this->add($tenant->id, $date, $branch->id, 'order_shown', 0, $shown);
                    $this->add($tenant->id, $date, $branch->id, 'order_value', 0, $shown * mt_rand(32000, 52000));
                }
            }
        });
        $this->flush();
    }

    private function add(int $tenant, string $date, int $branch, string $metric, int $subject, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $this->rows[] = ['tenant_id' => $tenant, 'date' => $date, 'branch_id' => $branch, 'metric' => $metric, 'subject_id' => $subject, 'count' => $count];
        if (count($this->rows) >= 1000) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        foreach (array_chunk($this->rows, 1000) as $chunk) {
            DB::table('stats_daily')->insert($chunk);
        }
        $this->rows = [];
    }
}
