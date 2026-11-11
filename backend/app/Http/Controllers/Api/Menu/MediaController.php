<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Tenant;
use App\Support\ImageProcessor;
use App\Support\MediaJanitor;
use App\Support\PlanLimits;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Photo upload. The browser has already cropped and downscaled the image
 * (QUESTIONS #45); the server checks it and writes the sizes with GD.
 */
class MediaController extends Controller
{
    use RecordsChanges;

    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(Request $request, CurrentTenant $current, PlanLimits $limits, ImageProcessor $images): JsonResponse
    {
        Gate::authorize('menu.manage');
        $request->validate(['file' => ['required', 'file', 'max:8192'], 'purpose' => ['sometimes', 'in:banner']]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::TYPES, true)) {
            $heic = in_array($mime, ['image/heic', 'image/heif'], true) || preg_match('/\.hei[cf]$/i', (string) $file->getClientOriginalName());
            throw ValidationException::withMessages(['file' => __($heic ? 'menu.media.heic' : 'menu.media.type')]);
        }

        $limits->ensureRoom('photos', Media::count());

        $folder = $current->id().'/'.Str::lower((string) Str::ulid());
        try {
            $result = $images->process((string) $file->getRealPath(), $mime, 'public', $folder, $request->input('purpose') === 'banner');
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('menu.media.unreadable')]);
        }

        $media = Media::create([
            ...$result,
            'disk' => 'public',
            'base_path' => $folder,
            'mime' => $mime,
            'bytes' => (int) $file->getSize(),
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $this->recordChange('media.uploaded', $media);

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    /** Deletes a photo not used by any item or category. */
    public function destroy(Tenant $tenant, int $media): Response
    {
        Gate::authorize('menu.manage');
        $model = Media::findOrFail($media);
        MediaJanitor::releaseIfUnused($model->id);

        return response()->noContent();
    }
}
