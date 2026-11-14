<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Support\ImpersonationManager;
use App\Support\Security\OwnerTwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user and every restaurant they belong to (restaurant switcher). */
class MeController extends Controller
{
    public function __invoke(Request $request, ImpersonationManager $impersonation): JsonResponse
    {
        $user = $request->user();
        // whereHas skips restaurants that were deleted (soft delete).
        $memberships = $user->memberships()->whereHas('tenant')->with('tenant')->get();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // A new address waiting for its confirmation link (SECURITY_REPORT.md H3).
                'pending_email' => $user->pending_email,
                // On the common-password list: a gentle "please change it" banner (SECURITY_REPORT.md M3).
                'password_weak' => (bool) $user->password_weak,
                'locale' => $user->locale,
                'email_verified' => $user->hasVerifiedEmail(),
                'news_subscribed' => $user->news_unsubscribed_at === null,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                // The Super Admin's switch is on and this Owner has no two-factor yet (M17).
                'two_factor_required' => ! $impersonation->active($request) && OwnerTwoFactor::required($user),
                'is_super_admin' => $user->is_super_admin,
                // Set while a Super Admin is "logged in as" this user (red banner).
                'impersonation' => $impersonation->current($request),
                'tenants' => $memberships->values()->map(
                    fn ($m) => (new TenantResource($m->tenant, $m))->resolve($request)
                ),
            ],
        ]);
    }
}
