<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Profile → Devices: where this account is signed in to the app (QUESTIONS #187). */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $devices = $user->tokens()->latest('last_used_at')->latest('id')->get()
            ->filter(fn (PersonalAccessToken $t) => $t->isFresh($user))
            ->values()
            ->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'platform' => $t->platform,
                'app_version' => $t->app_version,
                'last_used_at' => $t->last_used_at?->toAtomString(),
                'created_at' => $t->created_at?->toAtomString(),
                'current' => $current instanceof PersonalAccessToken && $current->is($t),
            ]);

        return response()->json(['data' => $devices]);
    }

    public function destroy(Request $request, int $device): Response
    {
        $request->user()->tokens()->whereKey($device)->firstOrFail()->delete();

        return response()->noContent();
    }
}
