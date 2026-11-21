<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Rules\ValidSlug;
use App\Support\Auth\GoogleLogin;
use App\Support\PlatformSettings;
use App\Support\SignUp\SignUp;
use App\Support\SignUp\SignUpForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public self sign-up (owner, 2026-11-13). Answers never tell whether an
 * e-mail address already has an account: in that case the address gets an
 * e-mail ("you already have an account") and the page shows the same
 * "check your inbox" as for a new account.
 */
class SignUpController extends Controller
{
    use PasswordValidationRules;

    /** What the sign-up page needs before showing the form. */
    public function config(): JsonResponse
    {
        $s = PlatformSettings::all();

        return response()->json(['data' => [
            'open' => (bool) $s['signup_open'],
            'turnstile_site_key' => $s['turnstile_enabled'] ? $s['turnstile_site_key'] : null,
            'currencies' => config('menu.currencies'),
            'default_currency' => SignUp::currency(),
            'terms' => trim((string) $s['terms']) !== '',
            'privacy' => trim((string) $s['privacy']) !== '',
            'platform' => $s['name'],
            // "Continue with Google" on the sign-up and sign-in pages (owner, 2026-11-17).
            'google' => GoogleLogin::enabled(),
        ]]);
    }

    /** Live "available / taken" check for the menu address. */
    public function slug(Request $request): JsonResponse
    {
        $slug = Str::lower(trim((string) $request->query('slug')));
        $error = Validator::make(['slug' => $slug], ['slug' => [new ValidSlug]])->errors()->first('slug');
        $reason = match (true) {
            $error === '' => null,
            $error === __('menu.slug.reserved') => 'reserved',
            $error === __('menu.slug.taken') => 'taken',
            default => 'invalid',
        };

        return response()->json(['data' => ['slug' => $slug, 'available' => $reason === null, 'reason' => $reason, 'message' => $error ?: null]]);
    }

    public function store(Request $request, SignUpForm $form): JsonResponse
    {
        abort_unless((bool) PlatformSettings::get('signup_open'), 403, __('signup.closed'));
        $user = $form->submit($request);
        if ($user !== null) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return $this->checkInbox();
    }

    /** Wrong address: an unverified owner can correct it, and a new link goes out. */
    public function changeEmail(Request $request, SignUpForm $form): JsonResponse
    {
        $user = $form->changeEmail($request);

        return response()->json(['data' => ['email' => $user->email]]);
    }

    private function checkInbox(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'check_inbox']], 201);
    }
}
