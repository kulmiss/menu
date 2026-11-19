<?php

namespace App\Http\Controllers;

use App\Support\Ops\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /health for uptime monitors (QUESTIONS #233): the overall state of the
 * menu:doctor checks, never the details (those are for the Super Admin and
 * the server). 503 when something is broken, so a monitor alerts.
 */
class HealthController extends Controller
{
    public function __invoke(Doctor $doctor): JsonResponse
    {
        $status = Cache::remember('health:status', 60, fn () => Doctor::overall($doctor->run(quick: true)));

        return response()->json(['status' => $status], $status === 'down' ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
