<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\MenuRequest;
use App\Notifications\MenuReadyNotification;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Super Admin → Menu requests (QUESTIONS #235): see the photos, mark the work, tell the owner when done. */
class MenuRequestController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $rows = MenuRequest::with(['tenant:id,name,slug', 'user:id,name,email'])
            ->when(in_array($status, MenuRequest::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'new', 'working', 'done')")->latest('id')->limit(200)->get();

        return response()->json(['data' => [
            'open' => MenuRequest::where('status', '!=', 'done')->count(),
            'requests' => $rows->map(fn (MenuRequest $r) => self::row($r))->values(),
        ]]);
    }

    public function update(Request $request, MenuRequest $menuRequest): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(MenuRequest::STATUSES)]]);
        $was = $menuRequest->status;
        $menuRequest->forceFill(['status' => $data['status'], 'handled_by' => $request->user()->id])->save();
        $this->activity->log('platform.menu_request_updated', $menuRequest, ['status' => ['from' => $was, 'to' => $data['status']]], $menuRequest->tenant_id);
        if ($data['status'] === 'done' && $was !== 'done' && $menuRequest->user !== null && $menuRequest->tenant !== null) {
            $menuRequest->user->notify(new MenuReadyNotification($menuRequest->tenant));
        }

        return response()->json(['data' => self::row($menuRequest->load(['tenant:id,name,slug', 'user:id,name,email']))]);
    }

    /** One photo or PDF, only to a Super Admin, never cached by a browser or proxy. */
    public function file(MenuRequest $menuRequest, int $n): StreamedResponse
    {
        $file = $menuRequest->files[$n] ?? null;
        abort_if($file === null || ! Storage::disk('local')->exists($menuRequest->dir().'/'.$file['file']), 404);

        return Storage::disk('local')->response($menuRequest->dir().'/'.$file['file'], $file['name'] ?: $file['file'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], 'inline');
    }

    /** Delete the request and its photos (after the menu was typed in). */
    public function destroy(MenuRequest $menuRequest): JsonResponse
    {
        Storage::disk('local')->deleteDirectory($menuRequest->dir());
        $this->activity->log('platform.menu_request_deleted', null, ['id' => ['from' => $menuRequest->id, 'to' => null]], $menuRequest->tenant_id);
        $menuRequest->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private static function row(MenuRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'note' => $r->note,
            'created_at' => $r->created_at->toIso8601String(),
            'restaurant' => $r->tenant ? ['id' => $r->tenant->id, 'name' => $r->tenant->name, 'slug' => $r->tenant->slug] : null,
            'user' => $r->user ? ['name' => $r->user->name, 'email' => $r->user->email] : null,
            'files' => array_map(fn (array $f, int $i) => ['n' => $i, 'name' => $f['name'], 'mime' => $f['mime'], 'bytes' => $f['bytes']], $r->files, array_keys($r->files)),
        ];
    }
}
