<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * "Sign out of all other browsers" (SECURITY_REPORT.md L7): this browser stays
 * signed in; every other website session and "remember me" ends. The app has
 * its own device list (Account → Devices).
 */
class SessionController extends Controller
{
    public function logoutOthers(Request $request): JsonResponse
    {
        abort_unless($request->hasSession() && Auth::guard('web')->check(), 400);
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);
        $user = $request->user();
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        Auth::guard('web')->logoutOtherDevices($data['password']);
        // The new hash goes into this session, so this browser stays in.
        $request->session()->put('password_hash_web', $user->fresh()->getAuthPassword());
        SecurityLog::event('tokens.revoked', $user->id, null, ['why' => 'other_browsers']);

        return response()->json(['message' => __('security.sessions_ended')]);
    }
}
