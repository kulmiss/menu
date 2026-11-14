# Mobile app API (Menu Admin app)

The Flutter app [`kulmiss/menu-admin-app`](https://github.com/kulmiss/menu-admin-app) uses the same `/api/v1` JSON API as the web admin, with a **Bearer token** instead of the cookie session. Every create/change request still needs an `Idempotency-Key` header (except sign-in, below). Errors use the usual shapes: `422 {message, errors}`, `402/403/409 {message, code}`.

## Sign-in (QUESTIONS #187)

| Request | Answer |
|---|---|
| `POST /api/v1/mobile/auth/login` `{email, password, device_name, platform: android\|ios, app_version?}` | `200 {token, user}` or `200 {two_factor: true, challenge}`; wrong password `422 errors.email`; 5 tries a minute per e-mail + address, 30 per address |
| `POST /api/v1/mobile/auth/two-factor` `{challenge, code \| recovery_code}` | `200 {token, user}`; wrong code `422 errors.code`; challenge gone (5 minutes, used, or 5 wrong codes) `410 code=mobile.challenge_expired` |
| `POST /api/v1/mobile/auth/logout` · `…/logout-all` | `204`; revokes this token / every token of the user (the web session stays) |

Sign-in requests have **no** Idempotency-Key: they change nothing, and a stored answer would keep the token in the database.

Tokens: one per device (`device_name`), stored hashed by Sanctum, `expires_at` = 365 days, and **sliding idle expiry**: unused for 60 days (Super Admins: 14 days) → `401`. `mobile:prune-tokens` deletes expired/idle ones nightly (03:25).

## Account and devices (token only — the web session gets `400 mobile.token_required`)

| Request | Notes |
|---|---|
| `GET /api/v1/mobile/devices` | `[{id, name, platform, app_version, last_used_at, created_at, current}]` |
| `DELETE /api/v1/mobile/devices/{id}` | only your own |
| `PUT /api/v1/mobile/account/profile` `{name, email, locale?}` | same rules as the web; a new e-mail must be confirmed again |
| `PUT /api/v1/mobile/account/password` `{current_password, password, password_confirmation}` | signs out your other devices |
| `POST /api/v1/mobile/account/two-factor` `{password}` | starts setup (new secret) |
| `GET /api/v1/mobile/account/two-factor` | `{svg, url, secret}` while setup is pending, else `409` |
| `POST /api/v1/mobile/account/two-factor/confirm` `{code}` | `{recovery_codes}` |
| `DELETE /api/v1/mobile/account/two-factor` `{password}` | turns it off |
| `POST /api/v1/mobile/account/two-factor/recovery-codes` `{password}` | new `{recovery_codes}` |

Answers with secrets carry `Cache-Control: no-store`; the idempotency table then keeps only the key and status (a replay gets `409 idempotency.not_replayable`), never the codes.

## "Log in as" (QUESTIONS #188)

`POST /api/v1/platform/restaurants/{id}/impersonate-token` (Super Admin with two-factor, app token) → `{token, tenant_id, ends_at}`: a token for the restaurant owner that works for 2 hours, with the same blocked actions as the web (`403 impersonation.blocked`) and no platform access. `/me` shows `impersonation`. `POST /api/v1/impersonation/stop` with that token deletes it; the app then goes back to the Super Admin's own token.

## Push notifications and inbox (QUESTIONS #189)

| Request | Notes |
|---|---|
| `POST /api/v1/mobile/push-tokens` `{token, platform}` | registers this phone's FCM token (linked to this sign-in; signing out removes it). A token already known moves to the new user. |
| `DELETE /api/v1/mobile/push-tokens` `{token}` | |
| `GET /api/v1/mobile/notification-settings` | `[{type, label, group: restaurant\|platform, enabled}]` for the user's roles |
| `PUT /api/v1/mobile/notification-settings` `{settings: {type: bool}}` | only switched-off types are stored |
| `GET /api/v1/mobile/notifications?cursor=` | inbox, newest first, 30 a page, last 90 days, `meta.unread`, `meta.next_cursor` |
| `POST /api/v1/mobile/notifications/read` `{ids: [..]}` or `{all: true}` | |

Push payload `data`: `type`, `link` (an app path), `notification_id`, `tenant_id`. Links: `/r/{tenant}/billing`, `/r/{tenant}/insights`, `/r/{tenant}/team`, `/r/{tenant}/news/{id}`, `/p/restaurants/{id}`, `/p/restaurants/{id}/payments`, `/p/approvals`, `/p/backups`, `/p/dashboard`, `/p/restaurants?follow_up=due`.

| Type | Who | When |
|---|---|---|
| `plan_expiring` / `plan_expired` | owners | `push:plan-reminders` 09:00: 7, 3, 1 days before; the day after |
| `monthly_report` | owners, managers | with the monthly Insights e-mail |
| `invitation_accepted` | owners | a member accepts |
| `platform_announcement` | owners, managers of the restaurants it shows to | a new announcement that starts now |
| `signup_new` / `signup_pending` | Super Admins | e-mail confirmed (live / waiting for approval) |
| `payment_recorded` | the other Super Admins | payment saved |
| `backup_failed` | Super Admins | with the daily backup alert |
| `health_warning` | Super Admins | `push:health` every 15 min: jobs waiting > 15 min, ≥ 10 failed jobs in an hour, < 10 % disk free; each at most every 6 h |
| `followups_due` | Super Admins | `push:followups` 08:00 |

Sending: inbox row at once; the phone push is a queued job (`SendPush`, 3 tries) run by the existing cron queue worker, through FCM HTTP v1 with the service account in `FCM_CREDENTIALS` (docs/deploy.md §10a). Without it, nothing is sent and nothing fails. Tokens of removed apps (404/UNREGISTERED) are deleted.

Everything else (restaurant `t/{id}/…`, Super Admin `platform/…`) works unchanged with the token.
