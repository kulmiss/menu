<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\NewsSubscriptionController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SpaController;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\UseWelcomeBroker;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public pages for guests and visitors set no cookies at all (no session, no
// CSRF cookie), also when PHP renders them instead of the web server serving
// the saved copy. The Privacy Policy says so (QUESTIONS #181).
$cookieFree = [AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, GuardImpersonation::class, UseWelcomeBroker::class];

// The platform's own pages (owner, 2026-11-13). "terms" and "privacy" are reserved menu addresses.
Route::withoutMiddleware($cookieFree)->group(function () {
    Route::get('/', [SiteController::class, 'home']);
    Route::get('/{page}', [SiteController::class, 'legal'])->whereIn('page', ['terms', 'privacy']);
    Route::get('/robots.txt', [SiteController::class, 'robots']);
    Route::get('/sitemap.xml', [SiteController::class, 'sitemap']);
});
// News e-mails: signed unsubscribe links (owner, 2026-11-13). The POST also serves
// mail clients' one-click "List-Unsubscribe-Post", so it skips the CSRF check.
Route::middleware(['signed', 'throttle:30,1'])->prefix('news/{user}')->whereNumber('user')->group(function () {
    Route::get('unsubscribe', [NewsSubscriptionController::class, 'show'])->name('news.unsubscribe');
    Route::post('unsubscribe', [NewsSubscriptionController::class, 'unsubscribe'])->name('news.unsubscribe.do');
    Route::post('resubscribe', [NewsSubscriptionController::class, 'resubscribe'])->name('news.resubscribe');
});

// Admin + Super Admin SPA: every URL below these prefixes loads the same page.
Route::get('/{area}/{path?}', SpaController::class)
    ->where('area', 'admin|platform|auth')
    ->where('path', '.*')
    ->name('spa');

// Link in "Confirm your e-mail" (owner, 2026-11-13).
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->whereNumber('id')->middleware('throttle:30,1')->name('signup.verify');

Route::withoutMiddleware($cookieFree)->group(function () {
    Route::get('/q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}')->name('qr');
    Route::get('/Q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}');

    // The printable menu for guests (Appearance → "Download menu (PDF)" link), cached until the menu changes.
    Route::get('/{slug}/{branch}/menu.pdf', [PublicMenuController::class, 'pdf'])->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+']);
    Route::get('/{slug}/menu.pdf', [PublicMenuController::class, 'pdf'])->where('slug', '[a-z0-9-]+');

    // Guest menu page. Normally served as a static file by the web server; PHP
    // only renders it when the copy is missing (PLAN.md §15.2.1).
    Route::get('/{slug}/{branch?}', [PublicMenuController::class, 'show'])
        ->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+'])
        ->name('public.menu');
});
