<?php

namespace App\Support;

use App\Models\Impersonation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" (PLAN.md §5): the session switches to the restaurant's owner;
 * the Super Admin's id stays in the session so every action is logged with
 * impersonator_id, and "Back to platform" (or 60 minutes) switches back.
 */
class ImpersonationManager
{
    public function start(Request $request, User $admin, Tenant $tenant, User $target, string $reason): Impersonation
    {
        // Only from the browser app (cookie session), never with an API token.
        abort_unless($request->hasSession(), 400);
        $record = Impersonation::create([
            'super_admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'reason' => $reason,
            'started_at' => now(),
            'ip_hash' => IpHash::make($request->ip()),
        ]);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put([
            'impersonation_id' => $record->id,
            'impersonator_id' => $admin->id,
            'impersonation_ends_at' => now()->addMinutes(Impersonation::MINUTES)->getTimestamp(),
        ]);

        return $record;
    }

    public function expired(Request $request): bool
    {
        return now()->getTimestamp() >= (int) $request->session()->get('impersonation_ends_at', 0);
    }

    /** Ends "log in as" and signs the Super Admin back in. */
    public function stop(Request $request): ?User
    {
        $id = $request->session()->get('impersonation_id');
        $adminId = $request->session()->get('impersonator_id');
        if ($id === null) {
            return null;
        }
        Impersonation::whereKey($id)->whereNull('ended_at')->update(['ended_at' => now()]);
        $request->session()->forget(['impersonation_id', 'impersonator_id', 'impersonation_ends_at']);

        $admin = User::find($adminId);
        if ($admin === null) {
            Auth::guard('web')->logout();

            return null;
        }
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return $admin;
    }

    /** @return array{tenant_id: int, user_name: string, ends_at: string}|null for the red banner */
    public function current(Request $request): ?array
    {
        if (! $request->hasSession() || ! $request->session()->has('impersonation_id')) {
            return null;
        }
        $record = Impersonation::find($request->session()->get('impersonation_id'));

        return $record === null ? null : [
            'tenant_id' => $record->tenant_id,
            'user_name' => (string) $request->user()?->name,
            'tenant_name' => (string) Tenant::whereKey($record->tenant_id)->value('name'),
            'ends_at' => date(DATE_ATOM, (int) $request->session()->get('impersonation_ends_at')),
        ];
    }
}
