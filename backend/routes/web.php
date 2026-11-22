<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\ConfirmEmailChangeController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NewsSubscriptionController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SpaController;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\UseWelcomeBroker;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

// Public pages for guests and visitors set no cookies at all (no session, no
// CSRF cookie), also when PHP renders them instead of the web server serving
// the saved copy. The Privacy Policy says so (QUESTIONS #181).
$cookieFree = [AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, GuardImpersonation::class, UseWelcomeBroker::class];

// The platform's own pages (owner, 2026-11-13). "terms" and "privacy" are reserved menu addresses.
Route::withoutMiddleware($cookieFree)->group(function () {
    Route::get('/', [SiteController::class, 'home']);
    // The new home page design, to look at before the Super Admin picks it (owner, 2026-11-22).
    Route::get('/landing2', [SiteController::class, 'landing2']);
    // Landing 2 in Amharic (owner, 2026-11-22): /am/landing2 always, /am once it is the home page.
    Route::get('/am', [SiteController::class, 'homeAm']);
    Route::get('/am/landing2', [SiteController::class, 'landing2Am']);
    Route::get('/{page}', [SiteController::class, 'legal'])->whereIn('page', ['terms', 'privacy', 'about', 'contact', 'cookies']);
    // Help centre for everyone: the public help articles (footer, owner 2026-11-18). Not /help: that is the
    // screenshots' folder (public/help/shots), which Apache and `php artisan serve` would serve instead.
    Route::get('/support', [SiteController::class, 'help']);
    Route::get('/support/{slug}', [SiteController::class, 'helpArticle'])->where('slug', '[a-z0-9-]+');
    Route::get('/robots.txt', [SiteController::class, 'robots']);
    Route::get('/sitemap.xml', [SiteController::class, 'sitemap']);
    // For uptime monitors (UptimeRobot, Better Stack…): 200 "ok"/"degraded", 503 "down" (QUESTIONS #233).
    Route::get('/health', HealthController::class)->middleware('throttle:60,1');
});
// News e-mails: signed unsubscribe links (owner, 2026-11-13). The POST also serves
// mail clients' one-click "List-Unsubscribe-Post". The signature is the
// protection, so these pages need no session or CSRF cookie and set none.
Route::withoutMiddleware($cookieFree)->middleware(['signed', 'throttle:30,1'])->prefix('news/{user}')->whereNumber('user')->group(function () {
    Route::get('unsubscribe', [NewsSubscriptionController::class, 'show'])->name('news.unsubscribe');
    Route::post('unsubscribe', [NewsSubscriptionController::class, 'unsubscribe'])->name('news.unsubscribe.do');
    Route::post('resubscribe', [NewsSubscriptionController::class, 'resubscribe'])->name('news.resubscribe');
});

// "Continue with Google" (owner, 2026-11-17).
// Socialite keeps a one-time state in the session (CSRF check of the callback).
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// The two areas (QUESTIONS #215–#222). Restaurant teams: /signin, then /admin.
// Each sign-in form posts to Fortify's own sign-in, so the checks, limits and the
// two-factor step stay the same; SignInArea decides which accounts may sign in there.
Route::get('/signin', [SignInController::class, 'restaurant'])->name('login');
Route::post('/signin', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest:web', 'signin.area:restaurant', 'throttle:login'])->name('signin.store');
Route::get('/signin/two-factor', [SignInController::class, 'twoFactor'])->middleware(['guest:web', 'signin.challenge'])->name('two-factor.login');
Route::post('/signin/two-factor', [TwoFactorAuthenticatedSessionController::class, 'store'])
    ->middleware(['guest:web', 'signin.challenge', 'throttle:two-factor'])->name('signin.two-factor');
Route::get('/admin/{path?}', [SpaController::class, 'restaurant'])->where('path', '.*')->name('spa.admin');

// The other account pages, in the landing page's style (QUESTIONS #222).
Route::get('/signup', [AccountController::class, 'signUp'])->name('signup');
Route::post('/signup', [AccountController::class, 'signUpForm'])->middleware(['guest:web', 'throttle:10,1'])->name('signup.store');
Route::get('/signup/google', [GoogleController::class, 'finishPage'])->name('google.finish');
Route::post('/signup/google', [GoogleController::class, 'finishForm'])->middleware(['guest:web', 'throttle:10,1'])->name('google.finish.store');
Route::get('/verify-email', [AccountController::class, 'verifyEmail'])->name('verification.notice');
Route::middleware('auth:web')->group(function () {
    Route::post('/verify-email/resend', [AccountController::class, 'resendVerification'])->middleware('throttle:6,1,verify-mail')->name('verify.resend');
    Route::post('/verify-email/change', [AccountController::class, 'changeEmail'])->middleware('throttle:6,1,verify-mail')->name('verify.change');
    Route::post('/verify-email/check', [AccountController::class, 'checkVerified'])->middleware('throttle:30,1')->name('verify.check');
    Route::get('/waiting', [AccountController::class, 'waiting'])->name('waiting');
    Route::post('/invite/{token}/accept', [AccountController::class, 'acceptInvitation'])->middleware('throttle:10,1')->name('invite.accept');
});
Route::post('/signout', [AccountController::class, 'signOut'])->name('signout');
Route::get('/forgot-password', [AccountController::class, 'forgotPassword'])->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware(['guest:web', 'password.throttle'])->name('password.email.web');
Route::get('/reset-password', [AccountController::class, 'resetPassword'])->name('password.reset.web');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware(['guest:web', 'password.throttle', 'password.single'])->name('password.update.web');
Route::get('/invite/{token}', [AccountController::class, 'invitation'])->middleware('throttle:30,1')->name('invite');
Route::post('/invite/{token}/join', [AccountController::class, 'joinInvitation'])->middleware(['guest:web', 'throttle:10,1'])->name('invite.join');

// Super Admin: its own address (SUPER_ADMIN_PATH) and plain sign-in page; outside
// SUPER_ADMIN_IPS it doesn't exist (404).
Route::prefix(SuperAdminAccess::path())->middleware('super.web')->group(function () {
    Route::get('sign-in', [SignInController::class, 'super'])->name('super.signin');
    Route::post('sign-in', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['guest:web', 'signin.area:super', 'throttle:login'])->name('super.signin.store');
    // The sales sheet (QUESTIONS #234): Super Admins with two-factor only, a printable page.
    Route::get('sales-sheet', [SiteController::class, 'salesSheet'])->middleware(['auth', 'super'])->name('super.sales-sheet');
    Route::get('{path?}', [SpaController::class, 'super'])->where('path', '.*')->name('spa.super');
});

// Link in "Confirm your e-mail" (owner, 2026-11-13).
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->whereNumber('id')->middleware('throttle:30,1')->name('signup.verify');
// New e-mail address: the link sent to it (SECURITY_REPORT.md H3).
Route::get('/email/change/{user}/{hash}', ConfirmEmailChangeController::class)->whereNumber('user')->middleware('throttle:30,1')->name('email-change.confirm');

Route::withoutMiddleware($cookieFree)->group(function () {
    Route::get('/q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}')->name('qr');
    Route::get('/Q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}');

    // The printable menu for guests (Appearance → "Download menu (PDF)" link), cached until the menu changes.
    Route::get('/{slug}/{branch}/menu.pdf', [PublicMenuController::class, 'pdf'])->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+']);
    Route::get('/{slug}/menu.pdf', [PublicMenuController::class, 'pdf'])->where('slug', '[a-z0-9-]+');

    // A website demo menu in a phone frame (tablet and computer visitors, owner 2026-11-19).
    Route::get('/phone/{slug}/{branch?}', [PublicMenuController::class, 'phone'])->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+']);

    // Guest menu page. Normally served as a static file by the web server; PHP
    // only renders it when the copy is missing (PLAN.md §15.2.1).
    Route::get('/{slug}/{branch?}', [PublicMenuController::class, 'show'])
        ->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+'])
        ->name('public.menu');
});
