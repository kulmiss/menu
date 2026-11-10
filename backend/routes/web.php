<?php

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
Route::get('/{slug}', [PublicMenuController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('public.menu');
