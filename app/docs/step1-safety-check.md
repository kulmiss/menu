# Step 1 — Safety check (2026-11-14)

Read: `kulmiss/menu` @ `develop` 764925f (after #75), README, PLAN.md, QUESTIONS.md (#1–#186), handover.md, docs/, `backend/routes/*.php`, middleware, Fortify/Sanctum config, controllers.

## 1. Can Flutter run in Claude's workspace?

**Yes.** Flutter 3.47.9 stable (Dart 3.12) installs from `storage.googleapis.com`; `pub.dev`, `maven.google.com`, `dl.google.com` (Android SDK) and Gradle are reachable. `flutter create`, `pub add` and `flutter test` work. The backend runs too (PHP 8.3, MariaDB, Pest: **450 tests pass**).
Limits: no phone/emulator (no KVM) → integration tests run as `flutter test` against a fake API here, and on the real phone on the laptop. Larastan can't be installed here (phpstan's package is a 1 GB git mirror); CI runs it.

Version: the laptop has 3.47; the newest stable is 3.50.0 (2 days old). **Pinned 3.47.9** in CI and `.flutter-version`.

## 2. What the backend has (all under `/api/v1`, JSON, Idempotency-Key on every POST/PUT/PATCH/DELETE)

| Area | Endpoints that exist | Works with a Bearer token? |
|---|---|---|
| Sign-in | Fortify `api/v1/auth/login`, `two-factor-challenge`, `logout` (web middleware: cookie session + CSRF) | **No** — session only |
| Profile | Fortify `user/profile-information`, `user/password`, 2FA enable/confirm/disable/recovery codes (session + "confirm password") | **No** |
| Me | `GET me` (tenants + role + features, impersonation) | Yes once `User` has `HasApiTokens` |
| Restaurant `t/{tenant}/…` (now `restaurant/{tenant}/…`, M51) | menus (+tree, schedules, duplicate, reorder), categories, items (+bulk, bulk-price, sold-out, price, duplicate, reorder, DataTable mode), addon groups (+option sold out), media upload (8 MB), tags, import/export, branches (+tables, overrides incl. per size, table cards PDF), QR codes (svg/png/pdf), print materials (+PDF), announcements (+read), insights (+CSV/PDF, report setting), dashboard + widgets, price periods, promotions, profile/hours/closures, appearance (+HTML preview), billing (+invoice PDF), menu PDF, team, invitations, activity, slug | Yes (Sanctum falls back to the token when no cookie) |
| Super Admin `platform/…` | dashboard, widgets, insights, restaurants (+DataTable, show, update, delete/restore, suspend, approve/reject, resend welcome, features, payments, impersonate, tags, notes, follow-up), payments (+CSV), invoices (+PDF, resend, void, CSV), plans, themes, announcements, activity, settings (+legal reset, logo, share image, SMTP + test), campaigns (count, preview, test, send, retry, rate), search, backups (list, run, settings, signed link, delete) | Yes, **except "log in as"** (session only: `abort_unless($request->hasSession())`) |

Permissions are server-side Gates (`menu.manage`, `restaurant.manage`, `team.manage`, `account.manage`, `menu.toggle_sold_out`, `menu.edit_prices`) and `feature:` middleware (plan switches → 403 code `feature.*`) and 402 for limits/read-only. The app mirrors them from `/me` (role + features) only to hide buttons; the server stays the judge.

## 3. Missing for mobile (backend PRs)

1. **Token sign-in** (`POST mobile/auth/login`, `…/two-factor`, `logout`, `logout-all`), named token per device, expiry policy, `HasApiTokens`, rate limits keyed by e-mail+IP and by challenge.
2. **Account endpoints for tokens**: password change, profile, 2FA enable/confirm/disable/recovery codes (password in the body instead of the session "confirm password").
3. **Devices**: list my tokens/devices, revoke one.
4. **"Log in as" with tokens**: short-lived token for the owner carrying `impersonator_id`; same 2-hour limit and blocks as the web (`GuardImpersonation` must also look at the token); "back to platform" = revoke it.
5. **Push**: `device_tokens` table, register/unregister, per-user switches per event type, FCM HTTP v1 sender (queued, cron-driven), events for both roles, deep link in every payload; a notification **inbox** endpoint (`notifications` table).
6. **App config**: `GET mobile/config` (min version, latest version, feature flags, privacy/terms/support WhatsApp links) — public, cached.
7. **Privacy Policy default**: lines for push tokens/app, using the existing "replace only if unedited" migration pattern.
8. Media upload: the existing `POST media` already takes multipart up to 8 MB and runs the photo pipeline → **no new endpoint**; the app compresses to ≤ 2 MB JPEG first. Test added for a Bearer upload.

## 4. Risks and how they are handled

| Risk | Handling |
|---|---|
| Stolen phone = admin access | Tokens hashed (Sanctum), per device, sliding expiry (restaurant 60 days idle, Super Admin 14 days idle, 1 year max), revoke from Devices, logout-all, optional biometric/PIN lock, tokens only in Android Keystore (`flutter_secure_storage`). |
| Super Admin 2FA bypass | Token login refuses a Super Admin without confirmed 2FA for platform routes (same rule as web); the 2FA step is mandatory when 2FA is on; challenge is single-use, 5 minutes, 5 tries. |
| Brute force | `throttle` on login (5/min per e-mail+IP, as web), 2FA (5/min per challenge), plus 30/min per IP. |
| Idempotency replays across users | Scope is already per user; the app makes one key per user action and reuses it on retry. |
| "Log in as" abuse | Same rules as web (2 h, blocked URIs, not recorded — owner decision #170). Token cannot reach `platform/*`. |
| CSRF / cookies | Bearer requests carry no cookies; mobile routes skip the `web` group. Web admin unchanged. |
| Web admin / customer menu regressions | Backend PRs add routes, a trait and middleware checks only; full Pest suite + existing CI speed jobs must stay green; no frontend or Blade changes. |
| Secrets | `google-services.json`, keystore, FCM service account: never committed (`.gitignore`, CI checks). FCM credentials path in `.env`. |
| Play policies | No in-app sign-up (admins only, accounts are made on the web) → account deletion: link to the web request; photo picker (no media permission), camera permission only when used; Data safety lists e-mail, name, device push token, crash logs, optional analytics; reviewer test account needed (App access); personal developer account → 12 testers × 14 days closed test before production. |
| CI minutes | `kulmiss/menu` is **public** → Actions minutes are free there. `kulmiss/menu-admin-app` is **private** → minutes count. App CI: one Linux job, cached Flutter + pub, `paths` filters, cancel-in-progress, no APK builds in CI (release builds on the laptop), goldens on Linux only. ≈ 4–6 min per PR. |
| App size < 20 MB/ABI | No pdf viewer engine (open PDFs in the system viewer/share), drift + sqlite (~1.5 MB), Inter subset, split-per-ABI, R8. |

## 5. Questions — decided by Claude (owner said: don't wait; all changeable)

See `QUESTIONS.md` #M1–#M30.
