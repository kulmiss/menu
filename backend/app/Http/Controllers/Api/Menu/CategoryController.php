<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\MenuCopier;
use App\Support\PlanLimits;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class CategoryController extends Controller
{
    use RecordsChanges;

    public function __construct(private readonly CurrentTenant $current, private readonly PlanLimits $limits) {}

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $request->validate([
            'menu_id' => ['required', 'integer', $this->existsHere('menus')],
            ...$this->rules(),
        ]);

        $category = Category::create([
            ...$data,
            'sort' => $this->nextSort(Category::where('menu_id', $data['menu_id'])),
        ]);
        $this->recordChange('category.created', $category);

        return (new CategoryResource($category->setRelation('items', collect())))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, int $category): CategoryResource
    {
        Gate::authorize('menu.manage');
        $model = Category::findOrFail($category);
        $data = $request->validate([
            // Moving a category to another menu of the same restaurant.
            'menu_id' => ['sometimes', 'integer', $this->existsHere('menus')],
            ...$this->rules(partial: true),
        ]);

        if (isset($data['menu_id']) && (int) $data['menu_id'] !== $model->menu_id) {
            $data['sort'] = $this->nextSort(Category::where('menu_id', $data['menu_id']));
        }

        $model->fill($data)->save();
        $this->recordChange('category.updated', $model, ActivityLogger::diff($model));

        return new CategoryResource($model);
    }

    public function destroy(Tenant $tenant, int $category): Response
    {
        Gate::authorize('menu.manage');
        $model = Category::findOrFail($category);
        $model->delete();
        $this->recordChange('category.deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        Gate::authorize('menu.manage');
        $data = $request->validate([
            'menu_id' => ['required', 'integer', $this->existsHere('menus')],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        $this->applyOrder(Category::where('menu_id', $data['menu_id']), $data['ids']);
        $this->recordChange('category.reordered', Menu::find($data['menu_id']));

        return response()->noContent();
    }

    public function duplicate(Tenant $tenant, int $category, MenuCopier $copier): JsonResponse
    {
        Gate::authorize('menu.manage');
        $source = Category::findOrFail($category);
        $this->limits->ensureRoom('items', Item::count(), $copier->itemCount($source));

        $copy = DB::transaction(fn () => $copier->copyCategory($source, [
            'name' => $this->copyName($source->name),
            'sort' => $source->sort + 1,
        ]));
        // Make room right after the original.
        Category::where('menu_id', $source->menu_id)->where('sort', '>', $source->sort)
            ->whereKeyNot($copy->id)->increment('sort');
        $this->recordChange('category.duplicated', $copy, ['from' => $source->id]);

        return (new CategoryResource($copy->load('items.variants')))->response()->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'name' => [...$sometimes, Translations::required(80)],
            'description' => ['sometimes', Translations::optional(500)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'is_visible' => [...$sometimes, 'boolean'],
        ];
    }

    private function existsHere(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', $this->current->id());
    }
}
