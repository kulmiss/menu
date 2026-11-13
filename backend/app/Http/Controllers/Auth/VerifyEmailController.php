<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The link in "Confirm your e-mail" (owner, 2026-11-13). Works without being
 * signed in on that device; expired or changed links go back to sign-in with
 * a note, and a used link just says the address is already confirmed.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect('/auth/sign-in?verify=expired');
        }
        $user = User::find($id);
        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect('/auth/sign-in?verify=invalid');
        }
        $signedIn = $request->user()?->is($user) ?? false;

        if ($user->hasVerifiedEmail()) {
            return redirect($signedIn ? '/admin' : '/auth/sign-in?verify=already');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect($signedIn ? '/admin?welcome=1' : '/auth/sign-in?verify=done');
    }
}
