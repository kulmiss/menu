<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MenuResource;
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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    use RecordsChanges;

    public function __construct(private readonly CurrentTenant $current, private readonly PlanLimits $limits) {}

    /** All menus with counts (every role can read; staff use it for sold-out). */
    public function index(): AnonymousResourceCollection
    {
        $menus = Menu::query()
            ->with(['schedules', 'branches:id'])
            ->withCount('categories')
            ->addSelect(['items_count' => Item::query()
                ->selectRaw('count(*)')
                ->join('categories', 'categories.id', '=', 'items.category_id')
                ->whereColumn('categories.menu_id', 'menus.id'),
            ])
            ->orderBy('sort')->orderBy('id')
            ->get();

        return MenuResource::collection($menus);
    }

    public function show(Tenant $tenant, int $menu): MenuResource
    {
        return new MenuResource(Menu::with('schedules')->findOrFail($menu));
    }

    /** The editor's view of one menu: categories with their items. */
    public function tree(Tenant $tenant, int $menu): JsonResponse
    {
        $model = Menu::with([
            'schedules',
            'categories.media',
            'categories.items.variants',
            'categories.items.media',
        ])->findOrFail($menu);

        return response()->json(['data' => [
            'menu' => new MenuResource($model),
            'categories' => CategoryResource::collection($model->categories),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $request->validate($this->rules());
        $this->limits->ensureRoom('menus', Menu::count());

        $menu = Menu::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
            'sort' => $this->nextSort(Menu::query()),
        ]);
        $this->recordChange('menu.created', $menu);

        return (new MenuResource($menu->load('schedules')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, int $menu): MenuResource
    {
        Gate::authorize('menu.manage');
        $model = Menu::findOrFail($menu);
        $data = $request->validate($this->rules($model, partial: true));

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $data['name'] ?? $model->name, $model);
        }

        $model->fill($data)->save();
        $this->recordChange('menu.updated', $model, ActivityLogger::diff($model));

        return new MenuResource($model->load('schedules'));
    }

    public function destroy(Tenant $tenant, int $menu): Response
    {
        Gate::authorize('menu.manage');
        $model = Menu::findOrFail($menu);
        $model->delete();
        $this->recordChange('menu.deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        Gate::authorize('menu.manage');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->applyOrder(Menu::query(), $data['ids']);
        $this->recordChange('menu.reordered');

        return response()->noContent();
    }

    /** Copies the menu with its categories, items, prices and links. */
    public function duplicate(Tenant $tenant, int $menu, MenuCopier $copier): JsonResponse
    {
        Gate::authorize('menu.manage');
        $source = Menu::findOrFail($menu);
        $this->limits->ensureRoom('menus', Menu::count());
        $this->limits->ensureRoom('items', Item::count(), $copier->itemCount($source));

        $copy = DB::transaction(fn () => $copier->copyMenu($source, [
            'name' => $this->copyName($source->name),
            'slug' => $this->uniqueSlug(null, $source->name),
            'sort' => $this->nextSort(Menu::query()),
        ]));
        $this->recordChange('menu.duplicated', $copy, ['from' => $source->id]);

        return (new MenuResource($copy->load('schedules')))->response()->setStatusCode(201);
    }

    /** Replaces the weekly time windows of a scheduled menu. */
    public function schedules(Request $request, Tenant $tenant, int $menu): MenuResource
    {
        Gate::authorize('menu.manage');
        $model = Menu::findOrFail($menu);
        $data = $request->validate([
            'schedule_mode' => ['required', Rule::in([Menu::ALWAYS, Menu::SCHEDULED])],
            'schedules' => ['present', 'array', 'max:50'],
            'schedules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i'],
        ]);

        foreach ($data['schedules'] as $i => $row) {
            if ($row['starts_at'] === $row['ends_at']) {
                throw ValidationException::withMessages([
                    "schedules.{$i}.ends_at" => __('menu.menu_editor.empty_window'),
                ]);
            }
        }

        DB::transaction(function () use ($model, $data) {
            $model->forceFill(['schedule_mode' => $data['schedule_mode']])->save();
            $model->schedules()->delete();
            foreach ($data['schedules'] as $row) {
                $model->schedules()->create($row);
            }
        });
        $this->recordChange('menu.schedule_updated', $model, ['schedule_mode' => $data['schedule_mode'], 'windows' => count($data['schedules'])]);

        return new MenuResource($model->load('schedules'));
    }

    /** @return array<string, mixed> */
    private function rules(?Menu $menu = null, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'name' => [...$sometimes, Translations::required(80)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_visible' => [...$sometimes, 'boolean'],
        ];
    }

    /**
     * A slug unique among this restaurant's menus, from the given one or the
     * name in the main language ("menu" for names in other scripts).
     *
     * @param  array<string, string>  $name
     */
    private function uniqueSlug(?string $wanted, array $name, ?Menu $ignore = null): string
    {
        $default = $this->current->get()->default_locale ?? 'en';
        $base = $wanted ?: Str::slug($name[$default] ?? reset($name) ?: '') ?: 'menu';
        $base = Str::limit($base, 50, '');
        $slug = $base;

        for ($i = 2; Menu::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
