<?php

namespace App\Support\PublicMenu;

use App\Models\Item;
use App\Models\Menu;
use App\Models\PricePeriod;
use App\Models\Tenant;
use App\Support\Features;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Time-based prices on the guest page (owner, 2026-11-14). The static page
 * is built with the prices valid at that moment; the scheduler removes the
 * page when a period starts or ends (menu:price-periods), and open phones
 * update themselves as for any other menu change.
 */
class PricePeriods
{
    /**
     * Periods running now for this branch (the restaurant's time zone).
     *
     * @return Collection<int, array{period: PricePeriod, until: Carbon}>
     */
    public static function active(Tenant $tenant, ?int $branchId, ?Carbon $at = null): Collection
    {
        if (! (Features::for($tenant)['price_periods'] ?? false)) {
            return collect();
        }
        $now = ($at ?? now())->copy()->setTimezone($tenant->timezone);

        return PricePeriod::with('items')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderBy('id')->get()
            ->map(fn (PricePeriod $p) => ['period' => $p, 'until' => $p->endsAfter($now)])
            ->filter(fn ($row) => $row['until'] !== null)->values();
    }

    /**
     * Applies the running periods to the loaded menus. Returns, per dish id,
     * the normal prices and the label ("Happy hour until 18:00"); the first
     * period wins when two cover the same dish.
     *
     * @param  Collection<int, Menu>  $menus
     * @param  Collection<int, array{period: PricePeriod, until: Carbon}>  $active
     * @param  callable(?array<string, string>): string  $text
     * @return array<int, array{was: int|null, sizes: array<int, int>, label: string}>
     */
    public static function apply(Collection $menus, Collection $active, callable $text): array
    {
        if ($active->isEmpty()) {
            return [];
        }
        $rules = [];
        foreach ($active as $row) {
            foreach ($row['period']->items as $rule) {
                $rules[$rule->item_id][$rule->item_variant_id ?? 0] ??= [$rule, $row];
            }
        }
        $deals = [];
        foreach ($menus as $menu) {
            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    /** @var Item $item */
                    if (! isset($rules[$item->id])) {
                        continue;
                    }
                    $deal = ['was' => null, 'sizes' => [], 'label' => ''];
                    if ($item->variants->isEmpty() && isset($rules[$item->id][0]) && $item->price_minor !== null) {
                        [$rule, $row] = $rules[$item->id][0];
                        $deal['was'] = (int) $item->price_minor;
                        $item->price_minor = min((int) $item->price_minor, $rule->apply((int) $item->price_minor));
                        $deal['label'] = self::label($row, $text);
                    }
                    foreach ($item->variants as $variant) {
                        $match = $rules[$item->id][$variant->id] ?? $rules[$item->id][0] ?? null;
                        if ($match === null) {
                            continue;
                        }
                        [$rule, $row] = $match;
                        $deal['sizes'][$variant->id] = (int) $variant->price_minor;
                        $variant->price_minor = min((int) $variant->price_minor, $rule->apply((int) $variant->price_minor));
                        $deal['label'] = self::label($row, $text);
                    }
                    if ($deal['label'] !== '') {
                        $deals[$item->id] = $deal;
                    }
                }
            }
        }

        return $deals;
    }

    /** A short key of what runs now: when it changes, the static page is out of date. */
    public static function signature(Tenant $tenant, ?Carbon $at = null): string
    {
        $branches = PricePeriod::where('is_active', true)->distinct()->pluck('branch_id');

        return $branches->map(fn ($b) => ($b ?? 0).':'.self::active($tenant, $b, $at)->map(fn ($r) => $r['period']->id)->implode(','))->implode('|');
    }

    /** @param  array{period: PricePeriod, until: Carbon}  $row */
    private static function label(array $row, callable $text): string
    {
        return (string) __('public.deal_until', ['name' => $text($row['period']->name), 'time' => $row['until']->format('H:i')]);
    }
}
