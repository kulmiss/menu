<?php

use App\Http\Controllers\Api\AcceptInvitationController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AnnouncementController;
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
use App\Http\Controllers\Api\Restaurant\BillingController;
use App\Http\Controllers\Api\Restaurant\BranchController;
use App\Http\Controllers\Api\Restaurant\DashboardWidgetController;
use App\Http\Controllers\Api\Restaurant\InfoController;
use App\Http\Controllers\Api\Restaurant\InsightsController;
use App\Http\Controllers\Api\Restaurant\InsightsExportController;
use App\Http\Controllers\Api\Restaurant\PdfMenuController;
use App\Http\Controllers\Api\Restaurant\PricePeriodController;
use App\Http\Controllers\Api\Restaurant\PromotionController;
use App\Http\Controllers\Api\Restaurant\QrCodeController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Mobile\AccountController as MobileAccountController;
use App\Http\Controllers\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Mobile\DeviceController;
use App\Http\Controllers\NewsSubscriptionController;
use App\Http\Controllers\Platform\BackupController;
use App\Http\Controllers\Platform\CampaignController;
use App\Http\Controllers\Platform\CrmController;
use App\Http\Controllers\Platform\DashboardWidgetController as PlatformDashboardWidgetController;
use App\Http\Controllers\Platform\InsightsController as PlatformInsightsController;
use App\Http\Controllers\Platform\InvoiceController;
use App\Http\Controllers\Platform\PaymentController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\RestaurantController;
use App\Http\Controllers\Platform\SearchController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/*
| JSON API for the admin SPA. Every create/change route requires an
| Idempotency-Key header ('idempotent'). Restaurant routes live under
| /api/v1/t/{tenant} and only resolve for members ('tenant').
*/
// Visit beacon from the guest page (no body worth replaying, so no
// idempotency key). Counting arrives with Insights (Partition 10).
Route::post('v1/beacon', BeaconController::class)
    ->middleware('throttle:120,1')
    // Same-site guest pages must not start a session or need a CSRF token.
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
    ->name('beacon');

// Mobile app sign-in (QUESTIONS #187). Not idempotent on purpose: a replayed
// answer would have to store the new token in the database.
Route::prefix('v1/mobile/auth')->controller(MobileAuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:mobile-login')->name('mobile.login');
    Route::post('two-factor', 'twoFactor')->middleware('throttle:mobile-two-factor')->name('mobile.two-factor');
});

Route::prefix('v1')->middleware('idempotent')->group(function () {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])
        ->middleware('throttle:30,1');

    // Self sign-up (owner, 2026-11-13).
    Route::prefix('signup')->controller(SignUpController::class)->group(function () {
        Route::get('config', 'config')->middleware('throttle:60,1');
        Route::get('slug', 'slug')->middleware('throttle:30,1');
        Route::post('/', 'store')->middleware(['guest', 'throttle:10,1']);
        Route::post('email', 'changeEmail')->middleware(['auth:sanctum', 'throttle:6,1']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', MeController::class);

        // Mobile app: this device, other devices, account (QUESTIONS #187).
        Route::prefix('mobile')->middleware('mobile.token')->group(function () {
            Route::post('auth/logout', [MobileAuthController::class, 'logout']);
            Route::post('auth/logout-all', [MobileAuthController::class, 'logoutAll']);
            Route::get('devices', [DeviceController::class, 'index']);
            Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->whereNumber('device');
            Route::put('account/profile', [MobileAccountController::class, 'profile']);
            Route::put('account/password', [MobileAccountController::class, 'password'])->middleware('throttle:6,1');
            Route::post('account/two-factor', [MobileAccountController::class, 'enableTwoFactor'])->middleware('throttle:6,1');
            Route::get('account/two-factor', [MobileAccountController::class, 'twoFactorSetup']);
            Route::post('account/two-factor/confirm', [MobileAccountController::class, 'confirmTwoFactor'])->middleware('throttle:6,1');
            Route::delete('account/two-factor', [MobileAccountController::class, 'disableTwoFactor'])->middleware('throttle:6,1');
            Route::post('account/two-factor/recovery-codes', [MobileAccountController::class, 'recoveryCodes'])->middleware('throttle:6,1');
        });
        Route::put('me/news', [NewsSubscriptionController::class, 'update']);
        Route::post('impersonation/stop', [AnnouncementController::class, 'stopImpersonating']);

        // Super Admin (PLAN.md §7.3): Super Admins with two-factor sign-in only.
        Route::prefix('platform')->middleware('platform')->group(function () {
            Route::get('dashboard', [PlatformController::class, 'dashboard']);
            Route::prefix('widgets')->controller(PlatformDashboardWidgetController::class)->group(function () {
                foreach (['expiring', 'inactive', 'signups', 'health'] as $widget) {
                    Route::get($widget, $widget);
                }
                Route::get('plan-mix', 'planMix');
                Route::post('email/{tenant}', 'email')->whereNumber('tenant')->middleware('throttle:20,1');
            });
            Route::get('insights', [PlatformInsightsController::class, 'meta']);
            Route::get('insights/{widget}', [PlatformInsightsController::class, 'show'])->whereIn('widget', PlatformInsightsController::WIDGETS);
            Route::get('restaurants', [RestaurantController::class, 'index']);
            Route::post('restaurants', [RestaurantController::class, 'store']);
            Route::post('restaurants/bulk-tag', [RestaurantController::class, 'bulkTag']);
            Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->whereNumber('restaurant');
            Route::patch('restaurants/{restaurant}', [RestaurantController::class, 'update'])->whereNumber('restaurant');
            Route::delete('restaurants/{restaurant}', [RestaurantController::class, 'destroy'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/restore', [RestaurantController::class, 'restore'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/suspend', [RestaurantController::class, 'suspend'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/unsuspend', [RestaurantController::class, 'unsuspend'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/approve', [RestaurantController::class, 'approve'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/reject', [RestaurantController::class, 'reject'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/resend-welcome', [RestaurantController::class, 'resendWelcome'])->whereNumber('restaurant')->middleware('throttle:6,1');
            Route::put('restaurants/{restaurant}/features', [RestaurantController::class, 'features'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/payments', [PaymentController::class, 'store'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/impersonate', [RestaurantController::class, 'impersonate'])->whereNumber('restaurant');
            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments.csv', [PaymentController::class, 'export']);
            // CRM: tags, notes, follow-ups (owner, 2026-11-13)
            Route::get('tags', [CrmController::class, 'tags']);
            Route::post('tags', [CrmController::class, 'storeTag']);
            Route::patch('tags/{tag}', [CrmController::class, 'updateTag'])->whereNumber('tag');
            Route::delete('tags/{tag}', [CrmController::class, 'destroyTag'])->whereNumber('tag');
            Route::put('restaurants/{restaurant}/tags', [CrmController::class, 'setTags'])->whereNumber('restaurant');
            Route::get('restaurants/{restaurant}/notes', [CrmController::class, 'notes'])->whereNumber('restaurant');
            Route::post('restaurants/{restaurant}/notes', [CrmController::class, 'storeNote'])->whereNumber('restaurant');
            Route::patch('notes/{note}', [CrmController::class, 'updateNote'])->whereNumber('note');
            Route::delete('notes/{note}', [CrmController::class, 'destroyNote'])->whereNumber('note');
            Route::put('restaurants/{restaurant}/follow-up', [CrmController::class, 'followUp'])->whereNumber('restaurant');
            Route::get('widgets/follow-ups', [CrmController::class, 'due']);
            // Messages: e-mails to restaurant owners (owner, 2026-11-13)
            Route::get('campaigns', [CampaignController::class, 'index']);
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->whereNumber('campaign');
            Route::post('campaigns/count', [CampaignController::class, 'count']);
            Route::post('campaigns/preview', [CampaignController::class, 'preview']);
            Route::post('campaigns/test', [CampaignController::class, 'test'])->middleware('throttle:10,1');
            Route::post('campaigns', [CampaignController::class, 'store']);
            Route::post('campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->whereNumber('campaign');
            Route::put('campaigns/rate', [CampaignController::class, 'rate']);
            // Global search (owner, 2026-11-13)
            Route::get('search', SearchController::class)->middleware('throttle:120,1');
            // Backups (owner, 2026-11-13)
            Route::get('backups', [BackupController::class, 'index']);
            Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:6,1');
            Route::put('backups/settings', [BackupController::class, 'settings']);
            Route::post('backups/{backup}/link', [BackupController::class, 'link'])->whereNumber('backup')->middleware('throttle:6,1');
            Route::get('backups/{backup}/file', [BackupController::class, 'file'])->whereNumber('backup')->middleware('signed')->name('platform.backups.file');
            Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->whereNumber('backup');
            // Invoices (owner, 2026-11-13)
            Route::get('invoices', [InvoiceController::class, 'index']);
            Route::get('invoices.csv', [InvoiceController::class, 'export']);
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->whereNumber('invoice');
            Route::post('invoices/{invoice}/resend', [InvoiceController::class, 'resend'])->whereNumber('invoice')->middleware('throttle:10,1');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->whereNumber('invoice');
            Route::get('plans', [PlatformController::class, 'plans']);
            Route::post('plans', [PlatformController::class, 'savePlan']);
            Route::patch('plans/{plan}', [PlatformController::class, 'savePlan'])->whereNumber('plan');
            Route::get('themes', [PlatformController::class, 'themes']);
            Route::post('themes', [PlatformController::class, 'saveTheme']);
            Route::patch('themes/{theme}', [PlatformController::class, 'saveTheme'])->whereNumber('theme');
            Route::get('announcements', [PlatformController::class, 'announcements']);
            Route::post('announcements', [PlatformController::class, 'saveAnnouncement']);
            Route::patch('announcements/{announcement}', [PlatformController::class, 'saveAnnouncement'])->whereNumber('announcement');
            Route::delete('announcements/{announcement}', [PlatformController::class, 'deleteAnnouncement'])->whereNumber('announcement');
            Route::get('activity', [PlatformController::class, 'activity']);
            Route::get('settings', [PlatformController::class, 'settings']);
            Route::put('settings', [PlatformController::class, 'saveSettings']);
            Route::post('settings/legal/{page}/reset', [PlatformController::class, 'resetLegal'])->whereIn('page', ['terms', 'privacy']);
            Route::post('settings/share-image', [PlatformController::class, 'saveShareImage']);
            Route::delete('settings/share-image', [PlatformController::class, 'deleteShareImage']);
            Route::post('settings/logo', [PlatformController::class, 'saveLogo']);
            Route::delete('settings/logo', [PlatformController::class, 'deleteLogo']);
            Route::get('settings/mail', [PlatformController::class, 'mailSettings']);
            Route::put('settings/mail', [PlatformController::class, 'saveMailSettings']);
            Route::delete('settings/mail', [PlatformController::class, 'clearMailSettings']);
            Route::post('settings/mail/test', [PlatformController::class, 'testMail'])->middleware('throttle:5,1');
        });
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

            Route::get('items', [ItemController::class, 'index']);
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

            Route::get('import/template', [ImportController::class, 'template'])->middleware('feature:import_export');
            Route::get('export', [ImportController::class, 'export'])->middleware('feature:import_export');
            Route::post('import/preview', [ImportController::class, 'preview'])->middleware(['throttle:20,1', 'feature:import_export']);
            Route::post('import/commit', [ImportController::class, 'commit'])->middleware('feature:import_export');

            Route::get('branches', [BranchController::class, 'index']);
            Route::post('branches', [BranchController::class, 'store'])->middleware('feature:branches');
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
            Route::get('print-materials', [QrCodeController::class, 'printInfo']);
            Route::get('print-materials.pdf', [QrCodeController::class, 'printMaterials']);
            Route::get('qr-codes/{qr}.{format}', [QrCodeController::class, 'download'])->whereIn('format', ['svg', 'png', 'pdf']);

            Route::get('announcements', [AnnouncementController::class, 'index']);
            Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'read'])->whereNumber('announcement');

            Route::get('insights', [InsightsController::class, 'index'])->middleware('feature:insights');
            Route::middleware('feature:insights')->group(function () {
                Route::get('insights/export.csv', [InsightsExportController::class, 'csv']);
                Route::get('insights/report.pdf', [InsightsExportController::class, 'pdf']);
                Route::get('insights/settings', [InsightsExportController::class, 'settings']);
                Route::put('insights/settings', [InsightsExportController::class, 'updateSettings']);
            });
            Route::get('dashboard', [InsightsController::class, 'dashboard']);
            Route::prefix('widgets')->controller(DashboardWidgetController::class)->group(function () {
                Route::get('setup', 'setup');
                Route::post('setup/hide', 'hideSetup');
                Route::get('trend', 'trend');
                Route::get('missing-photos', 'missingPhotos');
                Route::get('orders', 'orders');
                Route::get('plan', 'plan');
            });

            // Time-based prices (owner, 2026-11-14)
            Route::middleware('feature:price_periods')->group(function () {
                Route::get('price-periods', [PricePeriodController::class, 'index']);
                Route::get('price-periods/preview', [PricePeriodController::class, 'preview']);
                Route::post('price-periods', [PricePeriodController::class, 'store']);
                Route::put('price-periods/{period}', [PricePeriodController::class, 'update'])->whereNumber('period');
                Route::delete('price-periods/{period}', [PricePeriodController::class, 'destroy'])->whereNumber('period');
            });
            Route::get('promotions', [PromotionController::class, 'index']);
            Route::post('promotions', [PromotionController::class, 'store'])->middleware('feature:promotions');
            Route::post('promotions/reorder', [PromotionController::class, 'reorder'])->middleware('feature:promotions');
            Route::patch('promotions/{promotion}', [PromotionController::class, 'update'])->middleware('feature:promotions');
            Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy']);

            Route::get('profile', [InfoController::class, 'profile']);
            Route::put('profile', [InfoController::class, 'saveProfile']);
            Route::get('hours', [InfoController::class, 'hours']);
            Route::put('hours', [InfoController::class, 'saveHours']);
            Route::get('closures', [InfoController::class, 'closures']);
            Route::post('closures', [InfoController::class, 'addClosure']);
            Route::delete('closures/{closure}', [InfoController::class, 'deleteClosure']);

            Route::get('appearance', [AppearanceController::class, 'show']);
            Route::put('appearance', [AppearanceController::class, 'update']);
            Route::get('appearance/preview', [AppearanceController::class, 'preview'])->name('appearance.preview');
            // Settings → Billing (owner only)
            Route::get('billing', [BillingController::class, 'show']);
            Route::get('billing/invoices/{invoice}/pdf', [BillingController::class, 'pdf'])->whereNumber('invoice');

            Route::get('menu-pdf', [PdfMenuController::class, 'download'])->middleware(['feature:pdf_menu', 'throttle:20,1']);

            Route::get('tags', [TagController::class, 'index']);
            Route::post('tags', [TagController::class, 'store']);
            Route::patch('tags/{tag}', [TagController::class, 'update'])->whereNumber('tag');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag');
        });
    });
});
