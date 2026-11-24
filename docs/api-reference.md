# API reference (generated)

<!-- Written by `php artisan docs:api-reference`. Do not edit by hand: a test fails when routes change and this file is not written again. -->

Every `/api/v1` endpoint, from the routes. **Who**: who may call it (roles inside a restaurant are checked by each screen's policy). **Feature**: the feature switch it needs (`402 features.<key>` when off). **Limit**: how many requests are allowed. Create/change requests need an `Idempotency-Key` header. Request and answer examples for the app: [mobile-api.md](mobile-api.md).

**294 endpoints.**

## Sign-in and account (web session)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `auth/email/verification-notification` | signed in |  | 6 a minute (shared: `verify-mail`) | Fortify\EmailVerificationNotificationController@store |
| GET | `auth/email/verify/{id}/{hash}` | signed in |  | 6 a minute (shared: `verify-mail`) | Fortify\VerifyEmailController@__invoke |
| POST | `auth/forgot-password` | signed out |  |  | Fortify\PasswordResetLinkController@store |
| POST | `auth/google/finish` | signed out |  | 10 a minute | Auth\GoogleController@finish |
| GET | `auth/google/pending` | anyone |  | 30 a minute | Auth\GoogleController@pending |
| POST | `auth/login` | signed out |  | `login` limiter | Fortify\AuthenticatedSessionController@store |
| POST | `auth/logout` | signed in |  |  | Fortify\AuthenticatedSessionController@destroy |
| POST | `auth/register` | signed out |  |  | Fortify\RegisteredUserController@store |
| POST | `auth/reset-password` | signed out |  |  | Fortify\NewPasswordController@store |
| POST | `auth/two-factor-challenge` | signed out |  | `two-factor` limiter | Fortify\TwoFactorAuthenticatedSessionController@store |
| POST | `auth/user/confirm-password` | signed in |  |  | Fortify\ConfirmablePasswordController@store |
| GET | `auth/user/confirmed-password-status` | signed in |  |  | Fortify\ConfirmedPasswordStatusController@show |
| POST | `auth/user/confirmed-two-factor-authentication` | signed in, password confirmed |  |  | Fortify\ConfirmedTwoFactorAuthenticationController@store |
| PUT | `auth/user/password` | signed in |  |  | Fortify\PasswordController@update |
| PUT | `auth/user/profile-information` | signed in |  |  | Fortify\ProfileInformationController@update |
| DELETE | `auth/user/two-factor-authentication` | signed in, password confirmed |  |  | Fortify\TwoFactorAuthenticationController@destroy |
| POST | `auth/user/two-factor-authentication` | signed in, password confirmed |  |  | Fortify\TwoFactorAuthenticationController@store |
| GET | `auth/user/two-factor-qr-code` | signed in, password confirmed |  |  | Fortify\TwoFactorQrCodeController@show |
| GET | `auth/user/two-factor-recovery-codes` | signed in, password confirmed |  |  | Fortify\RecoveryCodeController@index |
| POST | `auth/user/two-factor-recovery-codes` | signed in, password confirmed |  |  | Fortify\RecoveryCodeController@store |
| GET | `auth/user/two-factor-secret-key` | signed in, password confirmed |  |  | Fortify\TwoFactorSecretKeyController@show |

## Mobile app (token)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| PUT | `mobile/account/password` | signed in, app token |  | 6 a minute | Mobile\AccountController@password |
| PUT | `mobile/account/profile` | signed in, app token |  |  | Mobile\AccountController@profile |
| DELETE | `mobile/account/two-factor` | signed in, app token |  | 6 a minute | Mobile\AccountController@disableTwoFactor |
| GET | `mobile/account/two-factor` | signed in, app token |  |  | Mobile\AccountController@twoFactorSetup |
| POST | `mobile/account/two-factor` | signed in, app token |  | 6 a minute | Mobile\AccountController@enableTwoFactor |
| POST | `mobile/account/two-factor/confirm` | signed in, app token |  | 6 a minute | Mobile\AccountController@confirmTwoFactor |
| POST | `mobile/account/two-factor/recovery-codes` | signed in, app token |  | 6 a minute | Mobile\AccountController@recoveryCodes |
| POST | `mobile/auth/google` | anyone |  | `mobile-google` limiter | Mobile\AuthController@google |
| POST | `mobile/auth/google/finish` | anyone |  | `mobile-google` limiter | Mobile\AuthController@googleFinish |
| POST | `mobile/auth/login` | anyone |  | `mobile-login` limiter | Mobile\AuthController@login |
| POST | `mobile/auth/logout` | signed in, app token |  |  | Mobile\AuthController@logout |
| POST | `mobile/auth/logout-all` | signed in, app token |  |  | Mobile\AuthController@logoutAll |
| POST | `mobile/auth/two-factor` | anyone |  | `mobile-two-factor` limiter | Mobile\AuthController@twoFactor |
| GET | `mobile/config` | anyone |  | 60 a minute | Mobile\ConfigController |
| GET | `mobile/devices` | signed in, app token |  |  | Mobile\DeviceController@index |
| DELETE | `mobile/devices/{device}` | signed in, app token |  |  | Mobile\DeviceController@destroy |
| GET | `mobile/notification-settings` | signed in, app token |  |  | Mobile\PushController@settings |
| PUT | `mobile/notification-settings` | signed in, app token |  |  | Mobile\PushController@saveSettings |
| GET | `mobile/notifications` | signed in, app token |  |  | Mobile\PushController@inbox |
| POST | `mobile/notifications/read` | signed in, app token |  |  | Mobile\PushController@read |
| DELETE | `mobile/push-tokens` | signed in, app token |  |  | Mobile\PushController@unregister |
| POST | `mobile/push-tokens` | signed in, app token |  | 20 a minute | Mobile\PushController@register |

## Signed-in person

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `me` | signed in |  |  | Api\MeController |
| DELETE | `me/google` | signed in |  | 6 a minute | Auth\GoogleController@unlink |
| PUT | `me/news` | signed in |  |  | NewsSubscriptionController@update |
| PUT | `me/onboarding` | signed in |  | 30 a minute | Api\OnboardingController |
| POST | `me/password-link` | signed in |  | 3 per 10 minutes | Auth\GoogleController@passwordLink |
| POST | `me/sessions/logout-others` | signed in |  | 6 a minute | Api\SessionController@logoutOthers |

## Self sign-up

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `signup` | signed out |  | 10 a minute | Auth\SignUpController@store |
| GET | `signup/config` | anyone |  | 60 a minute | Auth\SignUpController@config |
| POST | `signup/email` | signed in |  | 6 a minute (shared: `verify-mail`) | Auth\SignUpController@changeEmail |
| GET | `signup/slug` | anyone |  | 30 a minute | Auth\SignUpController@slug |

## Team invitations

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `invitations/{token}` | anyone |  | 30 a minute | Api\AcceptInvitationController@show |
| POST | `invitations/{token}/accept` | signed in |  |  | Api\AcceptInvitationController@accept |

## "Log in as"

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `impersonation/stop` | signed in |  |  | Api\AnnouncementController@stopImpersonating |

## Restaurant (`restaurant/{tenant}/…`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `restaurant/{tenant}` | restaurant team |  | `restaurant` limiter | Api\TenantController@show |
| PATCH | `restaurant/{tenant}/account/slug` | restaurant team |  | `restaurant` limiter | Api\AccountController@updateSlug |
| GET | `restaurant/{tenant}/activity` | restaurant team |  | `restaurant` limiter | Api\ActivityController |
| GET | `restaurant/{tenant}/addon-groups` | restaurant team |  | `restaurant` limiter | Api\Menu\AddonGroupController@index |
| POST | `restaurant/{tenant}/addon-groups` | restaurant team |  | `restaurant` limiter | Api\Menu\AddonGroupController@store |
| POST | `restaurant/{tenant}/addon-groups/reorder` | restaurant team |  | `restaurant` limiter | Api\Menu\AddonGroupController@reorder |
| DELETE | `restaurant/{tenant}/addon-groups/{group}` | restaurant team |  | `restaurant` limiter | Api\Menu\AddonGroupController@destroy |
| PATCH | `restaurant/{tenant}/addon-groups/{group}` | restaurant team |  | `restaurant` limiter | Api\Menu\AddonGroupController@update |
| PATCH | `restaurant/{tenant}/addon-options/{option}/sold-out` | restaurant team | `sold_out` | `restaurant` limiter | Api\Menu\AddonGroupController@optionSoldOut |
| GET | `restaurant/{tenant}/announcements` | restaurant team |  | `restaurant` limiter | Api\AnnouncementController@index |
| POST | `restaurant/{tenant}/announcements/{announcement}/read` | restaurant team |  | `restaurant` limiter | Api\AnnouncementController@read |
| GET | `restaurant/{tenant}/appearance` | restaurant team |  | `restaurant` limiter | Api\Restaurant\AppearanceController@show |
| PUT | `restaurant/{tenant}/appearance` | restaurant team |  | `restaurant` limiter | Api\Restaurant\AppearanceController@update |
| GET | `restaurant/{tenant}/appearance/preview` | restaurant team |  | `restaurant` limiter | Api\Restaurant\AppearanceController@preview |
| GET | `restaurant/{tenant}/billing` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BillingController@show |
| GET | `restaurant/{tenant}/billing/invoices/{invoice}/pdf` | restaurant team |  | `restaurant, exports` limiter | Api\Restaurant\BillingController@pdf |
| GET | `restaurant/{tenant}/branches` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@index |
| POST | `restaurant/{tenant}/branches` | restaurant team | `branches` | `restaurant` limiter | Api\Restaurant\BranchController@store |
| DELETE | `restaurant/{tenant}/branches/{branch}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@destroy |
| PATCH | `restaurant/{tenant}/branches/{branch}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@update |
| GET | `restaurant/{tenant}/branches/{branch}/overrides` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@overrides |
| PUT | `restaurant/{tenant}/branches/{branch}/overrides` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@saveOverrides |
| GET | `restaurant/{tenant}/branches/{branch}/table-cards.pdf` | restaurant team | `tables` | `restaurant, exports` limiter | Api\Restaurant\QrCodeController@tableCards |
| GET | `restaurant/{tenant}/branches/{branch}/tables` | restaurant team | `tables` | `restaurant` limiter | Api\Restaurant\BranchController@tables |
| POST | `restaurant/{tenant}/branches/{branch}/tables` | restaurant team | `tables` | `restaurant` limiter | Api\Restaurant\BranchController@addTables |
| POST | `restaurant/{tenant}/categories` | restaurant team |  | `restaurant` limiter | Api\Menu\CategoryController@store |
| POST | `restaurant/{tenant}/categories/reorder` | restaurant team |  | `restaurant` limiter | Api\Menu\CategoryController@reorder |
| DELETE | `restaurant/{tenant}/categories/{category}` | restaurant team |  | `restaurant` limiter | Api\Menu\CategoryController@destroy |
| PATCH | `restaurant/{tenant}/categories/{category}` | restaurant team |  | `restaurant` limiter | Api\Menu\CategoryController@update |
| POST | `restaurant/{tenant}/categories/{category}/duplicate` | restaurant team |  | `restaurant` limiter | Api\Menu\CategoryController@duplicate |
| GET | `restaurant/{tenant}/closures` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@closures |
| POST | `restaurant/{tenant}/closures` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@addClosure |
| DELETE | `restaurant/{tenant}/closures/{closure}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@deleteClosure |
| GET | `restaurant/{tenant}/dashboard` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InsightsController@dashboard |
| GET | `restaurant/{tenant}/export` | restaurant team | `import_export` | `restaurant, exports` limiter | Api\Menu\ImportController@export |
| GET | `restaurant/{tenant}/help` | restaurant team |  | `restaurant` limiter | Api\HelpController@restaurant |
| GET | `restaurant/{tenant}/hours` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@hours |
| PUT | `restaurant/{tenant}/hours` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@saveHours |
| POST | `restaurant/{tenant}/import/commit` | restaurant team | `import_export` | `restaurant` limiter | Api\Menu\ImportController@commit |
| POST | `restaurant/{tenant}/import/preview` | restaurant team | `import_export` | `restaurant, 20,1` limiter | Api\Menu\ImportController@preview |
| GET | `restaurant/{tenant}/import/template` | restaurant team | `import_export` | `restaurant` limiter | Api\Menu\ImportController@template |
| GET | `restaurant/{tenant}/insights` | restaurant team | `insights` | `restaurant` limiter | Api\Restaurant\InsightsController@index |
| GET | `restaurant/{tenant}/insights/export.csv` | restaurant team | `insights` | `restaurant, exports` limiter | Api\Restaurant\InsightsExportController@csv |
| GET | `restaurant/{tenant}/insights/report.pdf` | restaurant team | `insights` | `restaurant, exports` limiter | Api\Restaurant\InsightsExportController@pdf |
| GET | `restaurant/{tenant}/insights/settings` | restaurant team | `insights` | `restaurant` limiter | Api\Restaurant\InsightsExportController@settings |
| PUT | `restaurant/{tenant}/insights/settings` | restaurant team | `insights` | `restaurant` limiter | Api\Restaurant\InsightsExportController@updateSettings |
| GET | `restaurant/{tenant}/invitations` | restaurant team |  | `restaurant` limiter | Api\InvitationController@index |
| POST | `restaurant/{tenant}/invitations` | restaurant team |  | `restaurant` limiter | Api\InvitationController@store |
| DELETE | `restaurant/{tenant}/invitations/{invitation}` | restaurant team |  | `restaurant` limiter | Api\InvitationController@destroy |
| GET | `restaurant/{tenant}/items` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@index |
| POST | `restaurant/{tenant}/items` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@store |
| POST | `restaurant/{tenant}/items/bulk` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@bulk |
| POST | `restaurant/{tenant}/items/bulk-price` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@bulkPrice |
| POST | `restaurant/{tenant}/items/reorder` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@reorder |
| DELETE | `restaurant/{tenant}/items/{item}` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@destroy |
| GET | `restaurant/{tenant}/items/{item}` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@show |
| PATCH | `restaurant/{tenant}/items/{item}` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@update |
| POST | `restaurant/{tenant}/items/{item}/duplicate` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@duplicate |
| PATCH | `restaurant/{tenant}/items/{item}/price` | restaurant team |  | `restaurant` limiter | Api\Menu\ItemController@price |
| PATCH | `restaurant/{tenant}/items/{item}/sold-out` | restaurant team | `sold_out` | `restaurant` limiter | Api\Menu\ItemController@soldOut |
| POST | `restaurant/{tenant}/media` | restaurant team |  | `restaurant, 30,1` limiter | Api\Menu\MediaController@store |
| DELETE | `restaurant/{tenant}/media/{media}` | restaurant team |  | `restaurant` limiter | Api\Menu\MediaController@destroy |
| GET | `restaurant/{tenant}/members` | restaurant team |  | `restaurant` limiter | Api\TeamController@index |
| DELETE | `restaurant/{tenant}/members/{member}` | restaurant team |  | `restaurant` limiter | Api\TeamController@destroy |
| PATCH | `restaurant/{tenant}/members/{member}` | restaurant team |  | `restaurant` limiter | Api\TeamController@update |
| GET | `restaurant/{tenant}/menu-pdf` | restaurant team | `pdf_menu` | `restaurant, 20,1` limiter | Api\Restaurant\PdfMenuController@download |
| GET | `restaurant/{tenant}/menu-requests` | restaurant team |  | `restaurant` limiter | Api\Restaurant\MenuRequestController@index |
| POST | `restaurant/{tenant}/menu-requests` | restaurant team |  | `restaurant, 6,60` limiter | Api\Restaurant\MenuRequestController@store |
| GET | `restaurant/{tenant}/menus` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@index |
| POST | `restaurant/{tenant}/menus` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@store |
| POST | `restaurant/{tenant}/menus/reorder` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@reorder |
| DELETE | `restaurant/{tenant}/menus/{menu}` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@destroy |
| GET | `restaurant/{tenant}/menus/{menu}` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@show |
| PATCH | `restaurant/{tenant}/menus/{menu}` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@update |
| PUT | `restaurant/{tenant}/menus/{menu}/branches` | restaurant team |  | `restaurant` limiter | Api\Restaurant\BranchController@menuBranches |
| POST | `restaurant/{tenant}/menus/{menu}/duplicate` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@duplicate |
| PUT | `restaurant/{tenant}/menus/{menu}/schedules` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@schedules |
| GET | `restaurant/{tenant}/menus/{menu}/tree` | restaurant team |  | `restaurant` limiter | Api\Menu\MenuController@tree |
| GET | `restaurant/{tenant}/price-periods` | restaurant team | `price_periods` | `restaurant` limiter | Api\Restaurant\PricePeriodController@index |
| POST | `restaurant/{tenant}/price-periods` | restaurant team | `price_periods` | `restaurant` limiter | Api\Restaurant\PricePeriodController@store |
| GET | `restaurant/{tenant}/price-periods/preview` | restaurant team | `price_periods` | `restaurant` limiter | Api\Restaurant\PricePeriodController@preview |
| DELETE | `restaurant/{tenant}/price-periods/{period}` | restaurant team | `price_periods` | `restaurant` limiter | Api\Restaurant\PricePeriodController@destroy |
| PUT | `restaurant/{tenant}/price-periods/{period}` | restaurant team | `price_periods` | `restaurant` limiter | Api\Restaurant\PricePeriodController@update |
| GET | `restaurant/{tenant}/print-materials` | restaurant team | `print_materials` | `restaurant` limiter | Api\Restaurant\QrCodeController@printInfo |
| GET | `restaurant/{tenant}/print-materials.pdf` | restaurant team | `print_materials` | `restaurant, exports` limiter | Api\Restaurant\QrCodeController@printMaterials |
| GET | `restaurant/{tenant}/profile` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@profile |
| PUT | `restaurant/{tenant}/profile` | restaurant team |  | `restaurant` limiter | Api\Restaurant\InfoController@saveProfile |
| GET | `restaurant/{tenant}/promotions` | restaurant team |  | `restaurant` limiter | Api\Restaurant\PromotionController@index |
| POST | `restaurant/{tenant}/promotions` | restaurant team | `promotions` | `restaurant` limiter | Api\Restaurant\PromotionController@store |
| POST | `restaurant/{tenant}/promotions/reorder` | restaurant team | `promotions` | `restaurant` limiter | Api\Restaurant\PromotionController@reorder |
| DELETE | `restaurant/{tenant}/promotions/{promotion}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\PromotionController@destroy |
| PATCH | `restaurant/{tenant}/promotions/{promotion}` | restaurant team | `promotions` | `restaurant` limiter | Api\Restaurant\PromotionController@update |
| GET | `restaurant/{tenant}/qr-codes` | restaurant team |  | `restaurant` limiter | Api\Restaurant\QrCodeController@index |
| POST | `restaurant/{tenant}/qr-codes` | restaurant team |  | `restaurant` limiter | Api\Restaurant\QrCodeController@store |
| DELETE | `restaurant/{tenant}/qr-codes/{qr}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\QrCodeController@destroy |
| PATCH | `restaurant/{tenant}/qr-codes/{qr}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\QrCodeController@update |
| GET | `restaurant/{tenant}/qr-codes/{qr}.{format}` | restaurant team |  | `restaurant` limiter | Api\Restaurant\QrCodeController@download |
| DELETE | `restaurant/{tenant}/tables/{table}` | restaurant team | `tables` | `restaurant` limiter | Api\Restaurant\BranchController@deleteTable |
| GET | `restaurant/{tenant}/tags` | restaurant team |  | `restaurant` limiter | Api\Menu\TagController@index |
| POST | `restaurant/{tenant}/tags` | restaurant team |  | `restaurant` limiter | Api\Menu\TagController@store |
| DELETE | `restaurant/{tenant}/tags/{tag}` | restaurant team |  | `restaurant` limiter | Api\Menu\TagController@destroy |
| PATCH | `restaurant/{tenant}/tags/{tag}` | restaurant team |  | `restaurant` limiter | Api\Menu\TagController@update |
| GET | `restaurant/{tenant}/widgets/missing-photos` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@missingPhotos |
| GET | `restaurant/{tenant}/widgets/orders` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@orders |
| GET | `restaurant/{tenant}/widgets/plan` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@plan |
| GET | `restaurant/{tenant}/widgets/setup` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@setup |
| POST | `restaurant/{tenant}/widgets/setup/hide` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@hideSetup |
| GET | `restaurant/{tenant}/widgets/trend` | restaurant team |  | `restaurant` limiter | Api\Restaurant\DashboardWidgetController@trend |

## Super Admin (`super/…`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `super/activity` | Super Admin |  | `super` limiter | Platform\PlatformController@activity |
| PUT | `super/alerts` | Super Admin |  | `super` limiter | Platform\BackupController@alerts |
| POST | `super/alerts/test` | Super Admin |  | `super, 5,1` limiter | Platform\BackupController@testAlert |
| GET | `super/announcements` | Super Admin |  | `super` limiter | Platform\PlatformController@announcements |
| POST | `super/announcements` | Super Admin |  | `super` limiter | Platform\PlatformController@saveAnnouncement |
| DELETE | `super/announcements/{announcement}` | Super Admin |  | `super` limiter | Platform\PlatformController@deleteAnnouncement |
| PATCH | `super/announcements/{announcement}` | Super Admin |  | `super` limiter | Platform\PlatformController@saveAnnouncement |
| GET | `super/backups` | Super Admin |  | `super` limiter | Platform\BackupController@index |
| POST | `super/backups` | Super Admin |  | `super, 6,1` limiter | Platform\BackupController@store |
| PUT | `super/backups/settings` | Super Admin |  | `super` limiter | Platform\BackupController@settings |
| DELETE | `super/backups/{backup}` | Super Admin |  | `super` limiter | Platform\BackupController@destroy |
| GET | `super/backups/{backup}/file` | Super Admin |  | `super` limiter | Platform\BackupController@file |
| POST | `super/backups/{backup}/link` | Super Admin |  | `super, 6,1` limiter | Platform\BackupController@link |
| GET | `super/campaigns` | Super Admin |  | `super` limiter | Platform\CampaignController@index |
| POST | `super/campaigns` | Super Admin |  | `super` limiter | Platform\CampaignController@store |
| POST | `super/campaigns/count` | Super Admin |  | `super` limiter | Platform\CampaignController@count |
| POST | `super/campaigns/preview` | Super Admin |  | `super` limiter | Platform\CampaignController@preview |
| PUT | `super/campaigns/rate` | Super Admin |  | `super` limiter | Platform\CampaignController@rate |
| POST | `super/campaigns/test` | Super Admin |  | `super, 10,1` limiter | Platform\CampaignController@test |
| GET | `super/campaigns/{campaign}` | Super Admin |  | `super` limiter | Platform\CampaignController@show |
| POST | `super/campaigns/{campaign}/retry` | Super Admin |  | `super` limiter | Platform\CampaignController@retry |
| GET | `super/dashboard` | Super Admin |  | `super` limiter | Platform\PlatformController@dashboard |
| GET | `super/footer` | Super Admin |  | `super` limiter | Platform\FooterController@show |
| PUT | `super/footer` | Super Admin |  | `super, 60,1` limiter | Platform\FooterController@update |
| DELETE | `super/footer/draft` | Super Admin |  | `super` limiter | Platform\FooterController@discard |
| GET | `super/footer/preview` | Super Admin |  | `super, 120,1` limiter | Platform\FooterController@preview |
| POST | `super/footer/publish` | Super Admin |  | `super, 20,1` limiter | Platform\FooterController@publish |
| POST | `super/footer/versions/{version}/restore` | Super Admin |  | `super, 20,1` limiter | Platform\FooterController@restore |
| GET | `super/help` | Super Admin |  | `super` limiter | Api\HelpController@platform |
| GET | `super/help-articles` | Super Admin |  | `super` limiter | Platform\HelpArticleController@index |
| POST | `super/help-articles/preview` | Super Admin |  | `super, 60,1` limiter | Platform\HelpArticleController@preview |
| DELETE | `super/help-articles/{audience}/{slug}` | Super Admin |  | `super` limiter | Platform\HelpArticleController@destroy |
| GET | `super/help-articles/{audience}/{slug}` | Super Admin |  | `super` limiter | Platform\HelpArticleController@show |
| PUT | `super/help-articles/{audience}/{slug}` | Super Admin |  | `super` limiter | Platform\HelpArticleController@update |
| GET | `super/insights` | Super Admin |  | `super` limiter | Platform\InsightsController@meta |
| GET | `super/insights/{widget}` | Super Admin |  | `super` limiter | Platform\InsightsController@show |
| GET | `super/invoices` | Super Admin |  | `super` limiter | Platform\InvoiceController@index |
| GET | `super/invoices.csv` | Super Admin |  | `super, exports` limiter | Platform\InvoiceController@export |
| GET | `super/invoices/{invoice}/pdf` | Super Admin |  | `super, exports` limiter | Platform\InvoiceController@pdf |
| POST | `super/invoices/{invoice}/resend` | Super Admin |  | `super, 10,1` limiter | Platform\InvoiceController@resend |
| POST | `super/invoices/{invoice}/void` | Super Admin |  | `super` limiter | Platform\InvoiceController@void |
| GET | `super/landing` | Super Admin |  | `super` limiter | Platform\LandingController@show |
| PUT | `super/landing` | Super Admin |  | `super` limiter | Platform\LandingController@update |
| DELETE | `super/landing/images/{slot}` | Super Admin |  | `super` limiter | Platform\LandingController@deleteImage |
| POST | `super/landing/images/{slot}` | Super Admin |  | `super, 20,1` limiter | Platform\LandingController@saveImage |
| GET | `super/landing2` | Super Admin |  | `super` limiter | Platform\Landing2Controller@show |
| PUT | `super/landing2` | Super Admin |  | `super, 60,1` limiter | Platform\Landing2Controller@update |
| POST | `super/landing2/restaurants` | Super Admin |  | `super, 30,1` limiter | Platform\Landing2Controller@storeRestaurant |
| DELETE | `super/landing2/restaurants/{restaurant}` | Super Admin |  | `super` limiter | Platform\Landing2Controller@destroyRestaurant |
| PUT | `super/landing2/restaurants/{restaurant}` | Super Admin |  | `super` limiter | Platform\Landing2Controller@updateRestaurant |
| DELETE | `super/landing2/restaurants/{restaurant}/logo` | Super Admin |  | `super` limiter | Platform\Landing2Controller@removeLogo |
| POST | `super/landing2/restaurants/{restaurant}/logo` | Super Admin |  | `super, 20,1` limiter | Platform\Landing2Controller@saveLogo |
| DELETE | `super/landing2/video` | Super Admin |  | `super` limiter | Platform\Landing2Controller@deleteVideo |
| POST | `super/landing2/video` | Super Admin |  | `super, 10,1` limiter | Platform\Landing2Controller@saveVideo |
| GET | `super/menu-requests` | Super Admin |  | `super` limiter | Platform\MenuRequestController@index |
| DELETE | `super/menu-requests/{menuRequest}` | Super Admin |  | `super` limiter | Platform\MenuRequestController@destroy |
| PUT | `super/menu-requests/{menuRequest}` | Super Admin |  | `super` limiter | Platform\MenuRequestController@update |
| GET | `super/menu-requests/{menuRequest}/files/{n}` | Super Admin |  | `super` limiter | Platform\MenuRequestController@file |
| GET | `super/mobile-app` | Super Admin |  | `super` limiter | Platform\MobileAppController@show |
| PUT | `super/mobile-app` | Super Admin |  | `super` limiter | Platform\MobileAppController@update |
| GET | `super/mobile-app/devices` | Super Admin |  | `super` limiter | Platform\MobileAppController@devices |
| POST | `super/mobile-app/devices/sign-out-all` | Super Admin |  | `super, 3,1` limiter | Platform\MobileAppController@signOutAll |
| DELETE | `super/mobile-app/devices/{device}` | Super Admin |  | `super` limiter | Platform\MobileAppController@signOutDevice |
| GET | `super/mobile-app/pushes` | Super Admin |  | `super` limiter | Platform\MobileAppController@pushes |
| POST | `super/mobile-app/pushes` | Super Admin |  | `super, 10,1` limiter | Platform\MobileAppController@sendPush |
| GET | `super/mobile-app/stats` | Super Admin |  | `super` limiter | Platform\MobileAppController@stats |
| DELETE | `super/notes/{note}` | Super Admin |  | `super` limiter | Platform\CrmController@destroyNote |
| PATCH | `super/notes/{note}` | Super Admin |  | `super` limiter | Platform\CrmController@updateNote |
| GET | `super/payments` | Super Admin |  | `super` limiter | Platform\PaymentController@index |
| GET | `super/payments.csv` | Super Admin |  | `super, exports` limiter | Platform\PaymentController@export |
| GET | `super/plans` | Super Admin |  | `super` limiter | Platform\PlatformController@plans |
| POST | `super/plans` | Super Admin |  | `super` limiter | Platform\PlatformController@savePlan |
| PATCH | `super/plans/{plan}` | Super Admin |  | `super` limiter | Platform\PlatformController@savePlan |
| GET | `super/restaurants` | Super Admin |  | `super` limiter | Platform\RestaurantController@index |
| POST | `super/restaurants` | Super Admin |  | `super` limiter | Platform\RestaurantController@store |
| POST | `super/restaurants/bulk-tag` | Super Admin |  | `super` limiter | Platform\RestaurantController@bulkTag |
| DELETE | `super/restaurants/{restaurant}` | Super Admin |  | `super` limiter | Platform\RestaurantController@destroy |
| GET | `super/restaurants/{restaurant}` | Super Admin |  | `super` limiter | Platform\RestaurantController@show |
| PATCH | `super/restaurants/{restaurant}` | Super Admin |  | `super` limiter | Platform\RestaurantController@update |
| POST | `super/restaurants/{restaurant}/approve` | Super Admin |  | `super` limiter | Platform\RestaurantController@approve |
| PUT | `super/restaurants/{restaurant}/features` | Super Admin |  | `super` limiter | Platform\RestaurantController@features |
| PUT | `super/restaurants/{restaurant}/follow-up` | Super Admin |  | `super` limiter | Platform\CrmController@followUp |
| POST | `super/restaurants/{restaurant}/impersonate` | Super Admin |  | `super` limiter | Platform\RestaurantController@impersonate |
| POST | `super/restaurants/{restaurant}/impersonate-token` | Super Admin, app token |  | `super` limiter | Platform\RestaurantController@impersonateToken |
| GET | `super/restaurants/{restaurant}/notes` | Super Admin |  | `super` limiter | Platform\CrmController@notes |
| POST | `super/restaurants/{restaurant}/notes` | Super Admin |  | `super` limiter | Platform\CrmController@storeNote |
| POST | `super/restaurants/{restaurant}/payments` | Super Admin |  | `super` limiter | Platform\PaymentController@store |
| POST | `super/restaurants/{restaurant}/reject` | Super Admin |  | `super` limiter | Platform\RestaurantController@reject |
| POST | `super/restaurants/{restaurant}/resend-welcome` | Super Admin |  | `super, 6,1` limiter | Platform\RestaurantController@resendWelcome |
| POST | `super/restaurants/{restaurant}/restore` | Super Admin |  | `super` limiter | Platform\RestaurantController@restore |
| POST | `super/restaurants/{restaurant}/suspend` | Super Admin |  | `super` limiter | Platform\RestaurantController@suspend |
| PUT | `super/restaurants/{restaurant}/tags` | Super Admin |  | `super` limiter | Platform\CrmController@setTags |
| POST | `super/restaurants/{restaurant}/unsuspend` | Super Admin |  | `super` limiter | Platform\RestaurantController@unsuspend |
| GET | `super/search` | Super Admin |  | `super, 120,1` limiter | Platform\SearchController |
| GET | `super/settings` | Super Admin |  | `super` limiter | Platform\PlatformController@settings |
| PUT | `super/settings` | Super Admin |  | `super` limiter | Platform\PlatformController@saveSettings |
| GET | `super/settings/features` | Super Admin |  | `super` limiter | Platform\FeatureSwitchController@index |
| PUT | `super/settings/features` | Super Admin |  | `super` limiter | Platform\FeatureSwitchController@update |
| GET | `super/settings/features/{key}/impact` | Super Admin |  | `super` limiter | Platform\FeatureSwitchController@impact |
| POST | `super/settings/legal/{page}/reset` | Super Admin |  | `super` limiter | Platform\PlatformController@resetLegal |
| DELETE | `super/settings/logo` | Super Admin |  | `super` limiter | Platform\PlatformController@deleteLogo |
| POST | `super/settings/logo` | Super Admin |  | `super` limiter | Platform\PlatformController@saveLogo |
| DELETE | `super/settings/mail` | Super Admin |  | `super` limiter | Platform\PlatformController@clearMailSettings |
| GET | `super/settings/mail` | Super Admin |  | `super` limiter | Platform\PlatformController@mailSettings |
| PUT | `super/settings/mail` | Super Admin |  | `super` limiter | Platform\PlatformController@saveMailSettings |
| POST | `super/settings/mail/test` | Super Admin |  | `super, 5,1` limiter | Platform\PlatformController@testMail |
| DELETE | `super/settings/share-image` | Super Admin |  | `super` limiter | Platform\PlatformController@deleteShareImage |
| POST | `super/settings/share-image` | Super Admin |  | `super` limiter | Platform\PlatformController@saveShareImage |
| GET | `super/setup` | Super Admin |  | `super` limiter | Platform\SetupController@show |
| PUT | `super/setup` | Super Admin |  | `super` limiter | Platform\SetupController@update |
| POST | `super/setup/demo/delete` | Super Admin |  | `super, 3,1` limiter | Platform\SetupController@deleteDemo |
| GET | `super/tags` | Super Admin |  | `super` limiter | Platform\CrmController@tags |
| POST | `super/tags` | Super Admin |  | `super` limiter | Platform\CrmController@storeTag |
| DELETE | `super/tags/{tag}` | Super Admin |  | `super` limiter | Platform\CrmController@destroyTag |
| PATCH | `super/tags/{tag}` | Super Admin |  | `super` limiter | Platform\CrmController@updateTag |
| GET | `super/themes` | Super Admin |  | `super` limiter | Platform\PlatformController@themes |
| POST | `super/themes` | Super Admin |  | `super` limiter | Platform\PlatformController@saveTheme |
| GET | `super/themes/preview` | Super Admin |  | `super, 120,1` limiter | Platform\ThemePreviewController@show |
| PATCH | `super/themes/{theme}` | Super Admin |  | `super` limiter | Platform\PlatformController@saveTheme |
| POST | `super/widgets/email/{tenant}` | Super Admin |  | `super, 20,1` limiter | Platform\DashboardWidgetController@email |
| GET | `super/widgets/expiring` | Super Admin |  | `super` limiter | Platform\DashboardWidgetController@expiring |
| GET | `super/widgets/follow-ups` | Super Admin |  | `super` limiter | Platform\CrmController@due |
| GET | `super/widgets/health` | Super Admin |  | `super` limiter | Platform\DashboardWidgetController@health |
| GET | `super/widgets/inactive` | Super Admin |  | `super` limiter | Platform\DashboardWidgetController@inactive |
| GET | `super/widgets/plan-mix` | Super Admin |  | `super` limiter | Platform\DashboardWidgetController@planMix |
| GET | `super/widgets/signups` | Super Admin |  | `super` limiter | Platform\DashboardWidgetController@signups |

## Guest menu (public)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `beacon` | anyone |  | 120 a minute | Api\BeaconController |

## Other (`help`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `help/feedback` | signed in |  | 30 a minute | Api\HelpController@feedback |
| GET | `help/public` | anyone |  | 30 a minute | Api\HelpController@public |

## Other (`landing`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `landing/click` | anyone |  | `landing-click` limiter | LandingClickController |
