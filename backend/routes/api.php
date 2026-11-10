<?php

use App\Http\Controllers\Api\AcceptInvitationController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\BeaconController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Menu\AddonGroupController;
use App\Http\Controllers\Api\Menu\CategoryController;
use App\Http\Controllers\Api\Menu\ImportController;
use App\Http\Controllers\Api\Menu\ItemController;
use App\Http\Controllers\Api\Menu\MediaController;
use App\Http\Controllers\Api\Menu\MenuController;
use App\Http\Controllers\Api\Menu\TagController;
use App\Http\Controllers\Api\Restaurant\AppearanceController;
use App\Http\Controllers\Api\Restaurant\BranchController;
use App\Http\Controllers\Api\Restaurant\QrCodeController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
| JSON API for the admin SPA. Every create/change route requires an
| Idempotency-Key header ('idempotent'). Restaurant routes live under
| /api/v1/t/{tenant} and only resolve for members ('tenant').
*/
// Visit beacon from the guest page (no body worth replaying, so no
// idempotency key). Counting arrives with Insights (Partition 10).
Route::post('v1/beacon', BeaconController::class)->middleware('throttle:120,1')->name('beacon');

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

            // Menu data (Partition 3). Ids are checked inside the current restaurant.
            Route::get('menus', [MenuController::class, 'index']);
            Route::post('menus', [MenuController::class, 'store']);
            Route::post('menus/reorder', [MenuController::class, 'reorder']);
            Route::get('menus/{menu}', [MenuController::class, 'show'])->whereNumber('menu');
            Route::get('menus/{menu}/tree', [MenuController::class, 'tree'])->whereNumber('menu');
            Route::patch('menus/{menu}', [MenuController::class, 'update'])->whereNumber('menu');
            Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->whereNumber('menu');
            Route::post('menus/{menu}/duplicate', [MenuController::class, 'duplicate'])->whereNumber('menu');
            Route::put('menus/{menu}/schedules', [MenuController::class, 'schedules'])->whereNumber('menu');

            Route::post('categories', [CategoryController::class, 'store']);
            Route::post('categories/reorder', [CategoryController::class, 'reorder']);
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->whereNumber('category');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category');
            Route::post('categories/{category}/duplicate', [CategoryController::class, 'duplicate'])->whereNumber('category');

            Route::post('items', [ItemController::class, 'store']);
            Route::post('items/reorder', [ItemController::class, 'reorder']);
            Route::post('items/bulk', [ItemController::class, 'bulk']);
            Route::post('items/bulk-price', [ItemController::class, 'bulkPrice']);
            Route::get('items/{item}', [ItemController::class, 'show'])->whereNumber('item');
            Route::patch('items/{item}', [ItemController::class, 'update'])->whereNumber('item');
            Route::delete('items/{item}', [ItemController::class, 'destroy'])->whereNumber('item');
            Route::post('items/{item}/duplicate', [ItemController::class, 'duplicate'])->whereNumber('item');
            Route::patch('items/{item}/sold-out', [ItemController::class, 'soldOut'])->whereNumber('item');
            Route::patch('items/{item}/price', [ItemController::class, 'price'])->whereNumber('item');

            Route::get('addon-groups', [AddonGroupController::class, 'index']);
            Route::post('addon-groups', [AddonGroupController::class, 'store']);
            Route::post('addon-groups/reorder', [AddonGroupController::class, 'reorder']);
            Route::patch('addon-groups/{group}', [AddonGroupController::class, 'update'])->whereNumber('group');
            Route::delete('addon-groups/{group}', [AddonGroupController::class, 'destroy'])->whereNumber('group');
            Route::patch('addon-options/{option}/sold-out', [AddonGroupController::class, 'optionSoldOut'])->whereNumber('option');

            Route::post('media', [MediaController::class, 'store'])->middleware('throttle:30,1');
            Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media');

            Route::get('import/template', [ImportController::class, 'template']);
            Route::get('export', [ImportController::class, 'export']);
            Route::post('import/preview', [ImportController::class, 'preview'])->middleware('throttle:20,1');
            Route::post('import/commit', [ImportController::class, 'commit']);

            Route::get('branches', [BranchController::class, 'index']);
            Route::post('branches', [BranchController::class, 'store']);
            Route::patch('branches/{branch}', [BranchController::class, 'update']);
            Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
            Route::get('branches/{branch}/tables', [BranchController::class, 'tables']);
            Route::post('branches/{branch}/tables', [BranchController::class, 'addTables']);
            Route::delete('tables/{table}', [BranchController::class, 'deleteTable']);
            Route::get('branches/{branch}/overrides', [BranchController::class, 'overrides']);
            Route::put('branches/{branch}/overrides', [BranchController::class, 'saveOverrides']);
            Route::put('menus/{menu}/branches', [BranchController::class, 'menuBranches']);
            Route::get('branches/{branch}/table-cards.pdf', [QrCodeController::class, 'tableCards']);

            Route::get('qr-codes', [QrCodeController::class, 'index']);
            Route::post('qr-codes', [QrCodeController::class, 'store']);
            Route::patch('qr-codes/{qr}', [QrCodeController::class, 'update']);
            Route::delete('qr-codes/{qr}', [QrCodeController::class, 'destroy']);
            Route::get('qr-codes/{qr}.{format}', [QrCodeController::class, 'download'])->whereIn('format', ['svg', 'png', 'pdf']);

            Route::get('appearance', [AppearanceController::class, 'show']);
            Route::put('appearance', [AppearanceController::class, 'update']);
            Route::get('appearance/preview', [AppearanceController::class, 'preview'])->name('appearance.preview');

            Route::get('tags', [TagController::class, 'index']);
            Route::post('tags', [TagController::class, 'store']);
            Route::patch('tags/{tag}', [TagController::class, 'update'])->whereNumber('tag');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag');
        });
    });
});
