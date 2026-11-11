# PLAN — Digital menu SaaS

Status: **approved; in build (Partitions 1–7, 9–11 done; 8 deferred).** Progress is in [handover.md](handover.md). **Speed is the top priority** — see §15, which wins over any older figure in this file.
Decisions behind this plan are in [QUESTIONS.md](QUESTIONS.md). Progress and next steps are in [handover.md](handover.md).

---

## 1. Product in one paragraph

Restaurants and cafés publish a fast, clean menu that customers open from a QR code. Restaurants manage everything themselves in an admin app that works well on a phone. The platform owner (Super Admin) sells plans, manages restaurants and records payments manually. Viewing only: no ordering, payments or reviews on the customer side.

## 2. Ground rules (from the brief + owner answers)

| Rule | How we meet it |
|---|---|
| Complete, final scope | Exactly the features in the brief. No placeholders except the Terms/Privacy text, which the owner supplies. |
| Multi-tenant, one DB | `tenant_id` on every restaurant-owned table, global scope + composite foreign keys (§4.3), isolation test suite (§9). |
| Server-side permissions | Laravel policies + gates on every endpoint; UI hiding is cosmetic only. |
| Idempotency | `Idempotency-Key` required on every POST/PUT/PATCH/DELETE (§6.3). |
| Activity log | Every important change written to `activity_log` (§4.9). |
| i18n everywhere | No hard-coded strings in SPA, Blade, API messages, emails or PDFs. |
| Accessibility | 44 px touch targets on touch screens, contrast ≥ 4.5:1, light + dark, RTL. |
| Admin on phones | Every admin screen designed at 375 px first. |
| No secrets / real data | `.env.example` only; demo data is fictional. |
| Tests in every partition | Pest (backend), Vitest (SPA + public JS), Playwright smoke in final QA. |
| CI checks only | Lint, typecheck, tests, build, migrate → rollback → migrate. CI never commits. |
| Git flow | Small PRs into `develop`; `main` protected and only updated by a release merge the owner does. |
| **Docker = local dev only** | Production runs without Docker on shared hosting (cPanel). On a VPS, Docker is optional. Both documented. |
| **Shared hosting is the main target** | No Node.js, Redis, queue workers or Docker on the server. Database/file cache and sessions. Heavy jobs via cron or synchronous. Admin frontend built in CI and uploaded. Images processed with GD (Imagick optional). |

## 3. Architecture

```
 menu.example.com
 ├── /{slug}[/{branch}][?table=12&lang=am]   Public menu — Laravel Blade, ~5 KB vanilla JS, inline CSS
 ├── /q/{code}                               Stable QR link → 302 to current slug/branch/table (+ scan count)
 ├── /terms  /privacy                        Static Blade pages (owner-supplied text, placeholders for now)
 ├── /auth/*                                 SPA: sign in, sign up, forgot/reset password, verify email, 2FA, accept invite
 ├── /admin/*                                SPA: restaurant admin (route tree 1)
 ├── /platform/*                             SPA: Super Admin (route tree 2, code-split)
 ├── /api/v1/*                               Laravel JSON API, Sanctum cookie (SPA) auth
 └── /media/*                                Processed images (public disk under public/, no symlink needed)
```

### 3.1 Backend
- **Laravel 13** (latest stable at build time, pinned in `composer.lock`), **PHP ≥ 8.3**, **MySQL ≥ 8.0 / MariaDB ≥ 10.6**.
- Auth: **Sanctum** stateful SPA cookies + **Fortify** (headless) for register, login, password reset, email verification and TOTP 2FA.
- Cache, sessions, queue: **`database`** drivers (file cache allowed). No Redis.
- Cron (one line on cPanel): `* * * * * php /home/USER/menu/artisan schedule:run`. The scheduler runs `queue:work --stop-when-empty --max-time=50` every minute, plus the daily jobs in §8.
- Images: **GD** via Intervention Image v3 (Imagick used automatically if present).
- Filesystem: `public` disk rooted at `public/media` (no `storage:link`). An S3-compatible disk is a config change.
- QR codes: **chillerlan/php-qrcode 6.0.1** (MIT/Apache-2.0; with chillerlan/php-settings-container 3.3.0, MIT). Table-card PDFs: **setasign/fpdf 1.9.0** (the original FPDF, MIT, pure PHP, no external programs or font files) — owner's decision, 2026-11-10 (QUESTIONS #75).

### 3.2 Admin + Super Admin SPA (one app)
- Based on **shadcn-admin @ `e16c87f213a5ba5e45964e9b67c792105ec74d26`** (MIT, notice in `THIRD_PARTY_NOTICES`).
- React 19, Vite, TypeScript, TanStack Router (file-based) / Query / Table, react-hook-form + zod, zustand, Tailwind v4, shadcn/ui (Radix).
- Two route trees: `/admin/*` (restaurant) and `/platform/*` (Super Admin), lazy-loaded so restaurant users never download platform code.
- Built in CI (`pnpm build`) into `public/app/` inside the release zip. The server never runs Node.
- New packages (approved in principle at Step 1, pinned at setup): `i18next`, `react-i18next`, `@dnd-kit/core`, `@dnd-kit/sortable`, `react-easy-crop`.

### 3.3 Public menu
- Blade view + one CSS file (Tailwind CLI in CI, inlined into `<head>`) + one vanilla JS file (≤ 6 KB gz, `defer`).
- Each menu is written to a **static HTML file** (plus `.gz`/`.br`) per `tenant · branch · locale`, served by Apache/Nginx **without starting PHP** (§15.2). Any admin change bumps `tenants.cache_version` and rewrites the files.
- Time-dependent parts (active menu by schedule, "Open now / Closed", promotion dates) are computed **in the browser** from data embedded in the page, in the restaurant's time zone. The server-rendered state (at render time) is the no-JS fallback. So caching never shows a wrong state for long.
- Views are counted by a 1-request beacon (`navigator.sendBeacon` → `POST /api/v1/beacon`) sent **after the page has loaded**, so caching doesn't break insights. No cookies, no stored IPs.
- **Budget (before images): ≤ 30 KB gzipped for HTML + CSS + JS** (hard, enforced in CI — §15). JS ≤ 5 KB. Fonts are extra and only when a custom font is chosen.
- Images: `<picture>` with AVIF (when the server can make it) + WebP + JPEG fallback, `srcset`/`sizes` 320/640/1280, explicit width/height, inline blurred placeholder (< 300 bytes); first visible images eager with `fetchpriority="high"`, the rest `loading="lazy" decoding="async"` (§15.2).
- SEO: `<title>`, meta description, canonical (strips `table`), Open Graph + Twitter card with the cover image (1200×630 crop), `hreflang` per enabled language, JSON-LD `Restaurant` + `Menu`, `robots.txt`, `sitemap.xml` of active restaurants.
- Light/dark follows the phone setting (`prefers-color-scheme`), with both palettes in the inlined CSS and brand colours adjusted per mode to keep ≥ 4.5:1.
- Fonts: **system fonts by default**; optional curated self-hosted list (Inter, Manrope, Lora, Nunito), subset WOFF2, preloaded, `font-display: swap`.

### 3.4 Deploy targets (documented in Partition 13)
- **Shared hosting (cPanel):** CI produces `menu-release-<version>.zip` (vendor installed with `--no-dev`, SPA built, public CSS/JS built). Upload, point the domain's document root at `menu/public` (or copy `public/` into `public_html` with the documented `index.php` path change), set `.env`, run migrations from cPanel Terminal or the provided one-time web installer guarded by a setup token (deleted after use), add the cron line.
- **VPS:** same zip with Nginx + PHP-FPM + MySQL + cron, **or** the optional production `docker-compose.prod.yml`.
- **Local dev:** `docker compose up` (PHP-FPM, Nginx, MySQL, Mailpit, Node for Vite). Docker is not used in production on shared hosting.

## 4. Data model

Conventions: `id` BIGINT unsigned; money in **minor units** (`price_minor`, ETB santim) + the tenant's currency; translatable text in **JSON columns** keyed by locale (`{"en":"Macchiato","am":"ማኪያቶ"}`) via a custom cast; `sort` = integer for drag-and-drop; timestamps on every table. **Every restaurant-owned table has `tenant_id`** (FK → `tenants.id`, indexed, first column of composite indexes).

### 4.1 Platform tables (no `tenant_id`)
| Table | Key columns |
|---|---|
| `users` | name, email (unique), password, locale, is_super_admin, email_verified_at, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, last_login_at |
| `plans` | name (json), slug, price_minor, currency, interval (month/year), trial_days, limits (json: menus, items, branches, languages, photos, members), is_public, is_active, sort |
| `plan_features` | plan_id, feature_key, enabled |
| `themes` | name (json), preset (json: layout, colours, font, radius), is_active, sort — the "menu themes/templates" Super Admin offers |
| `platform_settings` | key, value (json): name, logo, domain, support email, default locale (`en`), default currency (`ETB`), legal text placeholders |
| `platform_announcements` | title (json), body (json), level (info/warning), audience (all / plan ids), starts_at, ends_at |
| `announcement_reads` | platform_announcement_id, user_id, read_at |
| `idempotency_keys` | user_id, tenant_id (nullable), key, method, path, request_hash, status (pending/done), response_status, response_body, expires_at — unique(user_id, key) |
| `impersonations` | super_admin_id, tenant_id, user_id, reason, started_at, ended_at, ip_hash |
| Laravel | sessions, cache, cache_locks, jobs, failed_jobs, password_reset_tokens, personal_access_tokens |

### 4.2 The tenant
| Table | Key columns |
|---|---|
| `tenants` (restaurant) | slug (unique), name, status (`active`/`suspended`), plan_id, trial_ends_at, paid_until, suspended_at, suspended_reason, timezone (default `Africa/Addis_Ababa`), default_locale, currency_code (default `ETB`), currency_format (json: symbol, position, decimals, separators), theme_id, cache_version, deleted_at (soft delete, purged after 30 days) |
| `tenant_slug_history` | tenant_id, old_slug — old slugs 301 to the current one; reserved forever for that tenant |
| `tenant_feature_overrides` | tenant_id, feature_key, enabled — wins over the plan |
| `payments` | tenant_id, plan_id, amount_minor, currency, method (cash/bank/mobile money/other), reference, paid_at, period_start, period_end, recorded_by, notes — `paid_until` is updated from these |
| `memberships` | tenant_id, user_id, role (`owner`/`manager`/`staff`), can_edit_prices, invited_by — unique(tenant_id, user_id); exactly one owner |
| `invitations` | tenant_id, email, role, can_edit_prices, token_hash, expires_at, accepted_at, invited_by |

**Subscription state** (computed from dates, never stored):

| State | When | Admin | Public menu |
|---|---|---|---|
| `trial` | now < trial_ends_at | full | shown |
| `active` | now < paid_until | full | shown |
| `grace` | expiry → +7 days | full + banner "Subscription expired — renew within N days" | shown |
| `read_only` | +7 → +21 days | read-only (all writes 402 except account/billing views) + banner | shown; **owner only** (signed-in owner session) sees a small "subscription expired" note |
| `unavailable` | after +21 days | read-only | "Menu unavailable" page (HTTP 503 with `Retry-After`, `noindex`) |
| `suspended` | Super Admin action | sign-in shows reason | "Menu unavailable" |

### 4.3 Isolation mechanics
- `BelongsToTenant` trait: global scope `where tenant_id = current()`, sets `tenant_id` on create, throws if no tenant is bound (fail closed).
- Current tenant is bound by middleware from the URL (`/api/v1/t/{tenant}/…`) **after** checking the user's membership; public routes bind it from the slug.
- **Composite foreign keys:** each tenant table has `UNIQUE(tenant_id, id)`, and child FKs reference `(tenant_id, parent_id)`. The database itself refuses an item that points at another tenant's category.
- Super Admin code reads across tenants only through explicit `Tenant::withoutScope()` calls in the `Platform` namespace (an architecture test forbids it elsewhere).

### 4.4 Restaurant profile, info and appearance
| Table | Key columns |
|---|---|
| `tenant_profiles` | tenant_id, description (json), address (json), lat, lng, maps_url, phone, whatsapp, telegram, email, socials (json: facebook, instagram, tiktok, x, youtube, website), wifi_name, wifi_password, show_wifi |
| `tenant_appearance` | tenant_id, logo_media_id, cover_media_id, colors (json: primary, accent; text colour auto-derived for ≥ 4.5:1), font, layout (`list`/`grid`/`large`), show_prices, show_photos, show_descriptions, show_calories, theme_id |
| `tenant_languages` | tenant_id, locale, is_enabled, is_default, sort |

### 4.5 Branches, tables, QR, hours
| Table | Key columns |
|---|---|
| `branches` | tenant_id, slug, name (json), address (json), lat, lng, maps_url, phone, is_default, is_active, sort |
| `dining_tables` | tenant_id, branch_id, label, sort |
| `qr_codes` | tenant_id, code (8 chars, unique, never reused), branch_id (null = restaurant), dining_table_id (null), label |
| `opening_hours` | tenant_id, branch_id (null = restaurant default), weekday (0–6), opens_at, closes_at — several rows per day = breaks; closes_at < opens_at = past midnight |
| `closures` | tenant_id, branch_id (null = all), starts_on, ends_on, note (json) — holidays |

### 4.6 Menus
| Table | Key columns |
|---|---|
| `menus` | tenant_id, name (json), slug, schedule_mode (`always`/`scheduled`), is_visible, sort |
| `menu_schedules` | tenant_id, menu_id, weekday, starts_at, ends_at |
| `menu_branch` | tenant_id, menu_id, branch_id — no rows = all branches |
| `categories` | tenant_id, menu_id, name (json), description (json), icon (lucide key, nullable), media_id, is_visible, sort |
| `items` | tenant_id, category_id, name (json), summary (json, short line), description (json), price_minor (null when variants carry prices), media_id, calories, prep_minutes, is_sold_out, is_visible, is_new, is_popular, sort |
| `item_variants` | tenant_id, item_id, name (json), price_minor, is_default, sort |
| `addon_groups` | tenant_id, name (json), min_choices, max_choices, sort — reusable across items |
| `addon_options` | tenant_id, addon_group_id, name (json), price_minor, is_sold_out, sort |
| `item_addon_group` | tenant_id, item_id, addon_group_id, sort |
| `tags` | tenant_id, key (system key or null), name (json, used when key is null), sort — system tags (vegan, vegetarian, spicy, halal, gluten-free, new, popular) seeded per tenant and translated by i18n |
| `item_tag` | tenant_id, item_id, tag_id |
| `item_allergens` | tenant_id, item_id, allergen — fixed EU-14 keys (gluten, crustaceans, eggs, fish, peanuts, soy, milk, nuts, celery, mustard, sesame, sulphites, lupin, molluscs) |
| `branch_item_overrides` | tenant_id, branch_id, item_id, price_minor (null = inherit), is_sold_out (null = inherit), is_hidden (null = inherit) |
| `branch_variant_prices` | tenant_id, branch_id, item_variant_id, price_minor — a branch's price for one size (QUESTIONS #78) |

### 4.7 Promotions
| Table | Key columns |
|---|---|
| `promotions` | tenant_id, type (`special`/`announcement`), title (json), body (json), media_id, item_id (null), branch_id (null = all), starts_at, ends_at, is_active, sort |

"New" and "Popular" badges are `items.is_new` / `items.is_popular`; `is_new` can carry an optional `new_until` date.

### 4.8 Media
| Table | Key columns |
|---|---|
| `media` | tenant_id, disk, base_path, mime, width, height, bytes, variants (json: 320/640/1280 webp + 640 jpg + og 1200×630), dominant_color, alt (json), created_by |

Upload flow: the browser crops (react-easy-crop) and downscales to ≤ 2048 px **before** upload (keeps shared-hosting memory safe), server validates (≤ 8 MB, jpeg/png/webp/heic→rejected with a clear message), auto-orients, strips EXIF, writes variants synchronously with GD. Photo count is limited per plan.

### 4.9 Insights and log
| Table | Key columns |
|---|---|
| `stats_daily` | tenant_id, date (restaurant's local day), branch_id (0 = none), metric (`menu_view`/`qr_scan`/`item_view`), subject_id (0 / item / qr_code), count — unique key, `INSERT … ON DUPLICATE KEY UPDATE count = count + 1` |
| `activity_log` | tenant_id (null = platform), actor_id, impersonator_id, action (`item.updated`…), subject_type, subject_id, changes (json before/after, secrets masked), ip_hash, created_at — kept 12 months |

Bots (known UA list) are not counted. One menu view per page load; item view when the detail sheet opens; QR scan on `/q/{code}`.

## 5. Roles and permissions (enforced by policies)

| Capability | Owner | Manager | Staff |
|---|:-:|:-:|:-:|
| Toggle sold out (items, options, per branch) | ✓ | ✓ | ✓ |
| Edit prices (incl. bulk %) | ✓ | ✓ | only if `can_edit_prices` |
| Menus, categories, items, add-ons, import/export, translations | ✓ | ✓ | – |
| Branches, tables, QR codes | ✓ | ✓ | – |
| Appearance, restaurant info, hours, promotions | ✓ | ✓ | – |
| Insights | ✓ | ✓ | – |
| Team: invite/remove managers & staff | ✓ | staff only | – |
| Custom slug, plan & payments view, delete restaurant | ✓ | – | – |
| **Super Admin**: platform, all restaurants, plans, features, log in as | `users.is_super_admin` (2FA required) |

"Log in as": starts a separate impersonation session (60 min max), red banner in the SPA, every action logged with `impersonator_id`, and blocked actions: password/email/2FA changes, team ownership changes, slug change, restaurant deletion.

Plan limits are checked on create (and on import) server-side; over the limit after a downgrade → existing data stays, creating more returns 402 with a clear message.

## 6. API

### 6.1 Shape
- `/api/v1/auth/*` (Fortify), `/api/v1/me`, `/api/v1/t/{tenant}/…` (restaurant), `/api/v1/platform/…` (Super Admin), `/api/v1/beacon` (public, rate-limited).
- JSON resources, cursor/page pagination, validation errors in a standard shape with i18n message keys.
- Rate limits: auth 5/min, beacon 60/min per IP hash, API 120/min per user.

### 6.2 Main restaurant endpoints
`menus`, `menus/{id}/schedules`, `categories` (+ `reorder`, `duplicate`), `items` (+ `reorder`, `duplicate`, `sold-out`, `bulk-price`), `addon-groups`, `tags`, `media`, `import` (preview + commit), `export`, `translations` (grid view of missing strings), `branches` (+ `overrides`), `tables`, `qr-codes` (+ `png`, `svg`, `pdf`, `table-cards.pdf`), `profile`, `appearance` (+ `preview`), `languages`, `hours`, `closures`, `promotions`, `team`, `invitations`, `insights`, `account` (slug, plan, payments), `activity`.

### 6.3 Idempotency
- SPA sends `Idempotency-Key: <uuid>` on every mutation; one key per user action (retries reuse it). Missing key → **428**.
- Middleware inserts `(user_id, key)` as `pending` first (unique index = atomic lock). Same key + same body hash → replays the stored response. Same key + different body → **422**. Still pending → **409** (SPA waits and retries). Keys expire after 24 h (cron purge).
- Buttons also disable while pending; drag-and-drop reorders send the whole new order (naturally idempotent) with a key as well.

## 7. Screens

### 7.1 Customer (public)
1. **Menu page** — cover, logo, name, open/closed pill, today's special banner; sticky category tabs (scrollspy); items in the chosen layout (list / grid / large photos) with photo, name, summary, price (or "from"), tags, sold-out state; search button; filter sheet (dietary tags, "hide allergens…"); menu switcher when several menus are active (e.g. Breakfast / Drinks).
2. **Item sheet** — large photo, full description, variants with prices, add-on groups (min/max shown as information), allergens, calories, prep time.
3. **Info sheet** — hours per day with breaks + holiday notice, address + "Open in Maps", phone, WhatsApp, Telegram, socials, Wi-Fi (tap to copy password).
4. **Language switch** — hidden while the restaurant has one language (English only for now, §11); **branch switch** (if several).
5. **States** — closed now, holiday closure, menu not scheduled now ("Breakfast starts at 07:00"), empty search, menu unavailable, 404 slug.
6. **Terms / Privacy** pages.

### 7.2 Restaurant admin (`/admin`)
Phone-first; sidebar becomes a bottom-sheet menu on phones; sold-out toggle reachable in two taps.
1. **Dashboard** — today's views/scans, quick "sold out" list, subscription banner, platform announcements.
2. **Menu editor** — menus tabs → categories list (drag, show/hide, duplicate) → items list (drag, photo, price inline edit, sold-out switch, show/hide); search; bulk select → bulk price %, hide, move.
3. **Item editor** (sheet on desktop, full screen on phone) — tabs: Details · Prices & variants · Add-ons · Tags & allergens · Translations · Branches.
4. **Add-on groups**, **Tags**.
5. **Import / Export** — download template, upload CSV/XLSX, preview diff, commit.
6. **Translations** — grid of every name/description × enabled language, filter "missing".
7. **Branches** — list, editor (info, hours, price/availability overrides grid, tables).
8. **QR codes** — restaurant / branch / table codes, download PNG/SVG/PDF, table-card designer (logo, colour, text, preview, PDF for many tables).
9. **Appearance** — settings left, **live phone preview** right (on phones: toggle "Preview"); theme presets from Super Admin; contrast warnings.
10. **Restaurant info** — profile, contact, social, Wi-Fi, opening hours (per day, breaks, overnight), holiday closures.
11. **Promotions** — specials and announcements with dates, preview.
12. **Insights** — views per day chart, top items, top categories, scans per branch/table, date range.
13. **Team** — members, roles, "can edit prices", invitations.
14. **Account** — slug (with QR-safe redirect explanation), plan + limits usage bars, payments history, delete restaurant (owner).
15. **Settings (personal)** — profile, password, 2FA, interface language, theme.
16. **Auth** — sign in, sign up (restaurant name + slug + owner), verify email, forgot/reset, 2FA, accept invitation, restaurant switcher.

### 7.3 Super Admin (`/platform`)
1. **Dashboard** — restaurants by state (trial/active/grace/read-only/unavailable/suspended), new sign-ups (30 days), total views & scans, restaurants per plan, expiring soon.
2. **Restaurants** — table (search, filter by state/plan), create, edit, suspend/unsuspend, delete (soft, 30 days), feature overrides, record payment, **log in as**.
3. **Plans** — name, price, limits, features, trial length, public/active.
4. **Payments** — all recorded payments, filter, export CSV.
5. **Themes** — menu presets available to restaurants.
6. **Announcements** — to restaurant admins (all / by plan), scheduled.
7. **Activity log** — platform + per restaurant, filters.
8. **Platform settings** — name, logo, domain, email, default language (en) and currency (ETB), legal page text.

## 8. Scheduled work (cron → `schedule:run`)
Every minute: drain queue (emails). Hourly: purge expired idempotency keys. Daily: subscription state emails (7 days before, on expiry, on read-only, on unavailable), purge soft-deleted restaurants after 30 days, prune activity log > 12 months, prune expired invitations, rebuild `sitemap.xml`.

## 9. Testing strategy
- **Backend (Pest):** feature tests per endpoint (happy path, validation, each role, plan limits, idempotency replay/conflict), unit tests (hours/overnight/breaks, schedules, subscription states, price overrides, currency formatting, contrast), **tenant isolation suite** (two tenants; for every tenant resource: list/show/update/delete/attach across tenants → 404/422; composite FK rejects cross-tenant rows), **architecture tests** (every model under `App\Models\Tenant` uses `BelongsToTenant`; no `withoutScope` outside `Platform`; every mutating route has the idempotency middleware), migration test (migrate → rollback → migrate).
- **SPA (Vitest browser):** components, forms, permission-aware rendering, i18n key coverage (no missing keys in `en`; other locales report drafts), axios idempotency interceptor.
- **Public page:** Pest HTML tests (SEO tags, hreflang, layouts, sold-out, hidden prices), JS unit tests (open now, scheduling, search/filter), **size budget check** in CI.
- **Final QA:** Playwright smoke on 375/768/1024/1280/1920, light/dark, RTL, all roles, Lighthouse on the demo menu with the profiles in §15.5 (targets in §15.1; a11y ≥ 95).

## 10. CI (GitHub Actions, checks only — never commits)
Jobs on every PR to `develop`: `backend` (composer validate, Pint, Larastan level 6, Pest with MySQL 8 service), `migrations` (migrate → rollback → migrate), `frontend` (pnpm install --frozen-lockfile, ESLint, Prettier check, `tsc -b`, Vitest, build), `public` (build CSS/JS, 30 KB budget, **Lighthouse CI mobile throttled** on the demo menu — §15.5), admin bundle budget in `frontend` (§15.3), `release` (on tags only: builds the shared-hosting zip as a workflow artifact).

## 11. Internationalisation
**English only for now (owner, 2026-11-10; QUESTIONS #14, #15).** Admin, Super Admin and the customer menu ship in English. No Somali/Amharic/Arabic files, extra fonts or right-to-left work in this build.
Ready for more languages later without rewriting:
- Every interface text goes through `t()` (SPA) / `__()` (Laravel); adding one is a new locale file. The admin's language switch and the profile's language field appear by themselves once there are two languages (machine-draft marking via `_status` still works).
- Menu content keeps **JSON columns per field, keyed by language** (`{"en": "Macchiato"}`), with fallback to the default language.
- The customer menu's language switch and the admin's menu-translation screens stay **hidden until a restaurant has more than one language enabled** (Partition 8 is deferred; see §12).
- Numbers and money with `Intl` (Western digits), Gregorian calendar, 24 h or 12 h per locale. ETB shown as "Br 120" by default (symbol/position/decimals configurable per restaurant).

## 12. Partitions (each = one or more small PRs into `develop`)

| # | Partition | Main deliverables | Key tests |
|---|---|---|---|
| 0 | **Docs bootstrap** (this PR) | PLAN.md, QUESTIONS.md, handover.md | – |
| 1 | **Project setup** | Monorepo (`backend/`, `frontend/`), Laravel 13 API skeleton, shadcn-admin imported at pinned commit with Clerk + demos removed, i18n wiring, Docker dev, CI, THIRD_PARTY_NOTICES, `.env.example` | CI green, migrate/rollback |
| 2 | **Auth & tenancy** | Fortify + Sanctum, tenants, memberships, invitations, roles/policies, tenant middleware, `BelongsToTenant`, idempotency middleware, activity log, 2FA, auth screens, restaurant switcher | isolation suite base, idempotency, roles |
| 3 | **Menu data model & admin editor** | menus, schedules, categories, items, variants, add-ons, tags, allergens, media upload + crop, reorder, duplicate, bulk price, sold-out, import/export | CRUD × roles × limits, import preview |
| 4 ✅ | **Customer menu page** (PR #8) | Blade page rendered to **static files served without PHP**, inline CSS, ≤ 5 KB JS, layouts, search/filter, item sheet, service worker, beacon after load, SEO, `/q/{code}` without the database | HTML tests, **30 KB budget, Lighthouse CI**, JS units, query counts |
| 5 ✅ | **Appearance + live preview** (PR #9) | appearance settings, themes, fonts, contrast guard, preview iframe | contrast unit, preview render |
| 6 ✅ | **Branches + QR codes** (PR #10) | branches, overrides, tables, QR PNG/SVG/PDF, table cards | overrides resolution, PDF render |
| 7 ✅ | **Restaurant info + hours** (PR #11) | profile, contacts, Wi-Fi, hours with breaks/overnight, closures | hours units (incl. midnight) |
| 8 ⏸ | **Translations** — *deferred (English only, §11); nothing built* | languages, translation grid, fallback; hidden while one language | fallback, missing filter |
| 9 ✅ | **Promotions** (PR #13) | specials/announcements, badges | date windows |
| 10 ✅ | **Insights** (PR #14) | stats aggregation, charts, scans per table | counting, bot filter |
| 11 ✅ | **Super Admin** (PR #15) | dashboard, restaurants, plans, features, payments, themes, announcements, log, settings, log-in-as, subscription states | platform policies, impersonation limits, state machine |
| 12 | **Demo data** | fictional restaurants (see §13), seeders, demo images (generated, no real photos of people) | seeder runs on fresh DB |
| 13 | **Docs** | README, admin user guide (en), deploy: shared hosting + VPS (with/without Docker), legal page placeholders | – |
| 14 | **Final QA** | all sides, all roles, breakpoints, light/dark, RTL, the §15.5 network/CPU profiles, first and repeat visits, fixes | Playwright smoke, Lighthouse |
| 15 | **Final report** | PRs in merge order, test summary, open questions, go-live checklist | – |

## 13. Demo data (fictional)
- **Buna & Bread** — café, Addis Ababa (Bole), ETB, languages en/am, 2 branches (Bole, Piassa), Breakfast + All-day + Drinks menus.
- **Sahan Grill** — restaurant, Hargeisa, languages so/en/ar, 1 branch.
- **Lakeview Kitchen** — trial account, en only, to show trial banner.
All names, addresses and phone numbers are invented (phones use reserved/test formats). Demo photos are generated illustrations or royalty-free food images with licences listed in THIRD_PARTY_NOTICES.

## 14. Design
Mockups live on the design canvas (link in handover.md): customer menu on phone (light + dark, list and item sheet), admin menu editor (desktop + phone), Super Admin dashboard — all with the demo data above, using the shadcn-admin colour tokens (slate, oklch) and Inter.

## 15. Performance (top priority — owner, 2026-11-10)

The customer menu must open almost instantly, also on slow mobile networks and old phones. This section applies to **every partition** and wins over older figures above. Every partition report lists the numbers in §15.5.

### 15.1 Targets (customer menu, Lighthouse mobile)
| Metric | Target | Can it be met? |
|---|---|---|
| Server time, cached page | < 50 ms | **Yes**: a static file read by Apache/Nginx, no PHP (typically 1–5 ms). Measured *at the server* — see note A |
| Server time, uncached page | < 200 ms | **Yes** with OPcache + `php artisan optimize` + menu data cached; only the first request after a change is uncached |
| LCP on 4G (Lighthouse "mobile", 150 ms RTT, 1.6 Mbps, 4× CPU) | < 1.2 s | **Yes, tight.** Needs the largest element to be text or a small first photo (≤ ~30 KB). Measured in CI every PR |
| LCP on slow 3G | < 2.5 s | **Depends on the profile, see note B.** Repeat visits: yes |
| CLS | < 0.02 | **Yes**: sizes on every image, no late fonts or banners |
| INP | < 100 ms | **Yes**: ≤ 5 KB of plain JS |
| HTML + CSS + JS before images | < 30 KB gzip | **Yes for the demo menu and menus up to ~100 items. Larger menus: note C** |
| Lighthouse performance | 98–100 | **Yes** |
| Repeat visit | < 300 ms, also offline | **Yes** with the service worker (§15.2.8). Not on the very first visit, and iOS may clear it after weeks unused |

**A. "TTFB < 50 ms" over a phone network.** What the phone sees as TTFB includes the network trip: Lighthouse's own 4G profile adds 150 ms, and a phone in Addis Ababa reaching a server in Europe adds ~150–250 ms. No server can beat that. *Closest achievable:* < 50 ms **server time** (measured in CI and on the server with `curl`), and near-the-user delivery through Cloudflare's free CDN (§15.4), whose edge serves the static page from its cache.

**B. "LCP < 2.5 s on slow 3G".** With Chrome's "Slow 3G" preset (2 s per round trip, 400 kbps), opening a *new* HTTPS connection alone takes about 4 round trips ≈ 8 s before the first byte — physically impossible to beat on a first visit. *Closest achievable:* (1) **first visit < 2.5 s on WebPageTest "3G Slow" (400 ms round trip, 400 kbps)**, with text as the largest element; (2) **any repeat visit < 300 ms on any network**, thanks to the service worker; (3) on Chrome's "Slow 3G", text visible in the first ~9 s with nothing blocking it, reported but not gated.

**C. 30 KB for very large menus.** Text alone of ~200 items exceeds 30 KB gzipped. *Plan:* the HTML carries the first categories up to the budget; the rest arrives as a second static file right after load (also cached by the service worker), and search covers everything once it is there. So the **first download stays ≤ 30 KB for any menu**; total size grows only with the menu itself.

### 15.2 Customer menu — how
1. **Static full-page cache.** Each menu (`tenant · branch · locale`) is rendered to `public/menu-cache/…/index.html` plus pre-compressed `.gz` and `.br` copies. `.htaccess`/Nginx serve the file if it exists, otherwise Laravel renders and writes it. Pre-compressing means Brotli works even where the host has no Brotli module. Rewritten on every change (items, prices, sold out, appearance, hours, promotions) and by the daily cron for subscription state. Works on shared hosting.
2. **No framework.** Critical CSS inlined; ≤ 5 KB plain JS with `defer`, only for sticky tabs, search/filters, item sheet, "Open now" and the menu by time (computed in the browser, so cached pages are never wrong).
3. **Fonts.** System fonts by default. Custom fonts: self-hosted, subset WOFF2, preloaded, `font-display: swap`; Amharic/Arabic fonts load only when that language is shown.
4. **Images.** Done at upload (Partition 3c, tuned in this PR): 320/640/1280 WebP (q72), AVIF when GD supports it (many shared hosts don't; WebP is always made), 640 JPEG fallback (q75), metadata stripped, a < 300-byte blurred inline placeholder and the dominant colour. In the page: `srcset`/`sizes`, width + height, first visible images eager with `fetchpriority="high"`, all others `loading="lazy" decoding="async"`.
5. **Text and prices first.** Photos never block showing the menu.
6. **Caching headers.** Hashed assets and photos: `Cache-Control: public, max-age=31536000, immutable` (in `.htaccess` and Nginx since this PR; photo URLs change on every upload). Menu HTML: short cache + `ETag`/`Last-Modified` → 304.
7. **Compression.** gzip/Brotli via `.htaccess`/Nginx (this PR) plus the pre-compressed static menu files.
8. **Service worker.** Stale-while-revalidate for the menu HTML, cache-first for images and assets; `sw.js` itself `no-cache`.
9. **No third parties.** No external fonts, analytics or map embeds. The view/scan beacon is sent with `sendBeacon` after `load`, without cookies.
10. **Prefetch.** When an item scrolls into view, its detail-size photo is prefetched so the sheet opens instantly.
11. **QR links.** `/q/{code}` is answered by a tiny PHP file that reads a pre-generated map (no Laravel, no database) and redirects in one step; the scan is counted by the menu's beacon.

### 15.3 Admin and Super Admin
- Route-level code splitting (already on); rare screens and heavy widgets (command palette, appearance drawer, "no restaurant" screen, other UI languages) load on first use (this PR).
- **First load budget: 225 KB gzipped** (owner chose option A of §15.6; the original 150 KB target needs a different UI stack). Now **221 KB**; CI fails above 225 KB (`scripts/bundle-size.mjs`).
- TanStack Query caching with stale-while-revalidate; prefetch of likely next screens on hover/intent; optimistic updates for sold-out and reordering (done) and price edits (to do, with rollback).
- Skeletons, no spinners for page loads; no layout shift. Photo upload: instant local preview, progress, the UI stays usable.

### 15.4 Server and database
- Indexes on every lookup/filter column; composite indexes start with `tenant_id` (§4).
- No N+1: Eloquent strict mode fails tests on lazy loading, and **query-count tests** fail if a page's query count grows with the menu size (menus, editor tree, add-ons, tags, export — this PR).
- Public menu data in 1–3 queries, cached per restaurant, cleared on change.
- Production: `php artisan optimize`; OPcache settings for shared hosting and VPS documented in Partition 13.
- Cloudflare free plan (documented in Partition 13): CDN, cache rules for the static menu pages with a short edge time, and an optional API token so changes purge the edge copy at once.

### 15.5 Enforcement and reporting
- **CI fails the PR** when: the demo menu's HTML + CSS + JS > 30 KB gzip; Lighthouse CI (mobile, throttled; **median of 5 runs**) is over the **CI limits** below; the admin first load exceeds its ceiling; a query-count test grows. CI job `guest-menu-speed` (Partition 4): seeds the demo café, builds its static page, serves it with Nginx exactly like production (static file + `.gz`, no PHP) and runs `@lhci/cli` 0.16.0 with `ci/lighthouse/lighthouserc.json`.
- **Targets vs. CI limits (owner, 2026-11-11).** The **targets stay LCP < 1.2 s and blocking time (TBT, the lab stand-in for INP) < 100 ms**, plus performance score ≥ 98 — every PR and report states the real median against them (the job writes a table to the PR's check summary). Because CI runners vary from run to run (the same code measured TBT 21–586 ms), the check **fails only when the median of 5 runs** has **LCP > 1.3 s or TBT > 200 ms**; the score is reported, not gated. Still gated as before: accessibility ≥ 0.95, CLS ≤ 0.02, document ≤ 30 KB, at most 1 script file, no third parties. A Pest test also checks the 30 KB gzip and < 5 KB inline JS budgets.
- **Profiles:** Lighthouse mobile (4G, 4× CPU) gated in CI; "old Android" (4× CPU, slow 4G) and WebPageTest-style "3G Slow" (400 ms, 400 kbps) and Chrome "Slow 3G", first and repeat visit, reported.
- **Measured (Partition 4, demo café, 58 items, 7 photos):** HTML+CSS+JS **8.4 KB gzip** (37 KB raw; inline JS 4.7 KB raw / 1.9 KB gzip), whole first load **15 KB**; Lighthouse mobile **100 / 100 / 100 / 100** (perf, a11y, best practices, SEO), FCP 0.7 s, **LCP 0.7 s**, **CLS 0**, TBT 10–50 ms; server time ≈ 20 ms rendered by PHP, static copy served without PHP in ≈ 1 ms locally.
- **Every partition report** includes: page weight, LCP, CLS, server time cached/uncached, Lighthouse score for the customer menu, and the admin first-load size.

### 15.6 Admin first load: 150 KB is not reachable with the approved stack — **decided: A (2026-11-10)**
Measured on 2026-11-10: **285 KB → 241 KB** after this PR's changes. What remains is mostly the approved libraries themselves: React DOM ~60 KB, TanStack Router ~25 KB, Radix menus/popovers ~30 KB, i18next ~17 KB, Tailwind CSS ~16 KB, axios ~15 KB, TanStack Query ~10 KB. Even with everything optional removed the floor is about 220 KB. Options for the owner:
- **A (recommended): keep the stack, ceiling 225 KB.** Replace axios with a small `fetch` wrapper and lazy-load more widgets. Low risk. After the first visit the admin loads from the browser cache (immutable assets), so staff feel it only once per release.
- **B: swap React for Preact (compat layer).** ≈ −45 KB → ~180 KB. Real risk with Radix and TanStack internals; needs a full re-test. Big architecture change.
- **C: reach 150 KB** by dropping Radix-based components for hand-made ones and trimming the router — weeks of rework of finished screens.
The guest-facing 30 KB customer page is unaffected by this choice.

**Load on demand (owner, 2026-11-10: keep budgets; move code rather than raise limits):** screen texts (menu editor, branches, info, settings…) are `locales/en/screens.json`, loaded in parallel with the admin area (`/admin` route `beforeLoad`), not in the first download: 223.0 → **217.8 KB**. On the guest page the item details sheet is `sheet.{hash}.js` (1.3 KB), fetched after load and cached for a year by the browser and the service worker; inline script 5.0 → 4.2 KB.

**Done (A):** axios replaced by a small `fetch` client (same Idempotency-Key, XSRF and 419-retry behaviour); toasts, sign-out dialog, command palette, appearance drawer, rare screens and non-English UI texts load on first use. 285 → **221 KB**; CI ceiling 225 KB.

