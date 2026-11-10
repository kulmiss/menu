<?php

use Illuminate\Support\Facades\Route;

// Public menu pages (/{slug}) arrive in Partition 4; the SPA catch-all in Partition 2.
Route::get('/', fn () => view('welcome'));
