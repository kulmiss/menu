<?php

namespace App\Support;

use App\Models\Impersonation;
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
