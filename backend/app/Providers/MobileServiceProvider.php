<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The mobile app (QUESTIONS #187): its token model, the idle-expiry rule and
 * the sign-in rate limits.
 */
class MobileServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Sliding expiry: a token unused for too long stops working (and is pruned nightly).
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $isValid): bool => $isValid && $token instanceof PersonalAccessToken && $token->isFresh()
        );

        // Same limit as the web sign-in (5 a minute per e-mail and address), plus 30 a minute per address.
        RateLimiter::for('mobile-login', fn (Request $request) => [
            Limit::perMinute(5)->by('e:'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip()),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('mobile-two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('c:'.hash('sha256', (string) $request->input('challenge'))),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
    }
}
