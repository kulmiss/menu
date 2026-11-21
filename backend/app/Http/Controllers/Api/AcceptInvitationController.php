<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Push\Push;
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
        $membership = self::join($invitation, $request->user(), $activity);

        return (new TenantResource($invitation->tenant, $membership))->response();
    }

    /** The signed-in person joins the inviting restaurant (also the invitation page, QUESTIONS #222). */
    public static function join(Invitation $invitation, User $user, ActivityLogger $activity): Membership
    {
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
        // Tell the owners on their phones (QUESTIONS #189).
        Push::send(collect(Push::team($invitation->tenant_id))->reject(fn ($u) => $u->id === $user->id), 'invitation_accepted',
            __('push.invitation_accepted.title', ['name' => $user->name]),
            __('push.invitation_accepted.body', ['name' => $user->name, 'role' => $membership->role->value, 'restaurant' => $invitation->tenant->name]),
            "/r/{$invitation->tenant_id}/team", $invitation->tenant_id);

        return $membership;
    }

    /** An open invitation by its token, or null. */
    public static function open(string $token): ?Invitation
    {
        $invitation = Invitation::withoutTenancy()
            ->whereHas('tenant')
            ->with('tenant')
            ->where('token_hash', Invitation::hashToken($token))
            ->first();

        return $invitation !== null && $invitation->isOpen() ? $invitation : null;
    }

    private function find(string $token): Invitation
    {
        $invitation = self::open($token);
        abort_if($invitation === null, 404);

        return $invitation;
    }
}
