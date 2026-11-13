<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ItemSummaryResource;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemAllergen;
use App\Models\ItemVariant;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\MediaJanitor;
use App\Support\MenuCopier;
use App\Support\PlanLimits;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Items. Owners and managers edit everything; every member can mark items
 * sold out; staff with "can edit prices" may change prices only.
 */
class ItemController extends Controller
{
    use RecordsChanges;

    private const RELATIONS = ['variants', 'addonGroups', 'tags', 'allergenRows', 'category', 'media', 'photos'];

    /** Photos per dish (owner, 2026-11-11). */
    public const MAX_PHOTOS = 5;

    public function __construct(private readonly CurrentTenant $current, private readonly PlanLimits $limits) {}

    public function show(Tenant $tenant, int $item): ItemResource
    {
        return new ItemResource(Item::with(self::RELATIONS)->findOrFail($item));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $request->validate($this->rules());
        $this->limits->ensureRoom('items', Item::count());

        $item = DB::transaction(function () use ($data) {
            $photos = $this->photoIds($data, null);
            $item = Item::create([
                ...$this->fields($data),
                ...($photos === null ? [] : ['media_id' => $photos[0] ?? null]),
                'sort' => $this->nextSort(Item::where('category_id', $data['category_id'])),
            ]);
            $this->syncRelations($item, $data);
            $this->syncPhotos($item, $photos);

            return $item;
        });
        $this->recordChange('item.created', $item);

        return (new ItemResource($item->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, int $item): ItemResource
    {
        Gate::authorize('menu.manage');
        $model = Item::with(self::RELATIONS)->findOrFail($item);
        $data = $request->validate($this->rules(partial: true, item: $model));

        $changes = DB::transaction(function () use ($model, $data) {
            $fields = $this->fields($data);
            if (isset($fields['category_id']) && (int) $fields['category_id'] !== $model->category_id) {
                $fields['sort'] = $this->nextSort(Item::where('category_id', $fields['category_id']));
            }
            $photos = $this->photoIds($data, $model);
            $oldMedia = array_unique([$model->media_id, ...$model->photos->pluck('id')->all()]);
            if ($photos !== null) {
                $fields['media_id'] = $photos[0] ?? null;
            }
            $model->fill($fields)->save();
            $changes = ActivityLogger::diff($model);
            $this->syncRelations($model, $data);
            $this->syncPhotos($model, $photos);
            if ($photos !== null) {
                $changes['photos'] = ['from' => count(array_filter($oldMedia)), 'to' => count($photos)];
                foreach (array_diff(array_filter($oldMedia), $photos) as $gone) {
                    MediaJanitor::releaseIfUnused((int) $gone);
                }
            }

            return $changes;
        });
        $this->recordChange('item.updated', $model, $changes);

        return new ItemResource($model->refresh()->load(self::RELATIONS));
    }

    public function destroy(Tenant $tenant, int $item): Response
    {
        Gate::authorize('menu.manage');
        $model = Item::with('photos')->findOrFail($item);
        $model->delete();
        foreach (array_unique([$model->media_id, ...$model->photos->pluck('id')->all()]) as $mediaId) {
            MediaJanitor::releaseIfUnused($mediaId);
        }
        $this->recordChange('item.deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        Gate::authorize('menu.manage');
        $data = $request->validate([
            'category_id' => ['required', 'integer', $this->existsHere('categories')],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        $this->applyOrder(Item::where('category_id', $data['category_id']), $data['ids']);
        $this->recordChange('item.reordered', Category::find($data['category_id']));

        return response()->noContent();
    }

    public function duplicate(Tenant $tenant, int $item, MenuCopier $copier): JsonResponse
    {
        Gate::authorize('menu.manage');
        $source = Item::findOrFail($item);
        $this->limits->ensureRoom('items', Item::count());

        $copy = DB::transaction(function () use ($copier, $source) {
            Item::where('category_id', $source->category_id)->where('sort', '>', $source->sort)->increment('sort');

            return $copier->copyItem($source, [
                'name' => $this->copyName($source->name),
                'sort' => $source->sort + 1,
            ]);
        });
        $this->recordChange('item.duplicated', $copy, ['from' => $source->id]);

        return (new ItemResource($copy->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    /** Sold out / available again — every team member may do this. */
    public function soldOut(Request $request, Tenant $tenant, int $item): ItemSummaryResource
    {
        Gate::authorize('menu.toggle_sold_out');
        $model = Item::with('variants')->findOrFail($item);
        $data = $request->validate(['is_sold_out' => ['required', 'boolean']]);

        $model->forceFill($data)->save();
        $this->recordChange('item.sold_out', $model, ActivityLogger::diff($model));

        return new ItemSummaryResource($model);
    }

    /** Prices only: for staff allowed to edit prices (and managers, owners). */
    public function price(Request $request, Tenant $tenant, int $item): ItemResource
    {
        Gate::authorize('menu.edit_prices');
        $model = Item::with(self::RELATIONS)->findOrFail($item);
        $data = $request->validate([
            'price_minor' => ['sometimes', 'nullable', ...$this->priceRule()],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['required', 'integer', Rule::in($model->variants->pluck('id')->all())],
            'variants.*.price_minor' => ['required', ...$this->priceRule()],
        ]);

        $changes = DB::transaction(function () use ($model, $data) {
            $changes = [];
            if (array_key_exists('price_minor', $data) && $model->variants->isEmpty()) {
                $model->forceFill(['price_minor' => $data['price_minor']])->save();
                $changes = ActivityLogger::diff($model);
            }
            foreach ($data['variants'] ?? [] as $row) {
                $variant = $model->variants->firstWhere('id', (int) $row['id']);
                $variant?->forceFill(['price_minor' => (int) $row['price_minor']])->save();
                if ($variant?->wasChanged()) {
                    $changes['variants'][$variant->id] = ActivityLogger::diff($variant)['price_minor'];
                }
            }

            return $changes;
        });
        $this->recordChange('item.price_changed', $model, $changes);

        return new ItemResource($model->refresh()->load(self::RELATIONS));
    }

    /**
     * Raise or lower prices of many items by a percentage (sizes included),
     * optionally rounded to a step such as 5.00.
     */
    public function bulkPrice(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('menu.edit_prices');
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'percent' => ['required', 'numeric', 'between:-90,500', 'not_in:0'],
            'round_to' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);
        $factor = 1 + ((float) $data['percent'] / 100);
        $step = (int) ($data['round_to'] ?? 0);
        $adjust = function (int $price) use ($factor, $step): int {
            $new = $price * $factor;
            $rounded = $step > 0 ? round($new / $step) * $step : round($new);

            return (int) min(config('menu.max_price_minor'), max(0, $rounded));
        };

        $items = Item::with('variants')->whereIn('id', $data['ids'])->get();
        DB::transaction(function () use ($items, $adjust) {
            foreach ($items as $item) {
                if ($item->price_minor !== null) {
                    $item->forceFill(['price_minor' => $adjust($item->price_minor)])->save();
                }
                foreach ($item->variants as $variant) {
                    $variant->forceFill(['price_minor' => $adjust($variant->price_minor)])->save();
                }
            }
        });
        $this->recordChange('item.bulk_price', null, [
            'ids' => $items->pluck('id')->all(),
            'percent' => (float) $data['percent'],
            'round_to' => $step,
        ]);

        return ItemSummaryResource::collection($items->each->refresh()->load('variants'));
    }

    /** One action on many items: show, hide, sold out, available, move, delete. */
    public function bulk(Request $request): Response
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['show', 'hide', 'sold_out', 'available', 'move', 'delete'])],
            'category_id' => ['required_if:action,move', 'integer', $this->existsHere('categories')],
        ]);
        Gate::authorize(in_array($data['action'], ['sold_out', 'available'], true) ? 'menu.toggle_sold_out' : 'menu.manage');

        $items = Item::whereIn('id', $data['ids']);
        DB::transaction(function () use ($items, $data) {
            match ($data['action']) {
                'show' => $items->update(['is_visible' => true]),
                'hide' => $items->update(['is_visible' => false]),
                'sold_out' => $items->update(['is_sold_out' => true]),
                'available' => $items->update(['is_sold_out' => false]),
                'delete' => $items->get()->each->delete(),
                'move' => $this->moveTo($items->orderBy('sort')->get(), (int) $data['category_id']),
                default => throw new \LogicException('Unknown bulk action.'),
            };
        });
        $this->recordChange('item.bulk_'.$data['action'], null, ['ids' => array_map('intval', $data['ids'])]);

        return response()->noContent();
    }

    /** @param Collection<int, Item> $items */
    private function moveTo($items, int $categoryId): void
    {
        $sort = $this->nextSort(Item::where('category_id', $categoryId));
        foreach ($items as $item) {
            $item->forceFill(['category_id' => $categoryId, 'sort' => $sort++])->save();
        }
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false, ?Item $item = null): array
    {
        $sometimes = $partial ? ['sometimes'] : [];
        $variantIds = $item ? $item->variants->pluck('id')->all() : [];

        return [
            'category_id' => [$partial ? 'sometimes' : 'required', 'integer', $this->existsHere('categories')],
            'name' => [...$sometimes, Translations::required(120)],
            'summary' => ['sometimes', Translations::optional(160)],
            'description' => ['sometimes', Translations::optional(2000)],
            'price_minor' => ['sometimes', 'nullable', ...$this->priceRule()],
            'media_id' => ['sometimes', 'nullable', 'integer', $this->existsHere('media')],
            'photo_ids' => ['sometimes', 'array', 'max:'.self::MAX_PHOTOS],
            'photo_ids.*' => ['integer', 'distinct', $this->existsHere('media')],
            'calories' => ['sometimes', 'nullable', 'integer', 'between:0,20000'],
            'prep_minutes' => ['sometimes', 'nullable', 'integer', 'between:0,600'],
            'is_sold_out' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'new_until' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'is_popular' => ['sometimes', 'boolean'],
            'show_photo' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array', 'max:20'],
            'variants.*.id' => ['sometimes', 'nullable', 'integer', Rule::in($variantIds)],
            'variants.*.name' => ['required', Translations::required(60)],
            'variants.*.price_minor' => ['required', ...$this->priceRule()],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'addon_group_ids' => ['sometimes', 'array', 'max:20'],
            'addon_group_ids.*' => ['integer', 'distinct', $this->existsHere('addon_groups')],
            'tag_ids' => ['sometimes', 'array', 'max:30'],
            'tag_ids.*' => ['integer', 'distinct', $this->existsHere('tags')],
            'allergens' => ['sometimes', 'array'],
            'allergens.*' => ['string', 'distinct', Rule::in(config('menu.allergens'))],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $fields = collect($data)->only([
            'category_id', 'name', 'summary', 'description', 'price_minor', 'media_id', 'calories', 'prep_minutes',
            'is_sold_out', 'is_visible', 'is_new', 'new_until', 'is_popular', 'show_photo',
        ])->all();

        // With sizes, the sizes carry the prices (QUESTIONS #37).
        if (! empty($data['variants'])) {
            $fields['price_minor'] = null;
        }

        return $fields;
    }

    /** @param array<string, mixed> $data */
    private function syncRelations(Item $item, array $data): void
    {
        if (array_key_exists('variants', $data)) {
            $this->syncVariants($item, $data['variants']);
        }
        if (array_key_exists('addon_group_ids', $data)) {
            $groups = [];
            foreach (array_values((array) $data['addon_group_ids']) as $i => $id) {
                $groups[(int) $id] = ['tenant_id' => $item->tenant_id, 'sort' => $i];
            }
            $item->addonGroups()->sync($groups);
        }
        if (array_key_exists('tag_ids', $data)) {
            $tags = [];
            foreach ((array) $data['tag_ids'] as $id) {
                $tags[(int) $id] = ['tenant_id' => $item->tenant_id];
            }
            $item->tags()->sync($tags);
        }
        if (array_key_exists('allergens', $data)) {
            ItemAllergen::where('item_id', $item->id)->delete();
            foreach (array_unique($data['allergens']) as $allergen) {
                ItemAllergen::create(['item_id' => $item->id, 'allergen' => $allergen]);
            }
        }
    }

    /**
     * The dish's photos after this request, in order, or null when the
     * request doesn't touch them. Older clients send only media_id: that
     * replaces the main photo and keeps the others.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>|null
     */
    private function photoIds(array $data, ?Item $item): ?array
    {
        if (array_key_exists('photo_ids', $data)) {
            return array_values(array_map('intval', (array) $data['photo_ids']));
        }
        if (! array_key_exists('media_id', $data)) {
            return null;
        }
        $others = $item ? $item->photos->pluck('id')->reject(fn ($id) => $id === $item->media_id)->all() : [];

        return array_slice(array_values(array_unique(array_filter([$data['media_id'] === null ? null : (int) $data['media_id'], ...$others]))), 0, self::MAX_PHOTOS);
    }

    /** @param list<int>|null $ids */
    private function syncPhotos(Item $item, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }
        $rows = [];
        foreach ($ids as $sort => $id) {
            $rows[$id] = ['tenant_id' => $item->tenant_id, 'sort' => $sort];
        }
        $item->photos()->sync($rows);
    }

    /** @param array<int|string, array<string, mixed>> $rows */
    private function syncVariants(Item $item, array $rows): void
    {
        if (collect($rows)->where('is_default', true)->count() > 1) {
            throw ValidationException::withMessages(['variants' => __('menu.menu_editor.one_default_variant')]);
        }

        $keep = [];
        foreach (array_values($rows) as $sort => $row) {
            $values = [
                'name' => $row['name'],
                'price_minor' => (int) $row['price_minor'],
                'is_default' => (bool) ($row['is_default'] ?? false),
                'sort' => $sort,
            ];
            $variant = ! empty($row['id'])
                ? tap(ItemVariant::where('item_id', $item->id)->findOrFail((int) $row['id']))->update($values)
                : $item->variants()->create($values);
            $keep[] = $variant->id;
        }
        ItemVariant::where('item_id', $item->id)->whereNotIn('id', $keep)->delete();
    }

    /** @return list<string> */
    private function priceRule(): array
    {
        return ['integer', 'min:0', 'max:'.config('menu.max_price_minor')];
    }

    private function existsHere(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', $this->current->id());
    }
}
