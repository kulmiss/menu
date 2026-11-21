<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\BranchVariantPrice;
use App\Models\DiningTable;
use App\Models\ItemVariant;
use App\Models\Menu;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\Features;
use App\Support\PlanLimits;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Branches, their tables, item overrides and which menus they show
 * (PLAN.md §4.5–4.6, screen 7). Owners and managers.
 */
class BranchController extends Controller
{
    use RecordsChanges;

    /** Field names as the owner sees them in messages. */
    private const ATTRIBUTES = ['slug' => 'web address', 'maps_url' => 'map link'];

    public function __construct(private readonly CurrentTenant $current, private readonly PlanLimits $limits) {}

    public function index(): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $branches = Branch::withCount('tables')->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get();
        $codes = QrCode::whereNull('dining_table_id')->whereNotNull('branch_id')->pluck('code', 'branch_id');
        $menus = DB::table('menu_branch')->where('tenant_id', $this->current->id())->get()->groupBy('branch_id');

        return response()->json(['data' => $branches->map(fn (Branch $b) => $this->row($b, $codes[$b->id] ?? null, $menus->get($b->id)?->pluck('menu_id')->all() ?? []))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate($this->rules(), [], self::ATTRIBUTES);
        $this->limits->ensureRoom('branches', Branch::count());

        $branch = DB::transaction(function () use ($data) {
            $branch = Branch::create([...$data, 'is_default' => false, 'sort' => $this->nextSort(Branch::query())]);
            QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $branch->id]);

            return $branch;
        });
        $this->recordChange('branch.created', $branch);

        return response()->json(['data' => $this->row($branch->loadCount('tables'), QrCode::where('branch_id', $branch->id)->value('code'))], 201);
    }

    public function update(Request $request, Tenant $tenant, int $branch): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        $data = $request->validate([...$this->rules($model->id, partial: true), 'is_default' => ['sometimes', 'accepted']], [], self::ATTRIBUTES);

        DB::transaction(function () use ($model, $data) {
            if (! empty($data['is_default'])) {
                Branch::where('id', '!=', $model->id)->update(['is_default' => false]);
                $data['is_active'] = true;
            }
            if ($model->is_default && array_key_exists('is_active', $data) && ! $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => __('qr.branch_default_delete')]);
            }
            $model->fill($data)->save();
        });
        $this->recordChange('branch.updated', $model, ActivityLogger::diff($model));

        return response()->json(['data' => $this->row($model->loadCount('tables'), QrCode::whereNull('dining_table_id')->where('branch_id', $model->id)->value('code'))]);
    }

    public function destroy(Tenant $tenant, int $branch): Response
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        if ($model->is_default) {
            throw ValidationException::withMessages(['branch' => __('qr.branch_default_delete')]);
        }
        DB::transaction(function () use ($model) {
            // Printed codes stay unique forever; they stop working.
            QrCode::where('branch_id', $model->id)
                ->orWhereIn('dining_table_id', DiningTable::where('branch_id', $model->id)->pluck('id'))
                ->get()->each->delete();
            $model->delete();
        });
        $this->recordChange('branch.deleted', $model);

        return response()->noContent();
    }

    // ---- Tables ------------------------------------------------------------

    public function tables(Tenant $tenant, int $branch): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        $tables = DiningTable::with('qrCode')->where('branch_id', $model->id)->orderBy('sort')->orderBy('id')->get();

        return response()->json(['data' => $tables->map(fn (DiningTable $t) => $this->tableRow($t))]);
    }

    /** One table ({label}) or a numbered range ({prefix, from, to}). */
    public function addTables(Request $request, Tenant $tenant, int $branch): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        $data = $request->validate([
            'label' => ['required_without:from', 'nullable', 'string', 'max:30'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'from' => ['required_without:label', 'nullable', 'integer', 'min:0', 'max:9999'],
            'to' => ['required_with:from', 'nullable', 'integer', 'gte:from', 'max:9999'],
        ]);
        $labels = isset($data['from'])
            ? array_map(fn (int $n) => trim(($data['prefix'] ?? '').' '.$n), range((int) $data['from'], (int) $data['to']))
            : [trim((string) $data['label'])];
        if (count($labels) > 200) {
            throw ValidationException::withMessages(['to' => __('validation.max.numeric', ['attribute' => 'tables', 'max' => 200])]);
        }
        $existing = DiningTable::where('branch_id', $model->id)->pluck('label')->map(fn ($l) => mb_strtolower((string) $l))->all();
        $labels = array_values(array_filter(array_unique($labels), fn ($l) => $l !== '' && ! in_array(mb_strtolower($l), $existing, true)));
        $this->limits->ensureRoom('tables', DiningTable::count(), count($labels));

        $created = DB::transaction(function () use ($model, $labels) {
            $sort = $this->nextSort(DiningTable::where('branch_id', $model->id));

            return collect($labels)->map(function (string $label, int $i) use ($model, $sort) {
                $table = DiningTable::create(['branch_id' => $model->id, 'label' => $label, 'sort' => $sort + $i]);
                $table->setRelation('qrCode', QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $model->id, 'dining_table_id' => $table->id]));

                return $table;
            });
        });
        $this->recordChange('tables.created', $model, ['tables' => ['to' => $labels]]);

        return response()->json(['data' => $created->map(fn (DiningTable $t) => $this->tableRow($t))->values()], 201);
    }

    public function deleteTable(Tenant $tenant, int $table): Response
    {
        Gate::authorize('restaurant.manage');
        $model = DiningTable::findOrFail($table);
        DB::transaction(function () use ($model) {
            QrCode::where('dining_table_id', $model->id)->get()->each->delete();
            $model->delete();
        });
        $this->recordChange('table.deleted', $model);

        return response()->noContent();
    }

    // ---- Overrides -----------------------------------------------------------

    public function overrides(Tenant $tenant, int $branch): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);

        $sizes = BranchVariantPrice::query()
            ->join('item_variants', 'item_variants.id', '=', 'branch_variant_prices.item_variant_id')
            ->where('branch_variant_prices.branch_id', $model->id)
            ->get(['item_variants.item_id', 'branch_variant_prices.item_variant_id', 'branch_variant_prices.price_minor'])
            ->groupBy('item_id');
        $rows = BranchItemOverride::where('branch_id', $model->id)->get(['item_id', 'price_minor', 'is_sold_out', 'is_hidden'])->keyBy('item_id');

        $data = $rows->keys()->merge($sizes->keys())->unique()->sort()->values()->map(fn ($itemId) => [
            'item_id' => (int) $itemId,
            'price_minor' => $rows->get($itemId)?->price_minor,
            'is_sold_out' => Features::here('sold_out') ? $rows->get($itemId)?->is_sold_out : null,
            'is_hidden' => $rows->get($itemId)?->is_hidden,
            // Size id → this branch's price (QUESTIONS #78).
            'sizes' => (object) ($sizes->get($itemId)?->mapWithKeys(fn ($r) => [(string) $r->item_variant_id => (int) $r->price_minor])->all() ?? []),
        ]);

        return response()->json(['data' => $data]);
    }

    /** Replaces the branch's overrides; rows with nothing set are dropped. */
    public function saveOverrides(Request $request, Tenant $tenant, int $branch): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Branch::findOrFail($branch);
        $data = $request->validate([
            'overrides' => ['present', 'array', 'max:5000'],
            'overrides.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('tenant_id', $this->current->id())],
            'overrides.*.price_minor' => ['nullable', 'integer', 'min:0', 'max:'.config('menu.max_price_minor')],
            'overrides.*.is_sold_out' => ['nullable', 'boolean'],
            'overrides.*.is_hidden' => ['nullable', 'boolean'],
            'overrides.*.sizes' => ['sometimes', 'nullable', 'array'],
            'overrides.*.sizes.*' => ['nullable', 'integer', 'min:0', 'max:'.config('menu.max_price_minor')],
        ]);

        // Sizes must belong to the item they are listed under.
        $sizeIds = [];
        foreach ($data['overrides'] as $row) {
            foreach (array_keys($row['sizes'] ?? []) as $variantId) {
                $sizeIds[] = (int) $variantId;
            }
        }
        $owners = ItemVariant::whereIn('id', $sizeIds)->pluck('item_id', 'id');
        foreach ($data['overrides'] as $i => $row) {
            foreach (array_keys($row['sizes'] ?? []) as $variantId) {
                if ((int) ($owners[(int) $variantId] ?? 0) !== (int) $row['item_id']) {
                    throw ValidationException::withMessages(["overrides.$i.sizes" => __('validation.exists', ['attribute' => 'size'])]);
                }
            }
        }

        // "Sold out" off: the screen doesn't show the marks, so the saved ones are kept as they are.
        if (! Features::here('sold_out')) {
            $kept = BranchItemOverride::where('branch_id', $model->id)->whereNotNull('is_sold_out')->pluck('is_sold_out', 'item_id')->all();
            $rows = [];
            foreach ($data['overrides'] as $row) {
                $rows[(int) $row['item_id']] = ['is_sold_out' => null, ...$row];
            }
            foreach ($kept as $itemId => $mark) {
                $rows[(int) $itemId] = ['item_id' => (int) $itemId, ...($rows[(int) $itemId] ?? []), 'is_sold_out' => (bool) $mark];
            }
            $data['overrides'] = array_values($rows);
        }

        DB::transaction(function () use ($model, $data) {
            BranchItemOverride::where('branch_id', $model->id)->delete();
            BranchVariantPrice::where('branch_id', $model->id)->delete();
            foreach ($data['overrides'] as $row) {
                foreach ($row['sizes'] ?? [] as $variantId => $price) {
                    if ($price !== null) {
                        BranchVariantPrice::create(['branch_id' => $model->id, 'item_variant_id' => (int) $variantId, 'price_minor' => (int) $price]);
                    }
                }
                $values = array_intersect_key($row, array_flip(['price_minor', 'is_sold_out', 'is_hidden']));
                if (array_filter($values, fn ($v) => $v !== null) === []) {
                    continue;
                }
                BranchItemOverride::create(['branch_id' => $model->id, 'item_id' => $row['item_id'], ...$values]);
            }
        });
        $this->recordChange('branch.overrides_saved', $model);

        return $this->overrides($tenant, $model->id);
    }

    /** Branches a menu is limited to: [] = every branch. */
    public function menuBranches(Request $request, Tenant $tenant, int $menu): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Menu::findOrFail($menu);
        $data = $request->validate([
            'branch_ids' => ['present', 'array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())],
        ]);
        $all = Branch::pluck('id')->all();
        $ids = array_map('intval', $data['branch_ids']);
        sort($all);
        sort($ids);
        $model->branches()->sync($ids === $all ? [] : collect($ids)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $this->current->id()]])->all());
        $this->recordChange('menu.branches_changed', $model);

        return response()->json(['data' => ['branch_ids' => $model->branches()->pluck('branches.id')]]);
    }

    /** @return array<string, mixed> */
    private function rules(?int $ignore = null, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'name' => [...$sometimes, Translations::required(80)],
            'slug' => [...$sometimes, 'required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('branches', 'slug')->where('tenant_id', $this->current->id())->ignore($ignore)],
            'address' => ['sometimes', Translations::optional(300)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+() \-]{5,30}$/'],
            'maps_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  list<int>  $menuIds
     * @return array<string, mixed>
     */
    private function row(Branch $b, ?string $code, array $menuIds = []): array
    {
        return [
            'id' => $b->id,
            'slug' => $b->slug,
            'name' => $b->name,
            'address' => $b->address,
            'phone' => $b->phone,
            'maps_url' => $b->maps_url,
            'is_default' => $b->is_default,
            'is_active' => $b->is_active,
            'tables_count' => (int) ($b->tables_count ?? 0),
            'qr_code' => $code,
            'menu_ids' => array_map('intval', $menuIds),
            'url' => url($b->path($this->current->get() ?? new Tenant)),
        ];
    }

    /** @return array<string, mixed> */
    private function tableRow(DiningTable $t): array
    {
        return ['id' => $t->id, 'branch_id' => $t->branch_id, 'label' => $t->label, 'qr_code' => $t->qrCode?->code, 'qr_id' => $t->qrCode?->id];
    }
}
