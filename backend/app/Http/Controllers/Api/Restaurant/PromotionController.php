<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\MediaJanitor;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Promotions screen (PLAN.md screen 11): specials (photo, linked item) and
 * announcements, with optional dates and branch.
 */
class PromotionController extends Controller
{
    use RecordsChanges;

    public function __construct(private readonly CurrentTenant $current) {}

    public function index(): JsonResponse
    {
        Gate::authorize('restaurant.manage');

        return response()->json(['data' => Promotion::with('media')->orderBy('sort')->orderBy('id')->get()->map(fn (Promotion $p) => $this->row($p))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate($this->rules());
        $promotion = Promotion::create([...$data, 'sort' => $this->nextSort(Promotion::query())]);
        $this->recordChange('promotion.created', $promotion);

        return response()->json(['data' => $this->row($promotion->load('media'))], 201);
    }

    public function update(Request $request, Tenant $tenant, int $promotion): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $model = Promotion::findOrFail($promotion);
        $oldMedia = $model->media_id;
        $model->fill($request->validate($this->rules(partial: true)))->save();
        if ($oldMedia !== $model->media_id) {
            MediaJanitor::releaseIfUnused($oldMedia);
        }
        $this->recordChange('promotion.updated', $model, ActivityLogger::diff($model));

        return response()->json(['data' => $this->row($model->load('media'))]);
    }

    public function destroy(Tenant $tenant, int $promotion): Response
    {
        Gate::authorize('restaurant.manage');
        $model = Promotion::findOrFail($promotion);
        $model->delete();
        MediaJanitor::releaseIfUnused($model->media_id);
        $this->recordChange('promotion.deleted', $model);

        return response()->noContent();
    }

    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);
        $this->applyOrder(Promotion::query(), $data['ids']);
        $this->recordChange('promotion.reordered');

        return $this->index();
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];
        $own = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $this->current->id());

        return [
            'type' => [...$sometimes, 'required', Rule::in(Promotion::TYPES)],
            'title' => [...$sometimes, Translations::required(80)],
            'body' => ['sometimes', Translations::optional(500)],
            'media_id' => ['sometimes', 'nullable', 'integer', $own('media')],
            'item_id' => ['sometimes', 'nullable', 'integer', $own('items')],
            'branch_id' => ['sometimes', 'nullable', 'integer', $own('branches')],
            'starts_at' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i'],
            'ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function row(Promotion $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'title' => (object) $p->title,
            'body' => (object) $p->body,
            'media' => $p->media ? new MediaResource($p->media) : null,
            'item_id' => $p->item_id,
            'branch_id' => $p->branch_id,
            'starts_at' => $p->starts_at?->format('Y-m-d H:i'),
            'ends_at' => $p->ends_at?->format('Y-m-d H:i'),
            'is_active' => $p->is_active,
            'sort' => $p->sort,
        ];
    }
}
