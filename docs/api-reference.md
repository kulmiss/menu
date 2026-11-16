# API reference (generated)

<!-- Written by `php artisan docs:api-reference`. Do not edit by hand: a test fails when routes change and this file is not written again. -->

Every `/api/v1` endpoint, from the routes. **Who**: who may call it (roles inside a restaurant are checked by each screen's policy). **Feature**: the feature switch it needs (`402 features.<key>` when off). **Limit**: how many requests are allowed. Create/change requests need an `Idempotency-Key` header. Request and answer examples for the app: [mobile-api.md](mobile-api.md).

**258 endpoints.**

## Sign-in and account (web session)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `auth/email/verification-notification` | signed in |  | 6 a minute | Fortify\EmailVerificationNotificationController@store |
| GET | `auth/email/verify/{id}/{hash}` | signed in |  | 6 a minute | Fortify\VerifyEmailController@__invoke |
| POST | `auth/forgot-password` | signed out |  |  | Fortify\PasswordResetLinkController@store |
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
| PUT | `me/news` | signed in |  |  | NewsSubscriptionController@update |
| PUT | `me/onboarding` | signed in |  | 30 a minute | Api\OnboardingController |
| POST | `me/sessions/logout-others` | signed in |  | 6 a minute | Api\SessionController@logoutOthers |

## Self sign-up

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `signup` | signed out |  | 10 a minute | Auth\SignUpController@store |
| GET | `signup/config` | anyone |  | 60 a minute | Auth\SignUpController@config |
| POST | `signup/email` | signed in |  | 6 a minute | Auth\SignUpController@changeEmail |
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

## Restaurant (`t/{tenant}/…`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `t/{tenant}` | restaurant team |  |  | Api\TenantController@show |
| PATCH | `t/{tenant}/account/slug` | restaurant team |  |  | Api\AccountController@updateSlug |
| GET | `t/{tenant}/activity` | restaurant team |  |  | Api\ActivityController |
| GET | `t/{tenant}/addon-groups` | restaurant team |  |  | Api\Menu\AddonGroupController@index |
| POST | `t/{tenant}/addon-groups` | restaurant team |  |  | Api\Menu\AddonGroupController@store |
| POST | `t/{tenant}/addon-groups/reorder` | restaurant team |  |  | Api\Menu\AddonGroupController@reorder |
| DELETE | `t/{tenant}/addon-groups/{group}` | restaurant team |  |  | Api\Menu\AddonGroupController@destroy |
| PATCH | `t/{tenant}/addon-groups/{group}` | restaurant team |  |  | Api\Menu\AddonGroupController@update |
| PATCH | `t/{tenant}/addon-options/{option}/sold-out` | restaurant team | `sold_out` |  | Api\Menu\AddonGroupController@optionSoldOut |
| GET | `t/{tenant}/announcements` | restaurant team |  |  | Api\AnnouncementController@index |
| POST | `t/{tenant}/announcements/{announcement}/read` | restaurant team |  |  | Api\AnnouncementController@read |
| GET | `t/{tenant}/appearance` | restaurant team |  |  | Api\Restaurant\AppearanceController@show |
| PUT | `t/{tenant}/appearance` | restaurant team |  |  | Api\Restaurant\AppearanceController@update |
| GET | `t/{tenant}/appearance/preview` | restaurant team |  |  | Api\Restaurant\AppearanceController@preview |
| GET | `t/{tenant}/billing` | restaurant team |  |  | Api\Restaurant\BillingController@show |
| GET | `t/{tenant}/billing/invoices/{invoice}/pdf` | restaurant team |  | `exports` limiter | Api\Restaurant\BillingController@pdf |
| GET | `t/{tenant}/branches` | restaurant team |  |  | Api\Restaurant\BranchController@index |
| POST | `t/{tenant}/branches` | restaurant team | `branches` |  | Api\Restaurant\BranchController@store |
| DELETE | `t/{tenant}/branches/{branch}` | restaurant team |  |  | Api\Restaurant\BranchController@destroy |
| PATCH | `t/{tenant}/branches/{branch}` | restaurant team |  |  | Api\Restaurant\BranchController@update |
| GET | `t/{tenant}/branches/{branch}/overrides` | restaurant team |  |  | Api\Restaurant\BranchController@overrides |
| PUT | `t/{tenant}/branches/{branch}/overrides` | restaurant team |  |  | Api\Restaurant\BranchController@saveOverrides |
| GET | `t/{tenant}/branches/{branch}/table-cards.pdf` | restaurant team |  | `exports` limiter | Api\Restaurant\QrCodeController@tableCards |
| GET | `t/{tenant}/branches/{branch}/tables` | restaurant team |  |  | Api\Restaurant\BranchController@tables |
| POST | `t/{tenant}/branches/{branch}/tables` | restaurant team |  |  | Api\Restaurant\BranchController@addTables |
| POST | `t/{tenant}/categories` | restaurant team |  |  | Api\Menu\CategoryController@store |
| POST | `t/{tenant}/categories/reorder` | restaurant team |  |  | Api\Menu\CategoryController@reorder |
| DELETE | `t/{tenant}/categories/{category}` | restaurant team |  |  | Api\Menu\CategoryController@destroy |
| PATCH | `t/{tenant}/categories/{category}` | restaurant team |  |  | Api\Menu\CategoryController@update |
| POST | `t/{tenant}/categories/{category}/duplicate` | restaurant team |  |  | Api\Menu\CategoryController@duplicate |
| GET | `t/{tenant}/closures` | restaurant team |  |  | Api\Restaurant\InfoController@closures |
| POST | `t/{tenant}/closures` | restaurant team |  |  | Api\Restaurant\InfoController@addClosure |
| DELETE | `t/{tenant}/closures/{closure}` | restaurant team |  |  | Api\Restaurant\InfoController@deleteClosure |
| GET | `t/{tenant}/dashboard` | restaurant team |  |  | Api\Restaurant\InsightsController@dashboard |
| GET | `t/{tenant}/export` | restaurant team | `import_export` | `exports` limiter | Api\Menu\ImportController@export |
| GET | `t/{tenant}/help` | restaurant team |  |  | Api\HelpController@restaurant |
| GET | `t/{tenant}/hours` | restaurant team |  |  | Api\Restaurant\InfoController@hours |
| PUT | `t/{tenant}/hours` | restaurant team |  |  | Api\Restaurant\InfoController@saveHours |
| POST | `t/{tenant}/import/commit` | restaurant team | `import_export` |  | Api\Menu\ImportController@commit |
| POST | `t/{tenant}/import/preview` | restaurant team | `import_export` | 20 a minute | Api\Menu\ImportController@preview |
| GET | `t/{tenant}/import/template` | restaurant team | `import_export` |  | Api\Menu\ImportController@template |
| GET | `t/{tenant}/insights` | restaurant team | `insights` |  | Api\Restaurant\InsightsController@index |
| GET | `t/{tenant}/insights/export.csv` | restaurant team | `insights` | `exports` limiter | Api\Restaurant\InsightsExportController@csv |
| GET | `t/{tenant}/insights/report.pdf` | restaurant team | `insights` | `exports` limiter | Api\Restaurant\InsightsExportController@pdf |
| GET | `t/{tenant}/insights/settings` | restaurant team | `insights` |  | Api\Restaurant\InsightsExportController@settings |
| PUT | `t/{tenant}/insights/settings` | restaurant team | `insights` |  | Api\Restaurant\InsightsExportController@updateSettings |
| GET | `t/{tenant}/invitations` | restaurant team |  |  | Api\InvitationController@index |
| POST | `t/{tenant}/invitations` | restaurant team |  |  | Api\InvitationController@store |
| DELETE | `t/{tenant}/invitations/{invitation}` | restaurant team |  |  | Api\InvitationController@destroy |
| GET | `t/{tenant}/items` | restaurant team |  |  | Api\Menu\ItemController@index |
| POST | `t/{tenant}/items` | restaurant team |  |  | Api\Menu\ItemController@store |
| POST | `t/{tenant}/items/bulk` | restaurant team |  |  | Api\Menu\ItemController@bulk |
| POST | `t/{tenant}/items/bulk-price` | restaurant team |  |  | Api\Menu\ItemController@bulkPrice |
| POST | `t/{tenant}/items/reorder` | restaurant team |  |  | Api\Menu\ItemController@reorder |
| DELETE | `t/{tenant}/items/{item}` | restaurant team |  |  | Api\Menu\ItemController@destroy |
| GET | `t/{tenant}/items/{item}` | restaurant team |  |  | Api\Menu\ItemController@show |
| PATCH | `t/{tenant}/items/{item}` | restaurant team |  |  | Api\Menu\ItemController@update |
| POST | `t/{tenant}/items/{item}/duplicate` | restaurant team |  |  | Api\Menu\ItemController@duplicate |
| PATCH | `t/{tenant}/items/{item}/price` | restaurant team |  |  | Api\Menu\ItemController@price |
| PATCH | `t/{tenant}/items/{item}/sold-out` | restaurant team | `sold_out` |  | Api\Menu\ItemController@soldOut |
| POST | `t/{tenant}/media` | restaurant team |  | 30 a minute | Api\Menu\MediaController@store |
| DELETE | `t/{tenant}/media/{media}` | restaurant team |  |  | Api\Menu\MediaController@destroy |
| GET | `t/{tenant}/members` | restaurant team |  |  | Api\TeamController@index |
| DELETE | `t/{tenant}/members/{member}` | restaurant team |  |  | Api\TeamController@destroy |
| PATCH | `t/{tenant}/members/{member}` | restaurant team |  |  | Api\TeamController@update |
| GET | `t/{tenant}/menu-pdf` | restaurant team | `pdf_menu` | 20 a minute | Api\Restaurant\PdfMenuController@download |
| GET | `t/{tenant}/menus` | restaurant team |  |  | Api\Menu\MenuController@index |
| POST | `t/{tenant}/menus` | restaurant team |  |  | Api\Menu\MenuController@store |
| POST | `t/{tenant}/menus/reorder` | restaurant team |  |  | Api\Menu\MenuController@reorder |
| DELETE | `t/{tenant}/menus/{menu}` | restaurant team |  |  | Api\Menu\MenuController@destroy |
| GET | `t/{tenant}/menus/{menu}` | restaurant team |  |  | Api\Menu\MenuController@show |
| PATCH | `t/{tenant}/menus/{menu}` | restaurant team |  |  | Api\Menu\MenuController@update |
| PUT | `t/{tenant}/menus/{menu}/branches` | restaurant team |  |  | Api\Restaurant\BranchController@menuBranches |
| POST | `t/{tenant}/menus/{menu}/duplicate` | restaurant team |  |  | Api\Menu\MenuController@duplicate |
| PUT | `t/{tenant}/menus/{menu}/schedules` | restaurant team |  |  | Api\Menu\MenuController@schedules |
| GET | `t/{tenant}/menus/{menu}/tree` | restaurant team |  |  | Api\Menu\MenuController@tree |
| GET | `t/{tenant}/price-periods` | restaurant team | `price_periods` |  | Api\Restaurant\PricePeriodController@index |
| POST | `t/{tenant}/price-periods` | restaurant team | `price_periods` |  | Api\Restaurant\PricePeriodController@store |
| GET | `t/{tenant}/price-periods/preview` | restaurant team | `price_periods` |  | Api\Restaurant\PricePeriodController@preview |
| DELETE | `t/{tenant}/price-periods/{period}` | restaurant team | `price_periods` |  | Api\Restaurant\PricePeriodController@destroy |
| PUT | `t/{tenant}/price-periods/{period}` | restaurant team | `price_periods` |  | Api\Restaurant\PricePeriodController@update |
| GET | `t/{tenant}/print-materials` | restaurant team | `print_materials` |  | Api\Restaurant\QrCodeController@printInfo |
| GET | `t/{tenant}/print-materials.pdf` | restaurant team | `print_materials` | `exports` limiter | Api\Restaurant\QrCodeController@printMaterials |
| GET | `t/{tenant}/profile` | restaurant team |  |  | Api\Restaurant\InfoController@profile |
| PUT | `t/{tenant}/profile` | restaurant team |  |  | Api\Restaurant\InfoController@saveProfile |
| GET | `t/{tenant}/promotions` | restaurant team |  |  | Api\Restaurant\PromotionController@index |
| POST | `t/{tenant}/promotions` | restaurant team | `promotions` |  | Api\Restaurant\PromotionController@store |
| POST | `t/{tenant}/promotions/reorder` | restaurant team | `promotions` |  | Api\Restaurant\PromotionController@reorder |
| DELETE | `t/{tenant}/promotions/{promotion}` | restaurant team |  |  | Api\Restaurant\PromotionController@destroy |
| PATCH | `t/{tenant}/promotions/{promotion}` | restaurant team | `promotions` |  | Api\Restaurant\PromotionController@update |
| GET | `t/{tenant}/qr-codes` | restaurant team |  |  | Api\Restaurant\QrCodeController@index |
| POST | `t/{tenant}/qr-codes` | restaurant team |  |  | Api\Restaurant\QrCodeController@store |
| DELETE | `t/{tenant}/qr-codes/{qr}` | restaurant team |  |  | Api\Restaurant\QrCodeController@destroy |
| PATCH | `t/{tenant}/qr-codes/{qr}` | restaurant team |  |  | Api\Restaurant\QrCodeController@update |
| GET | `t/{tenant}/qr-codes/{qr}.{format}` | restaurant team |  |  | Api\Restaurant\QrCodeController@download |
| DELETE | `t/{tenant}/tables/{table}` | restaurant team |  |  | Api\Restaurant\BranchController@deleteTable |
| GET | `t/{tenant}/tags` | restaurant team |  |  | Api\Menu\TagController@index |
| POST | `t/{tenant}/tags` | restaurant team |  |  | Api\Menu\TagController@store |
| DELETE | `t/{tenant}/tags/{tag}` | restaurant team |  |  | Api\Menu\TagController@destroy |
| PATCH | `t/{tenant}/tags/{tag}` | restaurant team |  |  | Api\Menu\TagController@update |
| GET | `t/{tenant}/widgets/missing-photos` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@missingPhotos |
| GET | `t/{tenant}/widgets/orders` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@orders |
| GET | `t/{tenant}/widgets/plan` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@plan |
| GET | `t/{tenant}/widgets/setup` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@setup |
| POST | `t/{tenant}/widgets/setup/hide` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@hideSetup |
| GET | `t/{tenant}/widgets/trend` | restaurant team |  |  | Api\Restaurant\DashboardWidgetController@trend |

## Super Admin (`platform/…`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| GET | `platform/activity` | Super Admin |  |  | Platform\PlatformController@activity |
| GET | `platform/announcements` | Super Admin |  |  | Platform\PlatformController@announcements |
| POST | `platform/announcements` | Super Admin |  |  | Platform\PlatformController@saveAnnouncement |
| DELETE | `platform/announcements/{announcement}` | Super Admin |  |  | Platform\PlatformController@deleteAnnouncement |
| PATCH | `platform/announcements/{announcement}` | Super Admin |  |  | Platform\PlatformController@saveAnnouncement |
| GET | `platform/backups` | Super Admin |  |  | Platform\BackupController@index |
| POST | `platform/backups` | Super Admin |  | 6 a minute | Platform\BackupController@store |
| PUT | `platform/backups/settings` | Super Admin |  |  | Platform\BackupController@settings |
| DELETE | `platform/backups/{backup}` | Super Admin |  |  | Platform\BackupController@destroy |
| GET | `platform/backups/{backup}/file` | Super Admin |  |  | Platform\BackupController@file |
| POST | `platform/backups/{backup}/link` | Super Admin |  | 6 a minute | Platform\BackupController@link |
| GET | `platform/campaigns` | Super Admin |  |  | Platform\CampaignController@index |
| POST | `platform/campaigns` | Super Admin |  |  | Platform\CampaignController@store |
| POST | `platform/campaigns/count` | Super Admin |  |  | Platform\CampaignController@count |
| POST | `platform/campaigns/preview` | Super Admin |  |  | Platform\CampaignController@preview |
| PUT | `platform/campaigns/rate` | Super Admin |  |  | Platform\CampaignController@rate |
| POST | `platform/campaigns/test` | Super Admin |  | 10 a minute | Platform\CampaignController@test |
| GET | `platform/campaigns/{campaign}` | Super Admin |  |  | Platform\CampaignController@show |
| POST | `platform/campaigns/{campaign}/retry` | Super Admin |  |  | Platform\CampaignController@retry |
| GET | `platform/dashboard` | Super Admin |  |  | Platform\PlatformController@dashboard |
| GET | `platform/help` | Super Admin |  |  | Api\HelpController@platform |
| GET | `platform/help-articles` | Super Admin |  |  | Platform\HelpArticleController@index |
| POST | `platform/help-articles/preview` | Super Admin |  | 60 a minute | Platform\HelpArticleController@preview |
| DELETE | `platform/help-articles/{audience}/{slug}` | Super Admin |  |  | Platform\HelpArticleController@destroy |
| GET | `platform/help-articles/{audience}/{slug}` | Super Admin |  |  | Platform\HelpArticleController@show |
| PUT | `platform/help-articles/{audience}/{slug}` | Super Admin |  |  | Platform\HelpArticleController@update |
| GET | `platform/insights` | Super Admin |  |  | Platform\InsightsController@meta |
| GET | `platform/insights/{widget}` | Super Admin |  |  | Platform\InsightsController@show |
| GET | `platform/invoices` | Super Admin |  |  | Platform\InvoiceController@index |
| GET | `platform/invoices.csv` | Super Admin |  | `exports` limiter | Platform\InvoiceController@export |
| GET | `platform/invoices/{invoice}/pdf` | Super Admin |  | `exports` limiter | Platform\InvoiceController@pdf |
| POST | `platform/invoices/{invoice}/resend` | Super Admin |  | 10 a minute | Platform\InvoiceController@resend |
| POST | `platform/invoices/{invoice}/void` | Super Admin |  |  | Platform\InvoiceController@void |
| GET | `platform/mobile-app` | Super Admin |  |  | Platform\MobileAppController@show |
| PUT | `platform/mobile-app` | Super Admin |  |  | Platform\MobileAppController@update |
| GET | `platform/mobile-app/devices` | Super Admin |  |  | Platform\MobileAppController@devices |
| POST | `platform/mobile-app/devices/sign-out-all` | Super Admin |  | 3 a minute | Platform\MobileAppController@signOutAll |
| DELETE | `platform/mobile-app/devices/{device}` | Super Admin |  |  | Platform\MobileAppController@signOutDevice |
| GET | `platform/mobile-app/pushes` | Super Admin |  |  | Platform\MobileAppController@pushes |
| POST | `platform/mobile-app/pushes` | Super Admin |  | 10 a minute | Platform\MobileAppController@sendPush |
| GET | `platform/mobile-app/stats` | Super Admin |  |  | Platform\MobileAppController@stats |
| DELETE | `platform/notes/{note}` | Super Admin |  |  | Platform\CrmController@destroyNote |
| PATCH | `platform/notes/{note}` | Super Admin |  |  | Platform\CrmController@updateNote |
| GET | `platform/payments` | Super Admin |  |  | Platform\PaymentController@index |
| GET | `platform/payments.csv` | Super Admin |  | `exports` limiter | Platform\PaymentController@export |
| GET | `platform/plans` | Super Admin |  |  | Platform\PlatformController@plans |
| POST | `platform/plans` | Super Admin |  |  | Platform\PlatformController@savePlan |
| PATCH | `platform/plans/{plan}` | Super Admin |  |  | Platform\PlatformController@savePlan |
| GET | `platform/restaurants` | Super Admin |  |  | Platform\RestaurantController@index |
| POST | `platform/restaurants` | Super Admin |  |  | Platform\RestaurantController@store |
| POST | `platform/restaurants/bulk-tag` | Super Admin |  |  | Platform\RestaurantController@bulkTag |
| DELETE | `platform/restaurants/{restaurant}` | Super Admin |  |  | Platform\RestaurantController@destroy |
| GET | `platform/restaurants/{restaurant}` | Super Admin |  |  | Platform\RestaurantController@show |
| PATCH | `platform/restaurants/{restaurant}` | Super Admin |  |  | Platform\RestaurantController@update |
| POST | `platform/restaurants/{restaurant}/approve` | Super Admin |  |  | Platform\RestaurantController@approve |
| PUT | `platform/restaurants/{restaurant}/features` | Super Admin |  |  | Platform\RestaurantController@features |
| PUT | `platform/restaurants/{restaurant}/follow-up` | Super Admin |  |  | Platform\CrmController@followUp |
| POST | `platform/restaurants/{restaurant}/impersonate` | Super Admin |  |  | Platform\RestaurantController@impersonate |
| POST | `platform/restaurants/{restaurant}/impersonate-token` | Super Admin, app token |  |  | Platform\RestaurantController@impersonateToken |
| GET | `platform/restaurants/{restaurant}/notes` | Super Admin |  |  | Platform\CrmController@notes |
| POST | `platform/restaurants/{restaurant}/notes` | Super Admin |  |  | Platform\CrmController@storeNote |
| POST | `platform/restaurants/{restaurant}/payments` | Super Admin |  |  | Platform\PaymentController@store |
| POST | `platform/restaurants/{restaurant}/reject` | Super Admin |  |  | Platform\RestaurantController@reject |
| POST | `platform/restaurants/{restaurant}/resend-welcome` | Super Admin |  | 6 a minute | Platform\RestaurantController@resendWelcome |
| POST | `platform/restaurants/{restaurant}/restore` | Super Admin |  |  | Platform\RestaurantController@restore |
| POST | `platform/restaurants/{restaurant}/suspend` | Super Admin |  |  | Platform\RestaurantController@suspend |
| PUT | `platform/restaurants/{restaurant}/tags` | Super Admin |  |  | Platform\CrmController@setTags |
| POST | `platform/restaurants/{restaurant}/unsuspend` | Super Admin |  |  | Platform\RestaurantController@unsuspend |
| GET | `platform/search` | Super Admin |  | 120 a minute | Platform\SearchController |
| GET | `platform/settings` | Super Admin |  |  | Platform\PlatformController@settings |
| PUT | `platform/settings` | Super Admin |  |  | Platform\PlatformController@saveSettings |
| GET | `platform/settings/features` | Super Admin |  |  | Platform\FeatureSwitchController@index |
| PUT | `platform/settings/features` | Super Admin |  |  | Platform\FeatureSwitchController@update |
| POST | `platform/settings/legal/{page}/reset` | Super Admin |  |  | Platform\PlatformController@resetLegal |
| DELETE | `platform/settings/logo` | Super Admin |  |  | Platform\PlatformController@deleteLogo |
| POST | `platform/settings/logo` | Super Admin |  |  | Platform\PlatformController@saveLogo |
| DELETE | `platform/settings/mail` | Super Admin |  |  | Platform\PlatformController@clearMailSettings |
| GET | `platform/settings/mail` | Super Admin |  |  | Platform\PlatformController@mailSettings |
| PUT | `platform/settings/mail` | Super Admin |  |  | Platform\PlatformController@saveMailSettings |
| POST | `platform/settings/mail/test` | Super Admin |  | 5 a minute | Platform\PlatformController@testMail |
| DELETE | `platform/settings/share-image` | Super Admin |  |  | Platform\PlatformController@deleteShareImage |
| POST | `platform/settings/share-image` | Super Admin |  |  | Platform\PlatformController@saveShareImage |
| GET | `platform/setup` | Super Admin |  |  | Platform\SetupController@show |
| PUT | `platform/setup` | Super Admin |  |  | Platform\SetupController@update |
| POST | `platform/setup/demo/delete` | Super Admin |  | 3 a minute | Platform\SetupController@deleteDemo |
| GET | `platform/tags` | Super Admin |  |  | Platform\CrmController@tags |
| POST | `platform/tags` | Super Admin |  |  | Platform\CrmController@storeTag |
| DELETE | `platform/tags/{tag}` | Super Admin |  |  | Platform\CrmController@destroyTag |
| PATCH | `platform/tags/{tag}` | Super Admin |  |  | Platform\CrmController@updateTag |
| GET | `platform/themes` | Super Admin |  |  | Platform\PlatformController@themes |
| POST | `platform/themes` | Super Admin |  |  | Platform\PlatformController@saveTheme |
| PATCH | `platform/themes/{theme}` | Super Admin |  |  | Platform\PlatformController@saveTheme |
| POST | `platform/widgets/email/{tenant}` | Super Admin |  | 20 a minute | Platform\DashboardWidgetController@email |
| GET | `platform/widgets/expiring` | Super Admin |  |  | Platform\DashboardWidgetController@expiring |
| GET | `platform/widgets/follow-ups` | Super Admin |  |  | Platform\CrmController@due |
| GET | `platform/widgets/health` | Super Admin |  |  | Platform\DashboardWidgetController@health |
| GET | `platform/widgets/inactive` | Super Admin |  |  | Platform\DashboardWidgetController@inactive |
| GET | `platform/widgets/plan-mix` | Super Admin |  |  | Platform\DashboardWidgetController@planMix |
| GET | `platform/widgets/signups` | Super Admin |  |  | Platform\DashboardWidgetController@signups |

## Guest menu (public)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `beacon` | anyone |  | 120 a minute | Api\BeaconController |

## Other (`help`)

| Method | Path | Who | Feature | Limit | Handler |
|---|---|---|---|---|---|
| POST | `help/feedback` | signed in |  | 30 a minute | Api\HelpController@feedback |
| GET | `help/public` | anyone |  | 30 a minute | Api\HelpController@public |
