<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PricePeriod;
use App\Models\PricePeriodItem;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PricePeriods;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Promotions → Price periods (owner, 2026-11-14): happy hour, lunch prices.
 * Owners and managers who may edit prices; plan feature "price_periods".
 */
class PricePeriodController extends Controller
{
    use RecordsChanges;

    public function __construct(private readonly CurrentTenant $current) {}

    public function index(): JsonResponse
    {
        $this->authorizePrices();

        return response()->json(['data' => PricePeriod::with('items')->orderBy('starts_at')->orderBy('id')->get()->map(fn (PricePeriod $p) => $this->row($p))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePrices();
        $data = $this->validated($request);
        $period = DB::transaction(function () use ($data) {
            $period = PricePeriod::create($data['period']);
            $this->saveItems($period, $data['items']);

            return $period;
        });
        $this->recordChange('price_period.created', $period);

        return response()->json(['data' => $this->row($period->load('items'))], 201);
    }

    public function update(Request $request, Tenant $tenant, int $period): JsonResponse
    {
        $this->authorizePrices();
        $model = PricePeriod::findOrFail($period);
        $data = $this->validated($request);
        DB::transaction(function () use ($model, $data) {
            $model->update($data['period']);
            $model->items()->delete();
            $this->saveItems($model, $data['items']);
        });
        $this->recordChange('price_period.updated', $model);

        return response()->json(['data' => $this->row($model->load('items'))]);
    }

    public function destroy(Tenant $tenant, int $period): Response
    {
        $this->authorizePrices();
        $model = PricePeriod::findOrFail($period);
        $model->delete();
        $this->recordChange('price_period.deleted', $model);

        return response()->noContent();
    }

    /** "What guests see now": running periods and the prices they change, per branch. */
    public function preview(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorizePrices();
        $branchId = $request->integer('branch_id') ?: null;
        $active = PricePeriods::active($tenant, $branchId);
        $locale = $tenant->default_locale ?: 'en';
        $text = fn (?array $v): string => $v === null || $v === [] ? '' : (string) ($v[$locale] ?? reset($v));
        $money = fn (int $m) => MenuPageData::money($m, $tenant->currency_code);
        $ids = $active->flatMap(fn ($r) => $r['period']->items->pluck('item_id'))->unique();
        $items = Item::with('variants')->whereIn('id', $ids)->get();
        $rows = [];
        foreach ($items as $item) {
            foreach ($active as $row) {
                foreach ($row['period']->items->where('item_id', $item->id) as $rule) {
                    $variant = $rule->item_variant_id ? $item->variants->firstWhere('id', $rule->item_variant_id) : null;
                    $normal = (int) ($variant !== null ? $variant->price_minor : $item->price_minor);
                    $rows[] = ['item' => $text($item->name).($variant ? ' · '.$text($variant->name) : ''), 'normal' => $money($normal), 'now' => $money(min($normal, $rule->apply($normal)))];
                }
            }
        }

        return response()->json(['data' => [
            'now' => now($tenant->timezone)->format('D H:i'),
            'running' => $active->map(fn ($r) => ['id' => $r['period']->id, 'name' => $text($r['period']->name), 'until' => $r['until']->format('H:i'), 'label' => __('public.deal_until', ['name' => $text($r['period']->name), 'time' => $r['until']->format('H:i')])])->values(),
            'prices' => $rows,
        ]]);
    }

    private function authorizePrices(): void
    {
        Gate::authorize('restaurant.manage');
        Gate::authorize('menu.edit_prices');
    }

    /** @return array{period: array<string, mixed>, items: list<array<string, mixed>>} */
    private function validated(Request $request): array
    {
        $own = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $this->current->id());
        $data = $request->validate([
            'name' => [Translations::required(60)],
            'branch_id' => ['nullable', 'integer', $own('branches')],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_id' => ['required', 'integer', $own('items')],
            'items.*.item_variant_id' => ['nullable', 'integer', $own('item_variants')],
            'items.*.mode' => ['required', Rule::in(['fixed', 'percent'])],
            'items.*.value' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);
        foreach ($data['items'] as $i => $row) {
            if ($row['mode'] === 'percent' && $row['value'] > 90) {
                throw ValidationException::withMessages(["items.{$i}.value" => __('menu.price_period.percent')]);
            }
            if (! empty($row['item_variant_id']) && ! ItemVariant::whereKey($row['item_variant_id'])->where('item_id', $row['item_id'])->exists()) {
                throw ValidationException::withMessages(["items.{$i}.item_variant_id" => __('menu.price_period.size')]);
            }
        }
        $weekdays = array_values(array_unique(array_map('intval', $data['weekdays'])));
        sort($weekdays);

        return [
            'period' => ['name' => $data['name'], 'branch_id' => $data['branch_id'] ?? null, 'weekdays' => $weekdays, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'is_active' => $data['is_active'] ?? true],
            'items' => $data['items'],
        ];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function saveItems(PricePeriod $period, array $items): void
    {
        $seen = [];
        foreach ($items as $row) {
            $key = $row['item_id'].':'.($row['item_variant_id'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            PricePeriodItem::create(['price_period_id' => $period->id, 'item_id' => $row['item_id'], 'item_variant_id' => $row['item_variant_id'] ?? null, 'mode' => $row['mode'], 'value' => $row['value']]);
        }
    }

    /** @return array<string, mixed> */
    private function row(PricePeriod $p): array
    {
        return [
            'id' => $p->id,
            'name' => (object) $p->name,
            'branch_id' => $p->branch_id,
            'weekdays' => $p->weekdays,
            'starts_at' => substr($p->starts_at, 0, 5),
            'ends_at' => substr($p->ends_at, 0, 5),
            'is_active' => $p->is_active,
            'items' => $p->items->map(fn (PricePeriodItem $i) => ['item_id' => $i->item_id, 'item_variant_id' => $i->item_variant_id, 'mode' => $i->mode, 'value' => $i->value])->values(),
        ];
    }
}
