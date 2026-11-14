<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The link sent to a new e-mail address (SECURITY_REPORT.md H3). Single use, one hour. */
class ConfirmEmailChangeController extends Controller
{
    public function __invoke(Request $request, int $user, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect('/auth/sign-in?verify=change_expired');
        }
        $model = User::find($user);
        if ($model === null || ! UpdateUserProfileInformation::confirm($model, $hash)) {
            return redirect('/auth/sign-in?verify=change_invalid');
        }

        return redirect('/auth/sign-in?verify=change_done');
    }
}
