<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\MenuRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MenuRequestNotification;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Add my menu for me" (owner, 2026-11-19, QUESTIONS #235): an owner or
 * manager sends photos (or a PDF) of the paper menu; every Super Admin is told,
 * and sees the request in Super Admin → Menu requests.
 */
class MenuRequestController extends Controller
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public function index(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');

        return response()->json(['data' => [
            'enabled' => (bool) PlatformSettings::get('menu_help_enabled'),
            'requests' => MenuRequest::where('tenant_id', $tenant->id)->latest('id')->limit(10)->get()
                ->map(fn (MenuRequest $r) => ['id' => $r->id, 'status' => $r->status, 'files' => count($r->files), 'note' => $r->note, 'created_at' => $r->created_at->toIso8601String()]),
        ]]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        abort_unless((bool) PlatformSettings::get('menu_help_enabled'), 404);
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.MenuRequest::MAX_FILES],
            'files.*' => ['required', 'file', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        if (MenuRequest::where('tenant_id', $tenant->id)->where('status', '!=', 'done')->count() >= MenuRequest::MAX_OPEN) {
            throw ValidationException::withMessages(['files' => __('menu_request.too_many')]);
        }
        // The real type from the bytes, never the name or the browser's word for it.
        foreach ($data['files'] as $i => $file) {
            if (self::type($file) === null) {
                throw ValidationException::withMessages(["files.{$i}" => __('menu_request.type')]);
            }
        }

        $menuRequest = DB::transaction(function () use ($data, $tenant, $request) {
            $r = MenuRequest::create(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'note' => $data['note'] ?? null, 'files' => [], 'status' => 'new']);
            $files = [];
            foreach ($data['files'] as $n => $file) {
                $mime = (string) self::type($file);
                $name = sprintf('%02d-%s.%s', $n + 1, Str::random(12), self::TYPES[$mime]);
                Storage::disk('local')->putFileAs($r->dir(), $file, $name);
                $files[] = ['name' => mb_substr((string) $file->getClientOriginalName(), 0, 120), 'file' => $name, 'mime' => $mime, 'bytes' => (int) $file->getSize()];
            }
            $r->forceFill(['files' => $files])->save();

            return $r;
        });

        $admins = User::where('is_super_admin', true)->get();
        Notification::send($admins, new MenuRequestNotification($menuRequest->id, $tenant->name, count($menuRequest->files)));

        return response()->json(['data' => ['id' => $menuRequest->id, 'status' => 'new', 'files' => count($menuRequest->files)]], 201);
    }

    /** image/jpeg, image/png, image/webp or application/pdf, judged by the content. */
    public static function type(UploadedFile $file): ?string
    {
        $path = (string) $file->getRealPath();
        $head = (string) @file_get_contents($path, false, null, 0, 5);
        if ($head === '%PDF-') {
            return 'application/pdf';
        }
        $info = @getimagesize($path);
        $mime = is_array($info) ? (string) $info['mime'] : '';

        return isset(self::TYPES[$mime]) && $mime !== 'application/pdf' ? $mime : null;
    }
}
