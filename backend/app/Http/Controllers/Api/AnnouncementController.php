<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\PlatformController;
use App\Models\PlatformAnnouncement;
use App\Models\Tenant;
use App\Support\ImpersonationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Platform announcements shown on a restaurant's dashboard, and "got it";
 * plus "Back to platform" at the end of a "log in as" session.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $read = DB::table('announcement_reads')->where('user_id', $request->user()->id)->pluck('platform_announcement_id');
        $rows = PlatformAnnouncement::query()->showingFor($tenant->plan_id)->whereNotIn('id', $read)->latest('id')->limit(5)->get();

        return response()->json(['data' => $rows->map(fn ($a) => PlatformController::announcementRow($a))]);
    }

    public function read(Request $request, Tenant $tenant, int $announcement): Response
    {
        PlatformAnnouncement::query()->showingFor($tenant->plan_id)->findOrFail($announcement);
        DB::table('announcement_reads')->insertOrIgnore(['platform_announcement_id' => $announcement, 'user_id' => $request->user()->id, 'read_at' => now()]);

        return response()->noContent();
    }

    public function stopImpersonating(Request $request, ImpersonationManager $manager): JsonResponse
    {
        abort_unless($request->session()->has('impersonation_id'), 404);
        $manager->stop($request);

        return response()->json(['data' => ['redirect' => '/platform']]);
    }
}
