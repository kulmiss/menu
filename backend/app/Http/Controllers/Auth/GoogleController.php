<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\GoogleLogin;
use App\Support\PlatformSettings;
use App\Support\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with Google" on the website (owner, 2026-11-17). Socialite does
 * the OAuth dance with a one-time state in the session (CSRF). Rules: see
 * App\Support\Auth\GoogleLogin. Errors go back to the sign-in page as
 * ?google=<code>; nothing from Google (code, token) is ever logged.
 */
class GoogleController extends Controller
{
    private const PENDING = 'google.pending';

    private const INTENT = 'google.intent';

    public function __construct(private readonly GoogleLogin $google) {}

    /** /auth/google (sign in or sign up) and /auth/google?link=1 (Account → Security). */
    public function redirect(Request $request): SymfonyRedirect
    {
        abort_unless(GoogleLogin::enabled(), 404);
        $link = $request->boolean('link');
        if ($link && (Auth::guard('web')->guest() || Auth::guard('web')->user()?->is_super_admin)) {
            return redirect('/signin');
        }
        $request->session()->put(self::INTENT, $link ? 'link' : 'signin');

        $google = Socialite::driver('google');
        if (! $google instanceof GoogleProvider) {
            throw new \LogicException('The "google" Socialite driver is not Google.');
        }

        return $google->scopes(['openid', 'email', 'profile'])->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleLogin::enabled(), 404);
        $link = $request->session()->pull(self::INTENT) === 'link';
        $fail = fn (string $code) => redirect($link ? '/admin/settings/security?google='.$code : '/signin?google='.$code);

        if ($request->filled('error')) {
            return $fail('cancelled');
        }
        try {
            $answer = Socialite::driver('google')->user();
            $profile = GoogleLogin::profile($answer instanceof AbstractUser ? $answer->getRaw() : []);
        } catch (InvalidStateException) {
            SecurityLog::event('google.refused', null, null, ['why' => 'state']);

            return $fail('failed');
        } catch (Throwable $e) {
            SecurityLog::event('google.refused', null, null, ['why' => 'google_error', 'error' => class_basename($e)]);

            return $fail('failed');
        }

        if ($link) {
            return $this->linkCurrent($profile);
        }

        $outcome = $this->google->resolve($profile);
        if ($outcome['result'] === 'refused') {
            return $fail($outcome['why']);
        }
        if ($outcome['result'] === 'new') {
            if (! (bool) PlatformSettings::get('signup_open')) {
                return redirect('/auth/sign-up?google=closed');
            }
            $request->session()->put(self::PENDING, [...$profile, 'at' => now()->timestamp]);

            return redirect('/auth/google/finish');
        }

        $user = $outcome['user'];
        $next = '/admin'.($outcome['linked'] ? '?google=linked' : '');
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            // The same two-factor step as a password sign-in (Fortify reads login.id).
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            $request->session()->put('url.intended', $next);

            return redirect('/signin/two-factor');
        }
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        GoogleLogin::signedIn($user, false);

        return redirect($next);
    }

    /** The Google profile waiting for its restaurant name (the "Almost done" page). */
    public function pending(Request $request): JsonResponse
    {
        abort_unless(GoogleLogin::enabled(), 404);
        $pending = $this->pendingProfile($request);
        abort_if($pending === null, 404);
        $s = PlatformSettings::all();

        return response()->json(['data' => [
            'email' => $pending['email'],
            'name' => $pending['name'],
            'terms' => trim((string) $s['terms']) !== '',
            'privacy' => trim((string) $s['privacy']) !== '',
        ]]);
    }

    /** Restaurant name + Terms → the account and restaurant exist, signed in. */
    public function finish(Request $request): JsonResponse
    {
        abort_unless(GoogleLogin::enabled(), 404);
        abort_unless((bool) PlatformSettings::get('signup_open'), 403, __('signup.closed'));
        $pending = $this->pendingProfile($request);
        if ($pending === null) {
            return response()->json(['message' => __('google.expired'), 'code' => 'google.expired'], 410);
        }
        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'min:2', 'max:120'],
            'terms' => ['accepted'],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ]);
        // Someone may have signed up with this e-mail meanwhile: sign in the normal way then.
        if (User::where('email', Str::lower($pending['email']))->orWhere('google_id', $pending['sub'])->exists()) {
            $request->session()->forget(self::PENDING);
            throw ValidationException::withMessages(['restaurant_name' => __('google.exists')]);
        }

        $user = $this->google->createOwner($pending, trim($data['restaurant_name']), $data['locale'] ?? 'en');
        $request->session()->forget(self::PENDING);
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        GoogleLogin::signedIn($user, false);

        return response()->json(['data' => ['redirect' => '/admin']], 201);
    }

    /** Account → Security → "Unlink Google". Only with a password, or the account could not sign in. */
    public function unlink(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->google_id === null) {
            return response()->json(['data' => ['linked' => false]]);
        }
        if ($user->password === null) {
            throw ValidationException::withMessages(['google' => __('google.password_first')]);
        }
        $this->google->unlink($user);

        return response()->json(['data' => ['linked' => false]]);
    }

    /**
     * Signed up with Google and no password yet: the usual "set a new
     * password" e-mail (Forgot password is for signed-out people only).
     */
    public function passwordLink(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->password === null, 409);
        Password::broker()->sendResetLink(['email' => $user->email]);
        SecurityLog::event('password.link_sent', $user->id, null, ['why' => 'google_only']);

        return response()->json(['data' => ['sent_to' => $user->email]]);
    }

    /** @param  array{sub: string, email: string, email_verified: bool, name: string}  $profile */
    private function linkCurrent(array $profile): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $back = fn (string $code) => redirect('/admin/settings/security?google='.$code);
        if (! $user instanceof User || $user->is_super_admin) {
            return redirect('/signin');
        }
        if (! $profile['email_verified']) {
            SecurityLog::event('google.refused', $user->id, $profile['email'], ['why' => 'unverified']);

            return $back('unverified');
        }
        // A Google account (or its e-mail) that belongs to someone else stays theirs.
        $taken = User::whereKeyNot($user->id)->where(fn ($q) => $q->where('google_id', $profile['sub'])->orWhere('email', Str::lower($profile['email'])))->exists();
        if ($taken) {
            SecurityLog::event('google.refused', $user->id, $profile['email'], ['why' => 'taken']);

            return $back('taken');
        }
        $this->google->link($user, $profile);

        return $back('linked');
    }

    /** @return array{sub: string, email: string, email_verified: bool, name: string}|null */
    private function pendingProfile(Request $request): ?array
    {
        $p = $request->session()->get(self::PENDING);
        if (! is_array($p) || ($p['at'] ?? 0) < now()->subMinutes(GoogleLogin::PENDING_MINUTES)->timestamp) {
            return null;
        }

        return ['sub' => (string) $p['sub'], 'email' => (string) $p['email'], 'email_verified' => (bool) $p['email_verified'], 'name' => (string) $p['name']];
    }
}
