<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SignupNotification;
use App\Rules\ValidSlug;
use App\Support\PlatformSettings;
use App\Support\SignUp\SignUp;
use App\Support\SignUp\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'default_currency' => $s['default_currency'] ?: 'ETB',
            'terms' => trim((string) $s['terms']) !== '',
            'privacy' => trim((string) $s['privacy']) !== '',
            'platform' => $s['name'],
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

    public function store(Request $request, SignUp $signUp, Turnstile $turnstile): JsonResponse
    {
        abort_unless((bool) PlatformSettings::get('signup_open'), 403, __('signup.closed'));

        $email = Str::lower(trim((string) $request->input('email')));
        $request->merge(['email' => $email, 'slug' => Str::lower(trim((string) $request->input('slug')))]);

        // Per address and per network, on top of the route's per-minute limit.
        foreach (['signup:ip:'.$request->ip() => 10, 'signup:email:'.sha1($email) => 3] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                abort(429, __('signup.too_many'));
            }
            RateLimiter::hit($key, 3600);
        }

        // Honeypot: a field people never see. Bots that fill it get the normal answer and nothing happens.
        if (filled($request->input('website'))) {
            return $this->checkInbox();
        }

        $data = Validator::make($request->all(), [
            'restaurant_name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['required', new ValidSlug],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => $this->passwordRules(),
            'currency' => ['required', Rule::in(config('menu.currencies'))],
            'terms' => ['accepted'],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ])->validate();

        if (! $turnstile->passes((string) $request->input('turnstile'), $request->ip())) {
            throw ValidationException::withMessages(['turnstile' => __('signup.captcha')]);
        }

        $domain = Str::after($data['email'], '@');
        if (in_array($domain, (array) PlatformSettings::get('disposable_domains'), true)) {
            throw ValidationException::withMessages(['email' => __('signup.email_not_allowed')]);
        }

        $existing = User::where('email', $data['email'])->first();
        if ($existing !== null) {
            $existing->notify(new SignupNotification('existing'));

            return $this->checkInbox();
        }

        $user = $signUp->create($data);
        $user->sendEmailVerificationNotification();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->checkInbox();
    }

    /** Wrong address: an unverified owner can correct it, and a new link goes out. */
    public function changeEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasVerifiedEmail(), 409);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        if (in_array(Str::after($data['email'], '@'), (array) PlatformSettings::get('disposable_domains'), true)) {
            throw ValidationException::withMessages(['email' => __('signup.email_not_allowed')]);
        }
        if (User::where('email', $data['email'])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => __('signup.email_unavailable')]);
        }
        $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();

        return response()->json(['data' => ['email' => $user->email]]);
    }

    private function checkInbox(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'check_inbox']], 201);
    }
}
