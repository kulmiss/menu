<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Auth\RecoveryCodeController;
use App\Http\Middleware\SignInArea;
use App\Http\Requests\HashedTwoFactorLoginRequest;
use App\Http\Responses\GenericResetLinkResponse;
use App\Http\Responses\SignedInResponse;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Support\LastSignIn;
use App\Support\Security\LoginCheck;
use App\Support\Security\SecurityLog;
use App\Support\SignUp\SignUp;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController as FortifyRecoveryCodeController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Password reset links open the SPA, not a Blade view.
        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/auth/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ])));

        // Same answer time whether or not the e-mail has an account (SECURITY_REPORT.md M1).
        // Each sign-in page only signs in its own kind of account (QUESTIONS #216): a
        // Super Admin on the restaurant page (or the JSON endpoint) and a restaurant
        // account on the Super Admin page get the same answer as a wrong password.
        Fortify::authenticateUsing(function (Request $request) {
            $user = LoginCheck::user((string) $request->input(Fortify::username()), (string) $request->input('password'));
            if ($user !== null && $user->is_super_admin !== (SignInArea::of($request) === 'super')) {
                SecurityLog::event('login.wrong_area', $user->id, $user->email);

                return null;
            }

            return $user;
        });

        // 5 a minute per e-mail and address; 30 wrong passwords an hour per account in LoginCheck (M2).
        RateLimiter::for('login', fn (Request $request) => LoginCheck::limits((string) $request->input(Fortify::username()), (string) $request->ip()));

        // Forgot / reset password: per address and per e-mail (M2). Answers never say whether
        // an account exists (M1): see GenericResetLinkResponse.
        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perMinute(5)->by('pw-ip:'.$request->ip()),
            Limit::perHour(5)->by('pw-mail:'.sha1(Str::lower((string) $request->input('email')))),
        ]);
        // CSV / PDF / export downloads are heavy for shared hosting: 30 a minute per user (M2).
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(30)->by('exp:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        // After signing in: the page asked for, else the person's own area (QUESTIONS #215).
        $this->app->bind(LoginResponse::class, fn () => new SignedInResponse);
        $this->app->bind(TwoFactorLoginResponse::class, fn () => new SignedInResponse(afterCode: true));
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericResetLinkResponse::class);

        // Recovery codes are checked against their hashes (SECURITY_REPORT.md M5).
        $this->app->bind(TwoFactorLoginRequest::class, HashedTwoFactorLoginRequest::class);
        $this->app->bind(FortifyRecoveryCodeController::class, RecoveryCodeController::class);

        // "Was this you?" e-mails for two-factor changes, web and app (SECURITY_REPORT.md M4).
        Event::listen(TwoFactorAuthenticationFailed::class, fn ($e) => SecurityLog::event('two_factor.failed', $e->user->id));
        Event::listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => $e->user->notify(new SecurityNotice('two_factor_on')));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => $e->user->notify(new SecurityNotice('two_factor_off')));
        Event::listen(RecoveryCodesGenerated::class, fn ($e) => $e->user->notify(new SecurityNotice('recovery_codes')));
        // Latest sign-in time (QUESTIONS #187). Fires after two-factor when it is on; not for "remember me" cookies.
        Event::listen(Login::class, fn (Login $e) => LastSignIn::record($e->user));

        // Self sign-up: confirming the e-mail makes the restaurant live or puts it up for approval.
        Event::listen(Verified::class, fn (Verified $e) => $e->user instanceof User && app(SignUp::class)->verified($e->user));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
