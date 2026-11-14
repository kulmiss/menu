<?php

namespace App\Support;

use App\Models\Impersonation;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" (owner, 2026-11-13, QUESTIONS #170): one click, no reason, and
 * nothing recorded: no impersonation row, no activity-log line, and changes
 * made meanwhile count as the owner's (no Super Admin attached). The session
 * only remembers who to switch back to and when it ends (2 hours);
 * GuardImpersonation keeps the safety blocks.
 */
class ImpersonationManager
{
    public function start(Request $request, User $admin, Tenant $tenant, User $target): void
    {
        // Only from the browser app (cookie session), never with an API token.
        abort_unless($request->hasSession(), 400);

        LastSignIn::quietly(fn () => Auth::guard('web')->login($target));
        $request->session()->regenerate();
        $request->session()->put([
            // The restaurant's id: the "Viewing as" badge, and the flag that keeps the platform area closed.
            'impersonation_id' => $tenant->id,
            'impersonator_id' => $admin->id,
            'impersonation_ends_at' => now()->addMinutes(Impersonation::MINUTES)->getTimestamp(),
        ]);
    }

    /**
     * "Log in as" from the mobile app (QUESTIONS #188): a 2-hour token for the
     * owner. The Super Admin's own token stays on the phone for "back to platform".
     *
     * @return array{token: string, tenant_id: int, ends_at: string}
     */
    public function startToken(User $admin, Tenant $tenant, User $target): array
    {
        $ends = now()->addMinutes(Impersonation::MINUTES);
        $new = $target->createToken('Log in as', ['mobile'], $ends);
        $new->accessToken->forceFill(['impersonator_id' => $admin->id, 'impersonation_tenant_id' => $tenant->id, 'platform' => 'support'])->save();

        return ['token' => $new->plainTextToken, 'tenant_id' => $tenant->id, 'ends_at' => $ends->toAtomString()];
    }

    /** The "log in as" token of this request, if it is one. */
    public function token(Request $request): ?PersonalAccessToken
    {
        // The sanctum guard: this also runs as group middleware, before auth:sanctum.
        $token = $request->user('sanctum')?->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->isImpersonation() ? $token : null;
    }

    /** Session ("log in as" on the web) or token (the app). */
    public function active(Request $request): bool
    {
        return ($request->hasSession() && $request->session()->has('impersonation_id')) || $this->token($request) !== null;
    }

    public function expired(Request $request): bool
    {
        return now()->getTimestamp() >= (int) $request->session()->get('impersonation_ends_at', 0);
    }

    /** Ends "log in as" and signs the Super Admin back in. */
    public function stop(Request $request): ?User
    {
        if (! $request->session()->has('impersonation_id')) {
            return null;
        }
        $adminId = $request->session()->get('impersonator_id');
        $request->session()->forget(['impersonation_id', 'impersonator_id', 'impersonation_ends_at']);

        $admin = User::find($adminId);
        if ($admin === null) {
            Auth::guard('web')->logout();

            return null;
        }
        LastSignIn::quietly(fn () => Auth::guard('web')->login($admin));
        $request->session()->regenerate();

        return $admin;
    }

    /** @return array{tenant_id: int, user_name: string, tenant_name: string, ends_at: string}|null for the "Viewing as" badge */
    public function current(Request $request): ?array
    {
        if (($token = $this->token($request)) !== null) {
            return [
                'tenant_id' => (int) $token->impersonation_tenant_id,
                'user_name' => (string) $request->user('sanctum')?->name,
                'tenant_name' => (string) Tenant::whereKey($token->impersonation_tenant_id)->value('name'),
                'ends_at' => (string) $token->expires_at?->toAtomString(),
            ];
        }
        if (! $request->hasSession() || ! $request->session()->has('impersonation_id')) {
            return null;
        }
        $tenantId = (int) $request->session()->get('impersonation_id');

        return [
            'tenant_id' => $tenantId,
            'user_name' => (string) $request->user()?->name,
            'tenant_name' => (string) Tenant::whereKey($tenantId)->value('name'),
            'ends_at' => date(DATE_ATOM, (int) $request->session()->get('impersonation_ends_at')),
        ];
    }
}
