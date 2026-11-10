<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user and every restaurant they belong to (restaurant switcher). */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        // whereHas skips restaurants that were deleted (soft delete).
        $memberships = $user->memberships()->whereHas('tenant')->with('tenant')->get();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'email_verified' => $user->hasVerifiedEmail(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'is_super_admin' => $user->is_super_admin,
                'tenants' => $memberships->values()->map(
                    fn ($m) => (new TenantResource($m->tenant, $m))->resolve($request)
                ),
            ],
        ]);
    }
}
