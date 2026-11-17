<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Support\Auth\GoogleIdToken;
use App\Support\Auth\GoogleLogin;
use App\Support\MobileApp;
use App\Support\PlatformSettings;
use App\Support\Security\LoginCheck;
use App\Support\Security\RecoveryCodes;
use App\Support\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Sign-in for the mobile app with Sanctum tokens (QUESTIONS #187), separate
 * from the web app's cookie session. Two-factor users get a one-time
 * challenge (5 minutes, 5 tries) and the token only after the code.
 */
class AuthController extends Controller
{
    private const CHALLENGE_MINUTES = 5;

    private const CHALLENGE_TRIES = 5;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            ...$this->deviceRules(),
        ]);

        $user = LoginCheck::user($data['email'], $data['password']);
        if ($user === null) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        if ($closed = $this->closed($user)) {
            return $closed;
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            return $this->challenge($user, $this->device($data));
        }

        return $this->issue($user, $this->device($data));
    }

    /**
     * "Continue with Google" in the app (owner, 2026-11-17): the app sends the
     * ID token Google gave it; the same rules as the website (GoogleLogin).
     * New e-mail → {google_signup: true, ticket}: the app asks the restaurant
     * name and the Terms, then calls googleFinish.
     */
    public function google(Request $request, GoogleLogin $google): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            ...$this->deviceRules(),
        ]);
        if (! GoogleLogin::enabled()) {
            return response()->json(['message' => __('mobile.google_off'), 'code' => 'google.off'], 403);
        }
        $profile = GoogleIdToken::verify($data['id_token']);
        if ($profile === null) {
            SecurityLog::event('google.refused', null, null, ['why' => 'invalid_token']);

            return response()->json(['message' => __('mobile.google_invalid'), 'code' => 'google.invalid'], 422);
        }

        $outcome = $google->resolve($profile);
        if ($outcome['result'] === 'refused') {
            return response()->json(['message' => __('mobile.google_'.$outcome['why']), 'code' => 'google.'.$outcome['why']], 403);
        }
        if ($outcome['result'] === 'new') {
            if (! (bool) PlatformSettings::get('signup_open')) {
                return response()->json(['message' => __('signup.closed'), 'code' => 'google.signup_closed'], 403);
            }
            $ticket = Str::random(48);
            Cache::put($this->ticketKey($ticket), ['profile' => $profile, 'device' => $this->device($data)], now()->addMinutes(GoogleLogin::PENDING_MINUTES));

            return response()->json(['google_signup' => true, 'ticket' => $ticket, 'email' => $profile['email'], 'name' => $profile['name']]);
        }

        $user = $outcome['user'];
        if ($closed = $this->closed($user)) {
            return $closed;
        }
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            return $this->challenge($user, $this->device($data), $outcome['linked']);
        }
        GoogleLogin::signedIn($user, true);

        return $this->issue($user, $this->device($data), $outcome['linked']);
    }

    /** The new owner's restaurant name and the Terms → account, restaurant and app token. */
    public function googleFinish(Request $request, GoogleLogin $google): JsonResponse
    {
        $data = $request->validate([
            'ticket' => ['required', 'string', 'size:48'],
            'restaurant_name' => ['required', 'string', 'min:2', 'max:120'],
            'terms' => ['accepted'],
            'locale' => ['nullable', Rule::in(config('menu.locales'))],
        ]);
        if (! GoogleLogin::enabled()) {
            return response()->json(['message' => __('mobile.google_off'), 'code' => 'google.off'], 403);
        }
        if (! (bool) PlatformSettings::get('signup_open')) {
            return response()->json(['message' => __('signup.closed'), 'code' => 'google.signup_closed'], 403);
        }
        $pending = Cache::pull($this->ticketKey($data['ticket']));
        if (! is_array($pending)) {
            return response()->json(['message' => __('google.expired'), 'code' => 'google.expired'], 410);
        }
        $profile = $pending['profile'];
        if (User::where('email', Str::lower($profile['email']))->orWhere('google_id', $profile['sub'])->exists()) {
            return response()->json(['message' => __('google.exists'), 'code' => 'google.exists'], 409);
        }

        $user = $google->createOwner($profile, trim($data['restaurant_name']), $data['locale'] ?? 'en');
        if ($closed = $this->closed($user)) {
            return $closed;
        }
        GoogleLogin::signedIn($user, true);

        return $this->issue($user, $pending['device']);
    }

    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:48'],
            'code' => ['nullable', 'string', 'max:10', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        $key = $this->challengeKey($data['challenge']);
        $pending = Cache::get($key);
        $user = is_array($pending) ? User::find($pending['user']) : null;
        if ($user === null || $user->two_factor_secret === null) {
            return response()->json(['message' => __('mobile.challenge_expired'), 'code' => 'mobile.challenge_expired'], 410);
        }

        $ok = false;
        if (! empty($data['code'])) {
            $ok = $provider->verify(decrypt($user->two_factor_secret), preg_replace('/\s+/', '', $data['code']));
        } elseif (! empty($data['recovery_code'])) {
            $code = RecoveryCodes::match($user, (string) $data['recovery_code']);
            if ($code !== null) {
                $user->replaceRecoveryCode($code);
                $ok = true;
            }
        }

        if (! $ok) {
            SecurityLog::event('two_factor.failed', $user->id);
            $pending['tries']++;
            $pending['tries'] >= self::CHALLENGE_TRIES ? Cache::forget($key) : Cache::put($key, $pending, now()->addMinutes(self::CHALLENGE_MINUTES));
            throw ValidationException::withMessages(['code' => __('mobile.code_invalid')]);
        }

        Cache::forget($key);

        return $this->issue($user, $pending['device']);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** Signs out every device of this user (the web session stays). */
    public function logoutAll(Request $request): Response
    {
        $count = $request->user()->tokens()->delete();
        SecurityLog::event('tokens.revoked', $request->user()->id, null, ['count' => $count, 'why' => 'logout_all']);

        return response()->noContent();
    }

    /** @return array<string, list<mixed>> */
    private function deviceRules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:100'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.+-]+$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, platform: string, app_version: string|null}
     */
    private function device(array $data): array
    {
        return ['name' => strip_tags($data['device_name']), 'platform' => $data['platform'], 'app_version' => $data['app_version'] ?? null];
    }

    /** @param  array{name: string, platform: string, app_version: string|null}  $device */
    private function challenge(User $user, array $device, bool $linked = false): JsonResponse
    {
        $challenge = Str::random(48);
        Cache::put($this->challengeKey($challenge), [
            'user' => $user->id, 'tries' => 0, 'device' => $device,
        ], now()->addMinutes(self::CHALLENGE_MINUTES));

        return response()->json(['two_factor' => true, 'challenge' => $challenge, ...($linked ? ['google_linked' => true] : [])]);
    }

    /** @param  array{name: string, platform: string, app_version: string|null}  $device */
    private function issue(User $user, array $device, bool $linked = false): JsonResponse
    {
        $new = $user->createToken($device['name'], ['mobile'], now()->addDays(PersonalAccessToken::MAX_DAYS));
        $new->accessToken->forceFill(['platform' => $device['platform'], 'app_version' => $device['app_version']])->save();
        // "Was this you?" for every new app sign-in, except a Super Admin's "log in as" (SECURITY_REPORT.md M4).
        $user->notify(new SecurityNotice('new_device', ['device' => (string) $device['name'], 'platform' => $device['platform'] === 'ios' ? 'iPhone / iPad' : 'Android']));

        return response()->json([
            'token' => $new->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_super_admin' => $user->is_super_admin],
            // "Google is now linked" (only after "Continue with Google" linked an existing account).
            ...($linked ? ['google_linked' => true] : []),
        ]);
    }

    /** Super Admin → Mobile app: maintenance and "app sign-in off" (QUESTIONS #193). */
    private function closed(User $user): ?JsonResponse
    {
        $m = MobileApp::settings();
        if ($m['maintenance'] && ! $user->is_super_admin) {
            return response()->json(['message' => $m['maintenance_message'] ?: __('mobile.maintenance'), 'code' => 'mobile.maintenance', 'web' => url('/admin')], 503);
        }
        if (! MobileApp::signInAllowed((bool) $user->is_super_admin)) {
            return response()->json(['message' => $m['sign_in_message'] ?: __('mobile.app_off'), 'code' => 'mobile.app_off', 'web' => url('/admin')], 403);
        }

        return null;
    }

    private function challengeKey(string $challenge): string
    {
        return 'mobile:2fa:'.hash('sha256', $challenge);
    }

    private function ticketKey(string $ticket): string
    {
        return 'mobile:google:'.hash('sha256', $ticket);
    }
}
