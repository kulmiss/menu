# Menu Admin — PLAN

Native Flutter app for **restaurant admins** (Owner, Manager, Staff) and the **Super Admin** of the Menu platform (`kulmiss/menu`). Everything an admin does in the web admin, without a browser. Android first (minSdk 26, targetSdk 36), iOS from the same code later.

Status: **approved by default** (owner asked to work non-stop, 2026-11-14). Decisions: [QUESTIONS.md](QUESTIONS.md). Progress: [handover.md](handover.md). Step 1 findings: [docs/step1-safety-check.md](docs/step1-safety-check.md).

## 1. Identity (one place: `lib/app/app_identity.dart` + `android/app/build.gradle.kts` `appName`)
- Name "Menu Admin" (placeholder). Android `com.kulmis.menu_admin`, iOS `com.kulmis.menuadmin`.
- Version `pubspec.yaml` `version:` (versionName+versionCode).

## 2. Architecture

```
lib/
  app/            app.dart (MaterialApp.router), app_identity.dart, router.dart, theme/
  core/
    api/          api_client.dart (dio), interceptors (auth, idempotency, retry, errors), api_error.dart
    auth/         session (token in secure storage), auth_repository, two-factor
    cache/        drift db (cache_entries), swr.dart (stale-while-revalidate stream)
    platform/     firebase (guarded), push, analytics, crash, app_lock, in-app update/review, links
    permissions/  role + features from /me → Can.* helpers, Upgrade badge
    widgets/      skeleton, empty/error states, adaptive scaffold, two-pane, section cards, money/price fields
    l10n/         ARB → AppLocalizations
  features/
    restaurant/   dashboard, menus, categories, dishes, all_dishes, sold_out, branches, qr, appearance, info_hours,
                  promotions, price_periods, insights, team, billing
    platform/     dashboard, restaurants, restaurant_detail, approvals, plans, payments, invoices, messages,
                  backups, search, announcements, settings, activity
    account/      profile, password, two_factor, devices, notifications_settings, inbox, app_settings (lock, analytics)
```

- **Layers**: Widget → Riverpod `Notifier`/`AsyncNotifier` → Repository (feature) → `ApiClient` + `CacheStore`. Repositories return `Stream<Cached<T>>` for reads (cache first, then network) and `Future<T>` for writes.
- **Riverpod 3** (hand-written providers, no provider codegen), **go_router** (`ShellRoute` per mode, deep links), **dio** with interceptors:
  1. `AuthInterceptor` — `Authorization: Bearer <token>`, `Accept: application/json`, `X-App-Version`; 401 → sign out (token revoked/expired) and route to sign-in.
  2. `IdempotencyInterceptor` — every POST/PUT/PATCH/DELETE gets `Idempotency-Key` (UUID v4, 32 chars) unless the caller gave one; the **same key is reused on retries** of that request.
  3. `RetryInterceptor` — retries GETs (and mutations with their same key) on timeouts / 502/503/504 / connection reset, 3 tries, 0.4/1.2/3 s backoff; honours `Retry-After` on 429; 409 `idempotency.in_progress` waits and retries.
  4. `ErrorInterceptor` — maps to `ApiError` (`validation` with field errors from Laravel 422, `paymentRequired` 402 with message + upgrade link, `forbidden` 403 + code (`feature.*`, `impersonation.blocked`, `platform.two_factor_required`), `notFound`, `rateLimited`, `offline`, `server`).
- **Models**: freezed + json_serializable, tolerant (`@JsonKey(defaultValue: …)`), money in minor units as the API does.
- **Tokens**: `flutter_secure_storage` (Android Keystore, `encryptedSharedPreferences` off since v10 → Keystore-backed default).

## 3. Packages (resolved 2026-11-14 with Flutter 3.47.9; exact versions pinned in `pubspec.lock`)

| Package | Version | Licence | Use |
|---|---|---|---|
| flutter_riverpod | 3.5.0 | MIT | state |
| go_router | 18.1.4 | BSD-3 | navigation, deep links |
| dio | 5.11.1 | MIT | HTTP |
| freezed_annotation / freezed | 3.1.0 / 4.0.3 | MIT | models |
| json_annotation / json_serializable | 4.12.0 / 6.15.2 | BSD-3 | JSON |
| drift / drift_flutter / drift_dev | 2.35.4 / 0.3.1 / 2.35.3 | MIT | SQLite cache |
| flutter_secure_storage | 11.2.0 | BSD-3 | token, PIN hash |
| cached_network_image | 4.0.7 | MIT | photo cache |
| fl_chart | 1.3.1 | MIT | charts |
| webview_flutter | 4.14.1 | BSD-3 | live preview |
| image_picker | 1.2.4 | Apache-2.0/BSD | camera / photo picker |
| image_cropper | 13.0.0 | BSD-3 (uCrop Apache-2.0) | crop |
| flutter_image_compress | 2.5.1 | MIT | resize before upload |
| share_plus | 13.3.1 | BSD-3 | share PDF/PNG/CSV/links |
| open_filex | 5.0.0 | BSD-3 | open PDF in the system viewer |
| path_provider | 2.1.6 | BSD-3 | cache folder |
| local_auth | 3.0.2 | BSD-3 | biometric lock |
| firebase_core / messaging / crashlytics / analytics | 5.1.0 / 17.0.1 / 6.0.1 / 13.0.1 | BSD-3 | push, crashes, analytics |
| in_app_update | 5.0.0 | MIT | Play in-app update |
| in_app_review | 2.0.12 | MIT | review prompt |
| url_launcher | 6.3.3 | BSD-3 | external links |
| package_info_plus | 10.2.2 | BSD-3 | version for app-config |
| connectivity_plus | 7.3.2 | BSD-3 | offline banner |
| intl, flutter_localizations | 0.20.3 / SDK | BSD-3 | i18n, dates, money |
| flutter_launcher_icons / flutter_native_splash | 0.14.4 / 2.4.8 | MIT | icon, splash (dev) |
| mocktail, build_runner | 1.0.5 / 2.17.1 | MIT / BSD-3 | tests, codegen (dev) |
| Inter font | 4.1 | OFL-1.1 | UI font (bundled) |

No paid services. Firebase (Spark plan: FCM, Crashlytics, Analytics are free).

## 4. API contract (all `/api/v1`, JSON, `Accept: application/json`, Bearer token)

### 4.1 Existing — used as is
Restaurant: `t/{id}/…` menus, categories, items (+ DataTable `?table=1&page&per_page&q&sort&filters`), addon-groups, media, tags, branches (+ tables, overrides, table-cards.pdf), qr-codes (+ .svg/.png/.pdf), print-materials (+ .pdf), announcements, insights (+ export.csv, report.pdf, settings), dashboard, widgets/*, price-periods, promotions, profile, hours, closures, appearance (+ preview), billing (+ invoices pdf), menu-pdf, members, invitations, activity, account/slug.
Super Admin: `platform/…` dashboard, widgets/*, insights/*, restaurants (+ every action), tags, notes, follow-up, payments, invoices, plans, themes, announcements, activity, settings (+ mail, mail/test, logo, share-image, legal reset), campaigns (count, preview, test, store, retry, rate), search, backups (index, store, settings).

### 4.2 New (backend PRs, `kulmiss/menu`)
| Method + path | Body / result |
|---|---|
| `POST mobile/auth/login` | `{email, password, device_name, platform}` → `{token, user}` or `{two_factor: true, challenge}` (5 min, single use) |
| `POST mobile/auth/two-factor` | `{challenge, code \| recovery_code, device_name, platform}` → `{token, user}` |
| `POST mobile/auth/logout` · `POST mobile/auth/logout-all` | revoke this / every token (and their push tokens) |
| `GET mobile/devices` · `DELETE mobile/devices/{id}` | my signed-in devices (name, platform, app version, last used, current) |
| `PUT mobile/account/profile` · `PUT mobile/account/password` | same validation as Fortify actions; password change revokes other tokens |
| `POST mobile/account/two-factor` (`{password}`) → secret + QR SVG + recovery codes; `POST …/two-factor/confirm` `{code}`; `DELETE …/two-factor` `{password}`; `POST …/two-factor/recovery-codes` `{password}` | 2FA for tokens |
| `POST mobile/impersonate/{restaurant}` (Super Admin) → owner token 2 h · `POST mobile/impersonation/stop` | "log in as" |
| `POST mobile/push-tokens` `{token, platform}` · `DELETE mobile/push-tokens` `{token}` | FCM registration (tied to the current access token) |
| `GET mobile/notification-settings` · `PUT …` `{type: bool}` | per-user switches (types depend on role) |
| `GET mobile/notifications?cursor` · `POST mobile/notifications/read` `{ids \| all}` | inbox (90 days) |
| `GET mobile/config` (public) | `{min_version, latest_version, flags:{…}, links:{privacy, terms, support_whatsapp, support_email, delete_account, forgot_password, signup}}` |

Existing `POST t/{id}/media` takes the phone photo (multipart `file`, ≤ 8 MB, `purpose=banner` for banners) — the app compresses first.

### 4.3 Push events (each switchable)
| Type | Who | Trigger | Deep link |
|---|---|---|---|
| `plan_expiring` | Owner | 7, 3, 1 days before `paid_until` (daily 09:00 tenant time) | `/r/{t}/billing` |
| `plan_expired` | Owner | day after expiry | `/r/{t}/billing` |
| `monthly_report` | Owner, Manager | `insights:monthly` sends the report | `/r/{t}/insights` |
| `invitation_accepted` | Owner | member accepts | `/r/{t}/team` |
| `platform_announcement` | all restaurant users | Super Admin publishes an announcement | `/r/{t}/news/{id}` |
| `signup_new` | Super Admin | new sign-up verified | `/p/restaurants/{id}` |
| `signup_pending` | Super Admin | sign-up waits for approval | `/p/approvals` |
| `payment_recorded` | Super Admin | payment saved | `/p/restaurants/{id}/payments` |
| `backup_failed` | Super Admin | backup job fails | `/p/backups` |
| `health_warning` | Super Admin | cron heartbeat older than 10 min, queue backlog, disk < 10 % | `/p/dashboard` |
| `followups_due` | Super Admin | daily 08:00, follow-ups due today | `/p/restaurants?follow_up=due` |

All go through a Laravel notification channel `fcm` → queued job → existing cron `queue:work --stop-when-empty`; invalid tokens (`UNREGISTERED`) are deleted. Each push also writes an inbox row.

## 5. Screens

> Redesign 2026-11 (docs/redesign.md): Home with today's numbers and quick actions, bottom bar Home · Menu · Orders · More, minimal Super Admin. The lists below are updated.

**Restaurant mode** (bottom bar: Home · Menu · Orders (with "My order") · More; rail on tablets)
- Home: today (views, QR scans vs yesterday, sold-out count), quick actions (change a price, hide/show a dish, add dish with photo, share menu link/QR, mark sold out, promotions), setup checklist, 7-day chart, missing photos, plan & usage, platform news, Manage shortcuts; pull to refresh.
- Orders: "My order" lists shown, average list, most-added dishes, 7/30 days.
- Menu: menus (schedules, duplicate, branches) → categories → dishes; drag to reorder (long-press handle); quick sold-out switches; FAB add.
- Dish editor: name, description, price or sizes, add-on groups, tags, allergens, calories, prep time, up to 5 photos (camera/gallery, crop, reorder, main), show photo, sold out, duplicate, delete.
- All dishes: DataTable-like list (search debounced 300 ms, filters: menu, category, sold out, no photo, tag), multi-select bulk (sold out on/off, hide, move, bulk price %/amount), infinite scroll.
- Sold out: big list, one-tap switches, search, per-branch.
- Branches: list, edit, tables, prices & availability (per size), hidden / sold out per branch.
- QR & print: codes (restaurant/branch/table), preview, share/save PNG/SVG/PDF, table cards PDF, print materials PDF, social images.
- Appearance: styles A–D cards, brand colour (+ contrast hint), fonts, banner, logo, show/hide switches, share/PDF/My-order switches; **live WebView preview** (debounced 600 ms), sticky Save/Undo.
- Info & hours: profile/contact/socials/Wi-Fi, week editor with breaks, holidays/closures.
- Promotions (specials, announcements) + Happy hour (price periods, preview).
- Insights: totals, fl_chart bars, top dishes, scans per code, 7/30/90 days, branch, export CSV/PDF (share), monthly report switch.
- Team: members, roles, invite, remove, pending invitations.
- Billing: plan, paid until, usage, invoices (open/share PDF), upgrade contact.
- More: All dishes, Sold out, Insights, Branches, QR codes, Print, Appearance, Info & hours, Promotions, Happy hour, Team, Activity, Billing, Notifications, Profile, Settings, Switch restaurant.

**Super Admin** (one screen, no bar — redesign 2026-11)
- Overview (restaurants, paying, trial, sign-ups and menu views over 30 days), emergency switches (app maintenance, self sign-up, push notifications, Super Admin app sign-in), "Open Super Admin on the web" for everything else.

**Both**: sign-in, 2FA, Profile (name/e-mail, password, two-factor, devices), Notifications (switches + inbox), App settings (theme Phone/Light/Dark, lock, auto-lock time, analytics switch, about, licences, privacy/terms/support, delete account link).

## 6. Offline & caching
- `cache_entries(key, scope, json, etag, fetched_at)`; scope = `userId:tenantId`; cleared on sign-out.
- Read: emit cache immediately (if any) → fetch → write → emit; screen shows a tiny "Updated 2 min ago"/"Offline" line, never a spinner when cache exists; skeletons only on the very first load.
- Pull-to-refresh forces a fetch. Background refresh when the screen becomes visible and the cache is older than 30 s.
- Writes: need a connection (offline banner + disabled save). Optimistic: sold-out, branch availability, price edit, reorder, show photo — update cache + UI, roll back on error with a snackbar.
- Images: `cached_network_image` with the server's 320/640 px sizes; disk cache 200 MB max.
- Lists: server pagination (DataTable mode, `per_page=50`), `ListView.builder` with fixed `itemExtent` where possible.

## 7. Platform features
- Push: FCM token registered after sign-in (and on refresh); foreground: in-app banner; tap: `go_router` to the payload path (restaurant switch if needed); inbox screen.
- Links: only `https`/`http` open in the browser (`url_launcher` external), `menuadmin://` handled in-app, everything else refused.
- App lock: biometric/PIN, auto-lock after inactivity (lifecycle observer), blur in app switcher (`FLAG_SECURE` off by default; on when lock is on).
- Crashlytics: `FlutterError.onError` + `PlatformDispatcher.onError`; non-fatal API 5xx. Analytics: screen views from the router observer, ~15 feature events, off switch.
- In-app update: flexible when a newer version exists; immediate when below `min_version`; app also blocks with an "Update required" screen when below `min_version` (non-Play installs).
- In-app review: after 5 sessions on 3 different days and ≥ 3 successful saves, never after an error, at most once per 120 days.

## 8. Design
- The web admin's shadcn tokens in `lib/app/theme.dart` (`AppTokens`, light + dark), primary = web slate everywhere, radius 10, 1 px borders, flat cards, Inter, Lucide icons; theme follows the phone with a switch in Settings. Details: docs/redesign.md.
- 48 dp min tap targets, contrast ≥ 4.5:1 (checked in tests for both themes), text scaling up to 130 %, semantics labels on every icon button, keyboard shortcuts on tablets (Ctrl+K search, Ctrl+S save, Esc close).
- Motion: 150–250 ms ease-out; `MediaQuery.disableAnimations` → no motion.
- Skeletons, empty states with one action, error states with Retry.
- Design preview: Claude Design artifact (link in handover.md).

## 9. Icon
Three original concepts (SVG, `assets/icon/concepts/`):
- **A Plate** — a plate seen from above with a fork-and-knife cut-out ring. Friendly, but reads as a food app for guests.
- **B Menu card + QR corner** ✅ — a rounded menu card with three text lines; its bottom-right corner is a QR finder pattern. Teal card on a deep slate background; monochrome version is the card outline + finder.
- **C Cloche + gear** — a serving dome with a gear notch. "Admin" but busy at 24 px.
Chosen **B** (M22): two shapes, high contrast, recognisable at 24 px, unique.
Outputs: adaptive foreground/background/monochrome, legacy mipmaps, iOS AppIcon set, Play 512×512, splash (light + dark, Android 12 splash API).

## 10. Performance budget
- Cold start → usable screen < 1.5 s on a mid-range phone: no network before first frame (token + cached `/me` from disk), Firebase initialised after first frame, deferred imports for platform mode and heavy screens, splash removed at first frame.
- 60 fps lists: builders, `const` widgets, image sizes matched to the device pixel ratio, no shadows on list rows.
- Release: `--split-per-abi`, R8 minify + shrink resources, `--obfuscate --split-debug-info=build/symbols`, Crashlytics symbols upload (`firebase crashlytics:symbols:upload`). Target < 20 MB per ABI.

## 11. Testing
- Unit: api interceptors (idempotency key reuse on retry, error mapping, 401 sign-out), auth repository (login, 2FA, logout), cache SWR, permissions (`Can` for Owner/Manager/Staff/Super Admin + features), deep-link parsing, link safety.
- Widget: sign-in, 2FA, dashboard, menu editor, dish editor, sold-out, restaurants list, record payment.
- Golden: sign-in, sold-out (phone + tablet), Home, Menu, Orders, More, Settings, Super Admin, components — light/dark. Fonts loaded in tests (Inter) for stable goldens; run on Linux only.
- Integration (`integration_test/`): login + 2FA, edit dish, sold-out toggle, record payment — against `FakeApi`; same files on a real phone.
- Backend: Pest feature tests for every new endpoint (tokens, expiry, 2FA, devices, impersonation, push register, settings, inbox, config, FCM sender with fake HTTP, events).

## 12. Build order (PRs)
Backend (`kulmiss/menu`): B1 mobile auth + devices + account → B2 impersonation tokens → B3 push (tokens, settings, inbox, sender, events) → B4 app-config + privacy text + docs.
App (`kulmiss/menu-admin-app`): A0 plan → A1 setup/CI/theme/core → A2 auth → A3 restaurant dashboard → A4 menu editor + dishes + photos → A5 sold-out + reorder + all dishes → A6 branches → A7 QR + print → A8 appearance → A9 info/hours → A10 promotions + happy hour → A11 insights → A12 team → A13 billing → A14 profile/settings/devices → A15 platform dashboard → A16 restaurants + detail + payments → A17 approvals/plans/messages/backups/search/announcements/settings/activity → A18 push + deep links + inbox → A19 app lock → A20 Crashlytics/Analytics → A21 in-app update/review → A22 icon + splash → A23 polish + performance.
