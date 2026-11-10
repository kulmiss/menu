<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Public menu pages (/{slug}) arrive in Partition 4.
Route::get('/', fn () => view('welcome'));

// Admin + Super Admin SPA: every URL below these prefixes loads the same page.
Route::get('/{area}/{path?}', SpaController::class)
    ->where('area', 'admin|platform|auth')
    ->where('path', '.*')
    ->name('spa');
