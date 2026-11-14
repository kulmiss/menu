<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\GenericResetLinkResponse;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Support\LastSignIn;
use App\Support\Security\LoginCheck;
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
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Fortify;

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
        Fortify::authenticateUsing(fn (Request $request) => LoginCheck::user((string) $request->input(Fortify::username()), (string) $request->input('password')));

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
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericResetLinkResponse::class);

        // "Was this you?" e-mails for two-factor changes, web and app (SECURITY_REPORT.md M4).
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
