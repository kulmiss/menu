<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddonGroupResource;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Reusable add-on groups ("Extra toppings") with their options. */
class AddonGroupController extends Controller
{
    use RecordsChanges;

    public function index(): AnonymousResourceCollection
    {
        return AddonGroupResource::collection(
            AddonGroup::with('options')->withCount('items')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $this->validated($request);

        $group = DB::transaction(function () use ($data) {
            $group = AddonGroup::create([
                ...collect($data)->except('options')->all(),
                'sort' => $this->nextSort(AddonGroup::query()),
            ]);
            $this->syncOptions($group, $data['options'] ?? []);

            return $group;
        });
        $this->recordChange('addon_group.created', $group);

        return (new AddonGroupResource($group->load('options')->loadCount('items')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, int $group): AddonGroupResource
    {
        Gate::authorize('menu.manage');
        $model = AddonGroup::with('options')->findOrFail($group);
        $data = $this->validated($request, $model);

        DB::transaction(function () use ($model, $data) {
            $model->fill(collect($data)->except('options')->all())->save();
            if (array_key_exists('options', $data)) {
                $this->syncOptions($model, $data['options']);
            }
        });
        $this->recordChange('addon_group.updated', $model, ActivityLogger::diff($model));

        return new AddonGroupResource($model->load('options')->loadCount('items'));
    }

    public function destroy(Tenant $tenant, int $group): Response
    {
        Gate::authorize('menu.manage');
        $model = AddonGroup::findOrFail($group);
        $model->delete();
        $this->recordChange('addon_group.deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        Gate::authorize('menu.manage');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->applyOrder(AddonGroup::query(), $data['ids']);
        $this->recordChange('addon_group.reordered');

        return response()->noContent();
    }

    /** An option sold out / available — every team member may do this. */
    public function optionSoldOut(Request $request, Tenant $tenant, int $option): JsonResponse
    {
        Gate::authorize('menu.toggle_sold_out');
        $model = AddonOption::findOrFail($option);
        $model->forceFill($request->validate(['is_sold_out' => ['required', 'boolean']]))->save();
        $this->recordChange('addon_option.sold_out', $model, ActivityLogger::diff($model));

        return response()->json(['data' => ['id' => $model->id, 'is_sold_out' => $model->is_sold_out]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AddonGroup $group = null): array
    {
        $partial = $group !== null;
        $optionIds = $group ? $group->options->pluck('id')->all() : [];

        $data = $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', Translations::required(80)],
            'min_choices' => ['sometimes', 'integer', 'between:0,50'],
            'max_choices' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'options' => [$partial ? 'sometimes' : 'present', 'array', 'max:50'],
            'options.*.id' => ['sometimes', 'nullable', 'integer', Rule::in($optionIds)],
            'options.*.name' => ['required', Translations::required(80)],
            'options.*.price_minor' => ['sometimes', 'integer', 'min:0', 'max:'.config('menu.max_price_minor')],
            'options.*.is_sold_out' => ['sometimes', 'boolean'],
        ]);

        $min = $data['min_choices'] ?? ($group !== null ? $group->min_choices : 0);
        $max = array_key_exists('max_choices', $data) ? $data['max_choices'] : $group?->max_choices;
        if ($max !== null && $max < $min) {
            throw ValidationException::withMessages(['max_choices' => __('menu.menu_editor.max_below_min')]);
        }

        return $data;
    }

    /** @param array<int|string, array<string, mixed>> $rows */
    private function syncOptions(AddonGroup $group, array $rows): void
    {
        $keep = [];
        foreach (array_values($rows) as $sort => $row) {
            $values = [
                'name' => $row['name'],
                'price_minor' => (int) ($row['price_minor'] ?? 0),
                'is_sold_out' => (bool) ($row['is_sold_out'] ?? false),
                'sort' => $sort,
            ];
            $option = ! empty($row['id'])
                ? tap(AddonOption::where('addon_group_id', $group->id)->findOrFail((int) $row['id']))->update($values)
                : $group->options()->create($values);
            $keep[] = $option->id;
        }
        AddonOption::where('addon_group_id', $group->id)->whereNotIn('id', $keep)->delete();
    }
}
