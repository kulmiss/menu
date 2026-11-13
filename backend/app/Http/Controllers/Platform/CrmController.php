<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformTag;
use App\Models\RestaurantNote;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super Admin CRM (owner, 2026-11-13): tags, notes and follow-ups on
 * restaurants. Everything here is behind the "platform" middleware, so
 * restaurants never see it.
 */
class CrmController extends Controller
{
    public const COLORS = ['#64748b', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#db2777'];

    public function tags(): JsonResponse
    {
        return response()->json(['data' => PlatformTag::withCount('tenants')->orderBy('name')->get()->map(fn (PlatformTag $t) => self::tag($t) + ['restaurants' => $t->tenants_count])]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $tag = PlatformTag::create($this->validTag($request));

        return response()->json(['data' => self::tag($tag)], 201);
    }

    public function updateTag(Request $request, PlatformTag $tag): JsonResponse
    {
        $tag->update($this->validTag($request, $tag));

        return response()->json(['data' => self::tag($tag)]);
    }

    public function destroyTag(PlatformTag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }

    public function setTags(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($restaurant);
        $data = $request->validate(['tag_ids' => ['present', 'array', 'max:20'], 'tag_ids.*' => ['integer', Rule::exists('platform_tags', 'id')]]);
        $tenant->platformTags()->sync($data['tag_ids']);

        return response()->json(['data' => $tenant->platformTags()->orderBy('name')->get()->map(fn (PlatformTag $t) => self::tag($t))]);
    }

    public function notes(int $restaurant): JsonResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($restaurant);

        return response()->json(['data' => RestaurantNote::with('author')->where('tenant_id', $tenant->id)->latest()->latest('id')->get()->map(fn (RestaurantNote $n) => $this->note($n))]);
    }

    public function storeNote(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($restaurant);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $note = RestaurantNote::create(['tenant_id' => $tenant->id, 'author_id' => $request->user()->id, 'body' => trim($data['body'])]);

        return response()->json(['data' => $this->note($note->load('author'))], 201);
    }

    public function updateNote(Request $request, RestaurantNote $note): JsonResponse
    {
        abort_unless($note->author_id === $request->user()->id, 403, __('platform.note_not_yours'));
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $note->update(['body' => trim($data['body'])]);

        return response()->json(['data' => $this->note($note->load('author'))]);
    }

    public function destroyNote(Request $request, RestaurantNote $note): JsonResponse
    {
        abort_unless($note->author_id === $request->user()->id, 403, __('platform.note_not_yours'));
        $note->delete();

        return response()->json(null, 204);
    }

    public function followUp(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($restaurant);
        $data = $request->validate([
            'date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $tenant->forceFill([
            'follow_up_on' => $data['date'],
            'follow_up_reason' => $data['date'] ? (trim((string) ($data['reason'] ?? '')) ?: null) : null,
        ])->save();

        return response()->json(['data' => ['follow_up_on' => $tenant->follow_up_on?->toDateString(), 'follow_up_reason' => $tenant->follow_up_reason]]);
    }

    /** Dashboard widget: follow-ups due today and overdue (platform time zone: the app's). */
    public function due(): JsonResponse
    {
        $today = now()->toDateString();
        $rows = Tenant::query()->whereNotNull('follow_up_on')->where('follow_up_on', '<=', $today)
            ->with('platformTags')->orderBy('follow_up_on')->limit(50)->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'date' => $t->follow_up_on?->toDateString(), 'reason' => $t->follow_up_reason,
                'overdue' => $t->follow_up_on?->toDateString() < $today,
                'tags' => $t->platformTags->map(fn (PlatformTag $g) => self::tag($g))->values(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** @return array{id: int, name: string, color: string} */
    public static function tag(PlatformTag $t): array
    {
        return ['id' => $t->id, 'name' => $t->name, 'color' => $t->color];
    }

    /** @return array<string, mixed> */
    private function note(RestaurantNote $n): array
    {
        return [
            'id' => $n->id,
            'body' => $n->body,
            'author' => $n->author ? ['id' => $n->author->id, 'name' => $n->author->name] : null,
            'mine' => $n->author_id === request()->user()?->id,
            'created_at' => $n->created_at->toIso8601String(),
            'edited' => $n->updated_at->gt($n->created_at),
        ];
    }

    /** @return array{name: string, color: string} */
    private function validTag(Request $request, ?PlatformTag $tag = null): array
    {
        $data = $request->validate([
            'name' => [$tag ? 'sometimes' : 'required', 'string', 'min:1', 'max:40', Rule::unique('platform_tags', 'name')->ignore($tag?->id)],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        return array_filter(['name' => isset($data['name']) ? trim($data['name']) : null, 'color' => isset($data['color']) ? strtolower($data['color']) : null], fn ($v) => $v !== null);
    }
}
