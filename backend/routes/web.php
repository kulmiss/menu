<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

// Admin + Super Admin SPA: every URL below these prefixes loads the same page.
Route::get('/{area}/{path?}', SpaController::class)
    ->where('area', 'admin|platform|auth')
    ->where('path', '.*')
    ->name('spa');

// Guest menu page. Normally served as a static file by the web server; PHP
// only renders it when the copy is missing (PLAN.md §15.2.1).
// Link in "Confirm your e-mail" (owner, 2026-11-13).
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->whereNumber('id')->middleware('throttle:30,1')->name('signup.verify');

Route::get('/q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}')->name('qr');
Route::get('/Q/{code}', [PublicMenuController::class, 'qr'])->where('code', '[A-Za-z0-9]{8}');

// The printable menu for guests (Appearance → "Download menu (PDF)" link), cached until the menu changes.
Route::get('/{slug}/{branch}/menu.pdf', [PublicMenuController::class, 'pdf'])->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+']);
Route::get('/{slug}/menu.pdf', [PublicMenuController::class, 'pdf'])->where('slug', '[a-z0-9-]+');

Route::get('/{slug}/{branch?}', [PublicMenuController::class, 'show'])
    ->where(['slug' => '[a-z0-9-]+', 'branch' => '[a-z0-9-]+'])
    ->name('public.menu');
