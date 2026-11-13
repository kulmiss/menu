<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" (owner, 2026-11-13): one click from the Super Admin, no reason,
 * no time limit, no blocked actions and nothing recorded in the activity log.
 * The session only remembers who to switch back to ("Back to platform") and
 * which restaurant the "Viewing as" badge names.
 */
class ImpersonationManager
{
    public function start(Request $request, User $admin, Tenant $tenant, User $target): void
    {
        // Only from the browser app (cookie session), never with an API token.
        abort_unless($request->hasSession(), 400);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put([
            // Kept as a flag: the platform area stays closed while viewing as someone.
            'impersonation_id' => $tenant->id,
            'impersonator_id' => $admin->id,
        ]);
    }

    /** Ends "log in as" and signs the Super Admin back in. */
    public function stop(Request $request): ?User
    {
        $adminId = $request->session()->get('impersonator_id');
        if (! $request->session()->has('impersonation_id')) {
            return null;
        }
        $request->session()->forget(['impersonation_id', 'impersonator_id']);

        $admin = User::find($adminId);
        if ($admin === null) {
            Auth::guard('web')->logout();

            return null;
        }
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return $admin;
    }

    /** @return array{tenant_id: int, user_name: string, tenant_name: string}|null for the "Viewing as" badge */
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
        ];
    }
}
