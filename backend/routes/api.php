<?php

use App\Http\Controllers\Api\AcceptInvitationController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
| JSON API for the admin SPA. Every create/change route requires an
| Idempotency-Key header ('idempotent'). Restaurant routes live under
| /api/v1/t/{tenant} and only resolve for members ('tenant').
*/
Route::prefix('v1')->middleware('idempotent')->group(function () {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])
        ->middleware('throttle:30,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', MeController::class);
        Route::post('invitations/{token}/accept', [AcceptInvitationController::class, 'accept']);

        Route::prefix('t/{tenant}')->middleware(['tenant', 'tenant.writable'])->group(function () {
            Route::get('/', [TenantController::class, 'show']);

            Route::get('members', [TeamController::class, 'index']);
            Route::patch('members/{member}', [TeamController::class, 'update'])->whereNumber('member');
            Route::delete('members/{member}', [TeamController::class, 'destroy'])->whereNumber('member');

            Route::get('invitations', [InvitationController::class, 'index']);
            Route::post('invitations', [InvitationController::class, 'store']);
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation');

            Route::patch('account/slug', [AccountController::class, 'updateSlug']);

            Route::get('activity', ActivityController::class);
        });
    });
});
