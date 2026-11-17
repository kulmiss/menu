<?php

namespace App\Support\SignUp;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Notifications\SignupNotification;
use App\Rules\ValidSlug;
use App\Support\PlatformSettings;
use App\Support\Security\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The self sign-up form's checks (owner, 2026-11-13), for the sign-up page
 * and the JSON API alike. The answer never tells whether an e-mail already
 * has an account: that address gets an e-mail ("you already have an
 * account") and the caller shows the same "check your inbox".
 */
class SignUpForm
{
    use PasswordValidationRules;

    public function __construct(private readonly SignUp $signUp, private readonly Turnstile $turnstile) {}

    /**
     * The new owner (with their restaurant, e-mail confirmation sent), or null
     * when nothing was created (address already known, or a bot).
     *
     * @throws ValidationException
     */
    public function submit(Request $request): ?User
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $request->merge(['email' => $email, 'slug' => Str::lower(trim((string) $request->input('slug')))]);

        // Per address and per network, on top of the route's per-minute limit.
        foreach (['signup:ip:'.$request->ip() => 10, 'signup:email:'.sha1($email) => 3] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                SecurityLog::event('signup.blocked', null, $email, ['why' => 'too_many']);
                abort(429, __('signup.too_many'));
            }
            RateLimiter::hit($key, 3600);
        }

        // Honeypot: a field people never see. Bots that fill it get the normal answer and nothing happens.
        if (filled($request->input('website'))) {
            return null;
        }

        // Shortest form (owner, 2026-11-17): name, restaurant, e-mail, password and the
        // terms. The menu address and currency are optional: made from the name / the
        // platform's default, both changeable later. Older forms still send them.
        $data = Validator::make($request->all(), [
            'restaurant_name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', new ValidSlug],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => $request->has('password_confirmation')
                ? $this->passwordRules((string) $request->input('email'))
                : array_values(array_filter($this->passwordRules((string) $request->input('email')), fn ($rule) => $rule !== 'confirmed')),
            'currency' => ['nullable', Rule::in(config('menu.currencies'))],
            'terms' => ['accepted'],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ])->validate();

        if (! $this->turnstile->passes((string) $request->input('turnstile'), $request->ip())) {
            SecurityLog::event('signup.blocked', null, $data['email'], ['why' => 'captcha']);
            throw ValidationException::withMessages(['turnstile' => __('signup.captcha')]);
        }

        $domain = Str::after($data['email'], '@');
        if (in_array($domain, (array) PlatformSettings::get('disposable_domains'), true)) {
            SecurityLog::event('signup.blocked', null, $data['email'], ['why' => 'disposable']);
            throw ValidationException::withMessages(['email' => __('signup.email_not_allowed')]);
        }

        $existing = User::where('email', $data['email'])->first();
        if ($existing !== null) {
            $existing->notify(new SignupNotification('existing'));

            return null;
        }

        $data['slug'] = ($data['slug'] ?? '') !== '' ? $data['slug'] : SlugMaker::from($data['restaurant_name']);
        $data['currency'] = ($data['currency'] ?? '') !== '' ? $data['currency'] : SignUp::currency();
        $user = $this->signUp->create($data);
        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Wrong address: an unverified owner can correct it, and a new link goes out.
     *
     * @throws ValidationException
     */
    public function changeEmail(Request $request): User
    {
        /** @var User $user */
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

        return $user;
    }
}
