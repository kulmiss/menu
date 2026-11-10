<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Invitation;
use App\Models\Membership;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invitations are opened by token before any restaurant is current, so the
 * lookup deliberately bypasses the tenant scope — by token hash only.
 */
class AcceptInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = $this->find($token);

        return response()->json(['data' => [
            'restaurant' => $invitation->tenant->name,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]]);
    }

    public function accept(Request $request, string $token, ActivityLogger $activity): JsonResponse
    {
        $invitation = $this->find($token);
        $user = $request->user();

        abort_unless(Str::lower($user->email) === $invitation->email, 403, __('menu.invitation.wrong_account'));

        $membership = DB::transaction(function () use ($invitation, $user) {
            $membership = Membership::firstOrCreate(
                ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'can_edit_prices' => $invitation->can_edit_prices, 'invited_by' => $invitation->invited_by],
            );

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $membership;
        });

        $activity->log('invitation.accepted', $invitation, ['user_id' => $user->id], $invitation->tenant_id);

        return (new TenantResource($invitation->tenant, $membership))->response();
    }

    private function find(string $token): Invitation
    {
        $invitation = Invitation::withoutTenancy()
            ->whereHas('tenant')
            ->with('tenant')
            ->where('token_hash', Invitation::hashToken($token))
            ->first();

        abort_if($invitation === null || ! $invitation->isOpen(), 404);

        return $invitation;
    }
}
