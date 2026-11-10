<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\Tenant;
use App\Rules\Translations;
use App\Support\ActivityLogger;
use App\Support\SystemTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Built-in tags (vegan, spicy…) plus the restaurant's own. */
class TagController extends Controller
{
    use RecordsChanges;

    public function index(): AnonymousResourceCollection
    {
        // Restaurants created before tags existed get them on first look.
        SystemTags::ensure();

        return TagResource::collection(Tag::orderByRaw('`key` is null')->orderBy('sort')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('menu.manage');
        $data = $request->validate(['name' => ['required', Translations::required(40)]]);

        $tag = Tag::create([...$data, 'sort' => $this->nextSort(Tag::whereNull('key'))]);
        $this->recordChange('tag.created', $tag);

        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, int $tag): TagResource
    {
        Gate::authorize('menu.manage');
        $model = $this->custom($tag);
        $model->fill($request->validate(['name' => ['required', Translations::required(40)]]))->save();
        $this->recordChange('tag.updated', $model, ActivityLogger::diff($model));

        return new TagResource($model);
    }

    public function destroy(Tenant $tenant, int $tag): Response
    {
        Gate::authorize('menu.manage');
        $model = $this->custom($tag);
        $model->delete();
        $this->recordChange('tag.deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    private function custom(int $id): Tag
    {
        $tag = Tag::findOrFail($id);

        if ($tag->isSystem()) {
            throw ValidationException::withMessages(['tag' => __('menu.menu_editor.system_tag')]);
        }

        return $tag;
    }
}
