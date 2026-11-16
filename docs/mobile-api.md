# Mobile app API (Menu Admin app)

The Flutter app [`kulmiss/menu-admin-app`](https://github.com/kulmiss/menu-admin-app) uses the same `/api/v1` JSON API as the web admin. It signs in with a **Bearer token** instead of the cookie session.

- The full list of endpoints is generated from the routes: [api-reference.md](api-reference.md).
- This page has a request and answer example for every endpoint that only the app uses, and for the Super Admin screens that control the app.
- `tests/Feature/Docs/ApiDocsTest.php` fails when one of those routes has no section here, or a section has no example.

## Rules for every request

| Header | When | Example |
|---|---|---|
| `Authorization: Bearer <token>` | after sign-in | `Bearer 1|YV07E0Wx…` |
| `X-App-Version: x.y.z` | **always** (the app adds it) | `1.2.0` |
| `Idempotency-Key: <uuid>` | every create/change request, except sign-in | `6f1c…` (reuse it when you retry) |
| `Accept-Language` | optional | `en` |

Error answers:

| Status | Body | Meaning |
|---|---|---|
| `401` | `{message}` | token missing, expired or idle too long → sign in again |
| `402` | `{message, code: "features.<key>", upgrade: {whatsapp, email}}` | the feature is off (plan, restaurant or Super Admin switch) |
| `403` | `{message, code}` | not allowed (role, `impersonation.blocked`, `mobile.app_off`) |
| `409` | `{message, code}` | conflict, e.g. `idempotency.not_replayable` |
| `410` | `{message, code: "mobile.challenge_expired"}` | two-factor challenge gone |
| `422` | `{message, errors: {field: [..]}}` | validation |
| `426` | `{message, code: "mobile.update_required", min_version, web}` | app older than the minimum version |
| `503` | `{message, code: "mobile.maintenance", web}` | maintenance mode |

### App gate (QUESTIONS #193)

`MobileAppGate` runs on every API request **that has `X-App-Version`** (so the web admin is never affected):

1. Version below `versions.min` → `426 mobile.update_required`.
2. Maintenance on and the person is not a Super Admin → `503 mobile.maintenance` (message from the Super Admin).
3. App sign-in off for this kind of user (restaurant team / Super Admin) → `403 mobile.app_off`.

Always allowed: `GET mobile/config`, sign-out, removing the push address. Sign-in and two-factor are checked after the password, so a wrong password still gets `422`. The app shows the same full-screen message for each code (app QUESTIONS M36) and does not retry a `503 mobile.maintenance`.

```json
{"message": "Back at 10:00", "code": "mobile.maintenance", "web": "https://menu.example/admin"}
```

```json
{"message": "Please update the app to keep using it.", "code": "mobile.update_required", "min_version": "1.3.0", "web": "https://menu.example/admin"}
```

```json
{"message": "The app is not available for your account right now. Please use the website.", "code": "mobile.app_off", "web": "https://menu.example/admin"}
```

### Feature off (QUESTIONS #191, #192)

A feature can be off in three places: **Settings → Features** (for everyone), the **plan**, or the **restaurant**. The server combines them: global OFF wins, then the restaurant's own switch, then the plan. The answer is the same everywhere:

```json
{"message": "\"Sold out\" marks is not included in your plan. Upgrade your plan to use it.", "code": "features.sold_out", "upgrade": {"whatsapp": "https://wa.me/251911222333", "email": "hello@menu.example"}}
```

The combined switches are in `GET me` (`tenants[].features`, per restaurant) and the global ones in `GET mobile/config` (`features`). Feature keys: `branches`, `promotions`, `insights`, `import_export`, `remove_branding`, `pdf_menu`, `order_list`, `style_editorial`, `style_grid`, `style_bistro`, `style_minimal`, `price_periods`, `sold_out`, `share`, `print_materials`, and the platform-only `signup`. A missing key means **on** (an older server).

## Start-up settings

### `GET /api/v1/mobile/config`

Public. `Cache-Control: public, max-age=60`. The app reads it from the device first, then fetches it at start, when it comes back to the screen (at most every 30 s) and on "Try again". It never waits for it.

```http
GET /api/v1/mobile/config
X-App-Version: 1.2.0
```

```json
{
  "data": {
    "name": "Menu",
    "min_version": "1.0.0",
    "latest_version": "1.4.0",
    "flags": {"biometric_lock": true, "push": true, "analytics": true, "crash_reports": true},
    "versions": {"latest": "1.4.0", "min": "1.0.0", "recommended": "1.3.0", "notes": "Faster menu"},
    "sign_in": {"restaurant": true, "super_admin": true, "message": null},
    "maintenance": {"on": false, "message": null},
    "banner": {"text": "New: happy hour", "link": "/r/1/promotions", "audience": "restaurant", "ends_at": "2026-11-20T21:00:00+03:00"},
    "features": {"branches": true, "promotions": true, "insights": true, "import_export": true, "remove_branding": true, "pdf_menu": true, "order_list": true, "style_editorial": true, "style_grid": true, "style_bistro": true, "style_minimal": true, "price_periods": true, "sold_out": true, "share": true, "print_materials": true, "signup": true},
    "app_features": {"biometric_lock": true, "push": true, "analytics": true, "crash_reports": true},
    "links": {
      "privacy": "https://menu.example/privacy",
      "terms": "https://menu.example/terms",
      "forgot_password": "https://menu.example/auth/forgot-password",
      "signup": "https://menu.example/auth/sign-up",
      "web_admin": "https://menu.example/admin",
      "play_store": "https://play.google.com/store/apps/details?id=com.kulmiss.menuadmin",
      "app_store": null,
      "support_whatsapp": "https://wa.me/251911222333",
      "support_email": "hello@menu.example"
    }
  }
}
```

| Field | Meaning |
|---|---|
| `versions.min` | below it → "Update required" (and `426` from the server) |
| `versions.recommended` | below it → a "new version" line the person can close (once per version); `null` = never |
| `versions.notes` | release notes, shown in both |
| `sign_in.restaurant` / `.super_admin` | app sign-in allowed; `message` is shown when not |
| `maintenance` | `on` + `message`; Super Admins keep working |
| `banner` | `null` outside its dates. `audience`: `all`, `restaurant`, `super_admin` (the app filters). `link`: an app path (`/inbox`) or `https://…` |
| `features` | global switches (Settings → Features) |
| `app_features` | app-only switches: `biometric_lock`, `push`, `analytics`, `crash_reports` |
| `min_version`, `latest_version`, `flags` | kept for app versions before #193 |

## Sign-in (QUESTIONS #187)

Sign-in requests have **no** Idempotency-Key: they change nothing, and a stored answer would keep the token in the database. Tokens: one per device, stored hashed, `expires_at` = 365 days, and unused for 60 days (Super Admins: 14) → `401`. `mobile:prune-tokens` deletes old ones nightly (03:25).

### `POST /api/v1/mobile/auth/login`

5 tries a minute per e-mail + address, 30 per address.

```http
POST /api/v1/mobile/auth/login
X-App-Version: 1.2.0
Content-Type: application/json

{"email": "sara@cafe.example", "password": "••••••••", "device_name": "Pixel 8", "platform": "android", "app_version": "1.2.0"}
```

```json
{"token": "1|YV07E0WxL7BiE7yohLFrBm7Qf2j2fyHQRXwRLB3C8e667afc", "user": {"id": 1, "name": "Sara Bekele", "email": "sara@cafe.example", "is_super_admin": false}}
```

With two-factor on: `{"two_factor": true, "challenge": "c9a1…"}`. Wrong password: `422 {"errors": {"email": ["These credentials do not match our records."]}}`. App sign-in off or maintenance: the `403` / `503` above.

### `POST /api/v1/mobile/auth/two-factor`

```http
POST /api/v1/mobile/auth/two-factor
X-App-Version: 1.2.0

{"challenge": "c9a1…", "code": "123456"}
```

```json
{"token": "2|…", "user": {"id": 1, "name": "Sara Bekele", "email": "sara@cafe.example", "is_super_admin": false}}
```

`recovery_code` instead of `code` also works. Wrong code `422 errors.code`; challenge older than 5 minutes, used, or 5 wrong codes → `410 mobile.challenge_expired`.

### `POST /api/v1/mobile/auth/logout` · `POST /api/v1/mobile/auth/logout-all`

```http
POST /api/v1/mobile/auth/logout
Authorization: Bearer 1|…
Idempotency-Key: 0b6e…
```

`204 No Content`. `logout` deletes this token; `logout-all` deletes every app token of the person (the web session stays).

## Signed-in person

### `GET /api/v1/me`

Works with the web session and the token. The app reads roles, restaurants and feature switches from here.

```http
GET /api/v1/me
Authorization: Bearer 1|…
X-App-Version: 1.2.0
```

```json
{
  "data": {
    "id": 1, "name": "Sara Bekele", "email": "sara@cafe.example", "pending_email": null, "password_weak": false,
    "locale": "en", "email_verified": true, "news_subscribed": true, "two_factor_enabled": false, "two_factor_required": false,
    "is_super_admin": false, "impersonation": null,
    "tenants": [{
      "id": 1, "slug": "abebe-cafe", "name": "Abebe Café", "timezone": "Africa/Addis_Ababa", "default_locale": "en", "currency_code": "ETB",
      "subscription": {"state": "trial", "allows_writes": true, "shows_banner": false, "stage_ends_at": "2026-11-30T17:10:41+00:00"},
      "features": {"branches": true, "promotions": true, "insights": true, "import_export": true, "remove_branding": false, "pdf_menu": true, "order_list": true, "style_editorial": true, "style_grid": true, "style_bistro": true, "style_minimal": true, "price_periods": true, "sold_out": false, "share": true, "print_materials": true},
      "upgrade": {"whatsapp": "https://wa.me/251911222333", "email": "hello@menu.example"},
      "membership": {"id": 1, "role": "owner", "can_edit_prices": true}
    }]
  }
}
```

`tenants[].features` is already combined (global + plan + restaurant). When "Sold out" is off, `is_sold_out` in dish answers is always `false` (marks are kept and come back when it is on again).

## Account and devices (token only)

The web session gets `400 {"message": "This needs the mobile app sign-in.", "code": "mobile.token_required"}`.

### `GET /api/v1/mobile/devices`

```http
GET /api/v1/mobile/devices
Authorization: Bearer 1|…
```

```json
{"data": [{"id": 1, "name": "Pixel 8", "platform": "android", "app_version": "1.2.0", "last_used_at": "2026-11-16T17:12:01+00:00", "created_at": "2026-11-16T17:12:01+00:00", "current": true}]}
```

### `DELETE /api/v1/mobile/devices/{device}`

Only your own devices.

```http
DELETE /api/v1/mobile/devices/4
Authorization: Bearer 1|…
Idempotency-Key: 4c2d…
```

`204 No Content`.

### `PUT /api/v1/mobile/account/profile`

A new e-mail must be confirmed again (`pending_email`).

```http
PUT /api/v1/mobile/account/profile
Authorization: Bearer 1|…
Idempotency-Key: 91aa…

{"name": "Sara Bekele", "email": "sara@cafe.example", "locale": "en"}
```

```json
{"data": {"id": 1, "name": "Sara Bekele", "email": "sara@cafe.example", "pending_email": null, "email_verified": true}}
```

### `PUT /api/v1/mobile/account/password`

Signs out your other devices.

```http
PUT /api/v1/mobile/account/password
Authorization: Bearer 1|…
Idempotency-Key: 77b0…

{"current_password": "••••••••", "password": "••••••••••", "password_confirmation": "••••••••••"}
```

`204 No Content`. Wrong current password: `422 errors.current_password`.

### `POST /api/v1/mobile/account/two-factor`

Starts setup with a new secret.

```http
POST /api/v1/mobile/account/two-factor
Authorization: Bearer 1|…
Idempotency-Key: 1f3e…

{"password": "••••••••"}
```

`204 No Content`.

### `GET /api/v1/mobile/account/two-factor`

While setup is waiting (else `409`). `Cache-Control: no-store`.

```http
GET /api/v1/mobile/account/two-factor
Authorization: Bearer 1|…
```

```json
{"data": {"svg": "<svg …/>", "url": "otpauth://totp/Menu:sara%40cafe.example?secret=JBSW…&issuer=Menu", "secret": "JBSWY3DPEHPK3PXP"}}
```

### `POST /api/v1/mobile/account/two-factor/confirm`

```http
POST /api/v1/mobile/account/two-factor/confirm
Authorization: Bearer 1|…
Idempotency-Key: 2a9c…

{"code": "123456"}
```

```json
{"data": {"recovery_codes": ["Xk2p9-Qm4Lw", "…"]}}
```

The idempotency table keeps only the key and status of answers with secrets: a replay gets `409 idempotency.not_replayable`.

### `DELETE /api/v1/mobile/account/two-factor`

```http
DELETE /api/v1/mobile/account/two-factor
Authorization: Bearer 1|…
Idempotency-Key: 5d61…

{"password": "••••••••"}
```

`204 No Content`.

### `POST /api/v1/mobile/account/two-factor/recovery-codes`

```http
POST /api/v1/mobile/account/two-factor/recovery-codes
Authorization: Bearer 1|…
Idempotency-Key: 8e07…

{"password": "••••••••"}
```

```json
{"data": {"recovery_codes": ["Pq7d2-Hn3Vx", "…"]}}
```

## Push notifications and inbox (QUESTIONS #189)

### `POST /api/v1/mobile/push-tokens`

Registers this phone's FCM token, linked to this sign-in (signing out removes it). A token already known moves to the new person. The app does not register when `app_features.push` is off.

```http
POST /api/v1/mobile/push-tokens
Authorization: Bearer 1|…
Idempotency-Key: a1b2…

{"token": "fcm-token-from-firebase…", "platform": "android"}
```

`204 No Content`.

### `DELETE /api/v1/mobile/push-tokens`

```http
DELETE /api/v1/mobile/push-tokens
Authorization: Bearer 1|…
Idempotency-Key: c3d4…

{"token": "fcm-token-from-firebase…"}
```

`204 No Content`.

### `GET /api/v1/mobile/notification-settings`

```http
GET /api/v1/mobile/notification-settings
Authorization: Bearer 1|…
```

```json
{"data": [{"type": "plan_expiring", "label": "Plan ending soon", "group": "restaurant", "enabled": true}, {"type": "monthly_report", "label": "Monthly report ready", "group": "restaurant", "enabled": true}]}
```

### `PUT /api/v1/mobile/notification-settings`

Only switched-off types are stored.

```http
PUT /api/v1/mobile/notification-settings
Authorization: Bearer 1|…
Idempotency-Key: e5f6…

{"settings": {"monthly_report": false}}
```

```json
{"data": [{"type": "monthly_report", "label": "Monthly report ready", "group": "restaurant", "enabled": false}]}
```

### `GET /api/v1/mobile/notifications`

Inbox, newest first, 30 a page, last 90 days. `?cursor=` from `meta.next_cursor`.

```http
GET /api/v1/mobile/notifications
Authorization: Bearer 1|…
```

```json
{"data": [{"id": 1, "type": "monthly_report", "title": "Your October report", "body": "Views up 12%", "link": "/r/1/insights", "tenant_id": 1, "read": false, "created_at": "2026-11-16T17:12:01+00:00"}], "meta": {"next_cursor": null, "unread": 1}}
```

### `POST /api/v1/mobile/notifications/read`

```http
POST /api/v1/mobile/notifications/read
Authorization: Bearer 1|…
Idempotency-Key: 0a0b…

{"ids": [1, 2]}
```

`204 No Content`. `{"all": true}` marks everything read.

Push payload `data`: `type`, `link` (an app path), `notification_id`, `tenant_id`. Links: `/r/{tenant}/billing`, `/r/{tenant}/insights`, `/r/{tenant}/team`, `/r/{tenant}/news/{id}`, `/inbox`, `/p`.

| Type | Who | When |
|---|---|---|
| `plan_expiring` / `plan_expired` | owners | `push:plan-reminders` 09:00: 7, 3, 1 days before; the day after |
| `monthly_report` | owners, managers | with the monthly Insights e-mail |
| `invitation_accepted` | owners | a member accepts |
| `platform_announcement` | owners, managers / anyone chosen | a new announcement, or a push sent from Super Admin → Mobile app → Push |
| `signup_new` / `signup_pending` | Super Admins | e-mail confirmed (live / waiting for approval) |
| `payment_recorded` | the other Super Admins | payment saved |
| `backup_failed` | Super Admins | with the daily backup alert |
| `health_warning` | Super Admins | `push:health` every 15 min |
| `followups_due` | Super Admins | `push:followups` 08:00 |

Sending: inbox row at once; the phone push is a queued job (`SendPush`, 3 tries) through FCM HTTP v1 with `FCM_CREDENTIALS` (docs/deploy.md §10a). With `app_features.push` off, only the inbox row is made.

## "Log in as" (QUESTIONS #188)

### `POST /api/v1/platform/restaurants/{restaurant}/impersonate-token`

Super Admin with two-factor, app token. The token works for 2 hours, has the same blocked actions as the web (`403 impersonation.blocked`) and no platform access. `GET me` shows `impersonation`.

```http
POST /api/v1/platform/restaurants/12/impersonate-token
Authorization: Bearer 3|…
Idempotency-Key: 9f8e…
```

```json
{"data": {"token": "4|bjquq0U63LqBX8ci…", "tenant_id": 12, "ends_at": "2026-11-16T19:12:01+00:00"}}
```

### `POST /api/v1/impersonation/stop`

With the "log in as" token. Deletes it; the app goes back to the Super Admin's own token.

```http
POST /api/v1/impersonation/stop
Authorization: Bearer 4|…
Idempotency-Key: 7d6c…
```

`204 No Content`.

## Help centre (QUESTIONS #194)

The app shows the same articles as the web admin, and keeps the last answer for offline use. Articles about switched-off features, and about jobs the person's role can't do, are left out by the server. Placeholders (plan prices and limits, contact) are already filled in. `body` is Markdown; `html` is the same, for the web. Links to other articles are `help:slug`; pictures are `/help/shots/…webp` with the title `phone`, `desktop` or `app`.

### `GET /api/v1/t/{tenant}/help`

Sends `ETag`; with `If-None-Match` the answer is `304` when nothing changed.

```http
GET /api/v1/t/12/help
Authorization: Bearer 1|…
If-None-Match: "5d41402abc4b2a76b9719d911017c592"
```

```json
{"data": {
  "topics": [{"key": "getting_started", "title": "Getting started"}, {"key": "menu", "title": "Your menu"}],
  "articles": [{
    "slug": "add-a-dish", "topic": "menu", "title": "Add a dish", "summary": "Name, price, photo and description, step by step.",
    "pages": ["/admin/menu"], "app": ["/r/:tid/menu", "/r/:tid/dishes/new"], "keywords": ["item", "new dish"],
    "body": "1. Open **Menu** and the category.\n…", "html": "<ol><li>Open <strong>Menu</strong> …</li></ol>", "updated_at": null
  }],
  "voted": {"add-a-dish": true}
}}
```

`pages` are web admin paths and `app` are app paths (`:tid` = the restaurant): the **?** button opens the first article whose path matches the screen.

### `GET /api/v1/help/public`

Signed out (sign-in screen): only the articles marked public, such as "I can't sign in". Same answer shape.

```http
GET /api/v1/help/public
```

```json
{"data": {"topics": [{"key": "problems", "title": "Problems and answers"}], "articles": [{"slug": "cant-sign-in", "topic": "problems", "title": "I can't sign in", "…": "…"}], "voted": {}}}
```

### `POST /api/v1/help/feedback`

"Was this helpful?" One answer per person and article; a new answer replaces the old one. 30 a minute.

```http
POST /api/v1/help/feedback
Authorization: Bearer 1|…
Idempotency-Key: 4e1f…

{"audience": "restaurant", "slug": "add-a-dish", "helpful": true}
```

`204 No Content`. Unknown article: `404`.

## Super Admin: overview and emergency switches (app `/p`)

The app's Super Admin screen shows numbers from `platform/dashboard` and four switches: **maintenance**, **self sign-up**, **push**, **Super Admin app sign-in**. Everything else opens the web console.

### `GET /api/v1/platform/dashboard`

```http
GET /api/v1/platform/dashboard
Authorization: Bearer 3|…
```

```json
{"data": {"states": {"trial": 4, "active": 21, "grace": 1, "read_only": 0, "unavailable": 0, "suspended": 0, "pending": 2}, "restaurants": 28, "signups_30d": 6, "views_30d": 18420, "scans_30d": 3311, "per_plan": {"Free": 9, "Pro": 19}, "expiring": [], "revenue_30d": []}}
```

### `GET /api/v1/platform/settings`

```http
GET /api/v1/platform/settings
Authorization: Bearer 3|…
```

```json
{"data": {"name": "Menu", "support_email": "hello@menu.example", "domain": "menu.example", "default_locale": "en", "default_currency": "ETB", "signup_open": true, "signup_approval": false, "app_min_version": "1.0.0", "app_latest_version": "1.4.0", "terms": "…", "privacy": "…"}}
```

(Shortened: the answer has every platform setting.)

### `PUT /api/v1/platform/settings`

Send only what changes. The app's "self sign-up" switch:

```http
PUT /api/v1/platform/settings
Authorization: Bearer 3|…
Idempotency-Key: 3e2d…

{"signup_open": false}
```

```json
{"data": {"name": "Menu", "signup_open": false, "…": "…"}}
```

## Super Admin: feature switches (QUESTIONS #192)

### `GET /api/v1/platform/settings/features`

One row per feature: is it on for everyone, and how many plans / restaurants have it off.

```http
GET /api/v1/platform/settings/features
Authorization: Bearer 3|…
```

```json
{"data": [
  {"key": "branches", "enabled": true, "label": "More branches", "plan_feature": true, "plans_off": 2, "restaurants_off": 0},
  {"key": "sold_out", "enabled": false, "label": "\"Sold out\" marks", "plan_feature": true, "plans_off": 0, "restaurants_off": 1},
  {"key": "signup", "enabled": true, "label": "Self sign-up", "plan_feature": false, "plans_off": 0, "restaurants_off": 0}
]}
```

### `PUT /api/v1/platform/settings/features`

Only OFF keys are stored. Guest menus are rebuilt when a restaurant feature changes. At least one menu style must stay on.

```http
PUT /api/v1/platform/settings/features
Authorization: Bearer 3|…
Idempotency-Key: 6a5b…

{"features": {"sold_out": false, "share": true}}
```

Answer: the same rows as `GET`. All four styles off: `422 {"errors": {"features": ["Keep at least one menu style switched on."]}}`.

## Super Admin: Mobile app control (QUESTIONS #193)

### `GET /api/v1/platform/mobile-app`

```http
GET /api/v1/platform/mobile-app
Authorization: Bearer 3|…
```

```json
{"data": {
  "restaurant_sign_in": true, "super_admin_sign_in": true, "sign_in_message": "",
  "maintenance": false, "maintenance_message": "",
  "latest_version": "1.4.0", "release_notes": "Faster menu", "min_version": "1.0.0", "recommended_version": "1.3.0",
  "banner": {"text": "New: happy hour", "link": "/r/1/promotions", "starts_at": "2026-11-16T06:00:00+03:00", "ends_at": "2026-11-20T21:00:00+03:00", "audience": "restaurant"},
  "app_features": {"biometric_lock": true, "push": true, "analytics": true, "crash_reports": true},
  "links": {"play_store": "https://play.google.com/store/apps/details?id=com.kulmiss.menuadmin", "app_store": "", "privacy": "", "terms": "", "support_whatsapp": "+251 911 222 333"}
}}
```

### `PUT /api/v1/platform/mobile-app`

Send only what changes. Versions are `x.y.z`; `min_version` must not be above `latest_version`. Links must be `https://`; the banner link may also be an app path (`/inbox`).

```http
PUT /api/v1/platform/mobile-app
Authorization: Bearer 3|…
Idempotency-Key: b7c8…

{"maintenance": true, "maintenance_message": "Back at 10:00"}
```

Answer: the full settings, like `GET`. `{"min_version": "2.0.0"}` above latest → `422 errors.min_version`.

### `POST /api/v1/platform/mobile-app/pushes`

Sends to everyone chosen who is signed in to the app (inbox + phone). `dry_run: true` only counts. 10 a minute.

`audience.to`: `all`, `restaurant`, `super_admin`. With `restaurant`, optional `roles` (`owner`, `manager`, `staff`), `plan_ids`, `tenant_ids`.

```http
POST /api/v1/platform/mobile-app/pushes
Authorization: Bearer 3|…
Idempotency-Key: d9e0…

{"title": "New styles", "body": "Try style D", "link": "/inbox", "audience": {"to": "restaurant", "roles": ["owner"]}, "dry_run": true}
```

```json
{"data": {"recipients": 14, "with_app": 9}}
```

Without `dry_run`: `201 {"data": {"id": 3, "recipients": 14, "with_app": 9}}`. Nobody matches: `422 errors.audience`.

### `GET /api/v1/platform/mobile-app/pushes`

The last 100 sent.

```http
GET /api/v1/platform/mobile-app/pushes
Authorization: Bearer 3|…
```

```json
{"data": [{"id": 3, "title": "New styles", "body": "Try style D", "link": "/inbox", "audience": {"to": "restaurant", "roles": ["owner"]}, "recipients": 14, "with_app": 9, "sent_by": "Hana Tesfaye", "created_at": "2026-11-16T17:10:24+00:00"}]}
```

### `GET /api/v1/platform/mobile-app/devices`

Server table: `page`, `per_page`, `q` (name, e-mail, device), `sort` (`user`, `app_version`, `last_used_at`, `-` for newest first), `platform` (`android`, `ios`), `version`, `kind` (`restaurant`, `super_admin`), `from`/`to` (last seen).

```http
GET /api/v1/platform/mobile-app/devices?page=1&platform=android&sort=-last_used_at
Authorization: Bearer 3|…
```

```json
{"data": [{"id": 1, "device": "Pixel 8", "platform": "android", "app_version": "1.2.0", "user": {"id": 1, "name": "Sara Bekele", "email": "sara@cafe.example", "is_super_admin": false}, "restaurants": "Abebe Café", "push_on": true, "last_used_at": "2026-11-16T17:10:24+00:00", "created_at": "2026-11-10T09:00:00+00:00"}], "meta": {"total": 1, "page": 1, "per_page": 25, "pages": 1}}
```

### `DELETE /api/v1/platform/mobile-app/devices/{device}`

Signs that device out (its token and push address are deleted).

```http
DELETE /api/v1/platform/mobile-app/devices/1
Authorization: Bearer 3|…
Idempotency-Key: f1a2…
```

`204 No Content`.

### `POST /api/v1/platform/mobile-app/devices/sign-out-all`

Every app device of every person, except the Super Admin's own current one. 3 a minute.

```http
POST /api/v1/platform/mobile-app/devices/sign-out-all
Authorization: Bearer 3|…
Idempotency-Key: a3b4…

{"confirm": true}
```

```json
{"data": {"signed_out": 41}}
```

### `GET /api/v1/platform/mobile-app/stats`

```http
GET /api/v1/platform/mobile-app/stats
Authorization: Bearer 3|…
```

```json
{"data": {"devices": 52, "active_7d": 37, "active_30d": 48, "people": 44, "platforms": [{"key": "android", "count": 45}, {"key": "ios", "count": 7}], "versions": [{"key": "1.2.0", "count": 40}, {"key": "1.1.0", "count": 12}], "push_opt_in": 81, "below_min": 0}}
```

`push_opt_in` is a percentage of devices (`null` with no devices); `below_min` counts devices older than `versions.min`.

Everything else (restaurant `t/{tenant}/…`, Super Admin `platform/…`) works the same with the token: see [api-reference.md](api-reference.md).
