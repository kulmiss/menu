<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Fortify\PasswordValidationRules;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SecurityNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Profile, password and two-factor for the app (QUESTIONS #187). Same rules
 * as the web (Fortify actions), but the password goes in the request instead
 * of a "confirm password" session.
 */
class AccountController extends Controller
{
    use PasswordValidationRules;

    public function profile(Request $request, UpdateUserProfileInformation $update): JsonResponse
    {
        $update->update($request->user(), $request->only(['name', 'email', 'locale', 'current_password']));

        return response()->json(['data' => $this->summary($request->user()->fresh())]);
    }

    public function password(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules($user->email),
        ]);
        $this->checkPassword($user, $data['current_password'], 'current_password');

        $user->forceFill(['password' => $data['password'], 'password_weak' => false])->save();
        // Other devices sign in again; this one stays.
        $user->revokeAppTokens($user->currentAccessToken()->getKey());
        $user->notify(new SecurityNotice('password_changed'));

        return response()->noContent();
    }

    /** Starts setup: a new secret that becomes active after confirm(). */
    public function enableTwoFactor(Request $request, EnableTwoFactorAuthentication $enable): Response
    {
        $user = $request->user();
        $this->checkPassword($user, (string) $request->input('password'));
        abort_if($user->two_factor_confirmed_at !== null, 409);
        $enable($user, true);

        return response()->noContent();
    }

    /** QR code and key while setup is pending (never after it is confirmed). */
    public function twoFactorSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            return response()->json(['message' => __('mobile.two_factor_not_pending'), 'code' => 'mobile.two_factor_not_pending'], 409);
        }

        return response()->json(['data' => [
            'svg' => $user->twoFactorQrCodeSvg(),
            'url' => $user->twoFactorQrCodeUrl(),
            'secret' => decrypt($user->two_factor_secret),
        ]])->header('Cache-Control', 'no-store');
    }

    public function confirmTwoFactor(Request $request, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();
        $confirm($user, preg_replace('/\s+/', '', (string) $request->input('code')));

        return response()->json(['data' => ['recovery_codes' => $user->fresh()->recoveryCodes()]])->header('Cache-Control', 'no-store');
    }

    public function disableTwoFactor(Request $request, DisableTwoFactorAuthentication $disable): Response
    {
        $user = $request->user();
        $this->checkPassword($user, (string) $request->input('password'));
        $disable($user);

        return response()->noContent();
    }

    public function recoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $user = $request->user();
        $this->checkPassword($user, (string) $request->input('password'));
        abort_if($user->two_factor_confirmed_at === null, 409);
        $generate($user);

        return response()->json(['data' => ['recovery_codes' => $user->fresh()->recoveryCodes()]])->header('Cache-Control', 'no-store');
    }

    private function checkPassword(User $user, string $password, string $field = 'password'): void
    {
        if ($password === '' || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([$field => __('mobile.password_invalid')]);
        }
    }

    /** @return array<string, mixed> */
    private function summary(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'pending_email' => $user->pending_email, 'email_verified' => $user->hasVerifiedEmail()];
    }
}
