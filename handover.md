# Handover

_Last updated: 2026-11-12 13:00 EAT. Everything requested is merged into `develop` (#1–#47; #31 was a temporary test PR, closed). Speed work is finished (owner, 2026-11-12): CI limits stay as they are. Next: the release PR `develop` → `main` (owner merges), then go-live with `docs/deploy.md` and `docs/final-report.md`._

> **Speed:** PLAN.md §15 still applies, but no more LCP optimising (owner, 2026-11-12). CI checks every style with and without a banner, plus style A without banner and specials; limits LCP 1.3 s / TBT 200 ms (median of 5), targets 1.2 s / 100 ms.
> **English only for now (owner, 2026-11-10):** texts stay in `t()` / `__()`; the database keeps per-language fields; language switches stay hidden while one language is on.
> **Working mode (owner):** small PRs into `develop`, CI green, tests for each change, decisions in QUESTIONS.md. Stop only for: changes outside this project, steps that can't be undone, licence doubts, the same CI failure 3 times, or the working limit.

## Where we are (2026-11-12)
- `develop` has every change; `main` still has only the bootstrap commit. The release PR `develop` → `main` carries the summary and go-live checklist; the owner merges it.
- Go-live checklist and test summary: `docs/final-report.md`. Deployment: `docs/deploy.md`. Link previews: `docs/share-preview.md`.
- Not built (owner, 2026-11-12: skipped for now): optimistic price edits in the admin (PLAN.md §15.3).

### Merged PRs
| PRs | What |
|---|---|
| #1–#15 | Plan; Partitions 1–11 (setup, auth & tenancy, menu data & editor, speed plan, settings & 2FA, customer page, appearance, branches/tables/QR, info & hours, branch size prices, promotions, insights, Super Admin) |
| #16–#20 | Menu styles A/B/C, dish photo galleries, "Viewing as" badge, SMTP settings, banner |
| #21–#25 | Demo data, deploy guide, final QA, `media:regenerate`, photo-free menus |
| #26–#30 | Share menu, PDF menu, "My order", restaurant and Super Admin dashboards |
| #32–#35 | Sales demo with 110 real (Pexels) photos, CI speed on medians, small "+" button |
| #36–#37 | Menu changes reach open phones; "Menu updated" notice |
| #38–#45 | One URL per branch; hours pill; sheet scroll lock; back to top; sticky "Add to my order"; footer; style D · Minimal; link preview picture |
| #46–#47 | LCP without a banner; CI without banner (all styles) and A without banner/specials |

### Laptop / server steps for this release
1. `git pull`; `composer install`; `php artisan migrate`; `pnpm install && pnpm build`.
2. `php artisan media:regenerate` (800 px sizes and link-preview pictures for older photos), then `php artisan menu:refresh-pages --all`.
3. Cron every minute: `php artisan schedule:run` (page refresh, mail queue, health heartbeat).
4. Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` (also used for link previews). Never run the demo seeders there.

### Workspace notes for the next session
- Screenshot/Lighthouse helpers lived in `/tmp` (not committed): Nginx on :8124 serving `public/menu-cache` (pattern must allow `/{slug}/{branch}`), `php artisan serve` on :8099, `npx lighthouse@12.8.2`.
- The Pest suite deletes `public/menu-cache` (PdfMenuTest): don't take screenshots while it runs.
- Pest is not parallel here (`--parallel` is not installed); the full run takes ~3 min.

## History (newest first)

## Nine features (2026-11-13, in progress)
- #60 Invoices and receipts (QUESTIONS #173). Live server once: `php artisan migrate --force` then `php artisan invoices:backfill`; fill Super Admin → Settings → Billing.

## Landing page (#59, 2026-11-13) — QUESTIONS #172
- `/` redesigned (Blade, no framework JS): hero with a real demo screenshot and QR code, how it works, features, 4 styles, speed, pricing from the database, optional real testimonials, FAQ, CTA, footer. Super Admin → Settings → Landing page. `robots.txt` and `sitemap.xml` are routes.
- New screenshots after a design change of the guest menu: `php artisan db:seed --class=DemoSeeder` (laptop only), `php artisan serve`, then `cd frontend; node scripts/landing-screenshots.mjs`.

## Self sign-up (#52–#58, 2026-11-13) — QUESTIONS #162–#171
- #52 "Log in as owner" in one click: no reason, no time limit, not recorded.
- #53 Backend: `POST /api/v1/signup`, e-mail confirmation (24 h signed link), Free plan (migration), `signup_state` on tenants (unverified / pending approval → public menu off), approval, rejection, `signup:prune` (7 days), anti-spam (rate limits, honeypot, optional Turnstile, blocked domains), branded e-mails with the platform logo, upgrade contact on 402.
- #54 Screens: sign-up page, "Check your inbox" gate, sign-in messages, welcome card, Super Admin sign-up settings, e-mail logo, pending approvals, upgrade toast button.
- #55 Public home, `/terms`, `/privacy`; browser check (`docs/screenshots/self-signup/`); docs.
- #56 "Log in as": 2-hour limit and safety blocks back (QUESTIONS #169).
- #58 CI: admin first-load budget 225 → 240 KB (QUESTIONS #171); customer menu budgets unchanged.
- #57 "Log in as" not recorded at all; on the 2-hour end the admin goes to /platform (QUESTIONS #170).
- Merge in order: #52 → #53 → #54 → #55 → #56 → #57 → #58 (each branch contains the one before).

## Customer-menu round (#36–#47, 2026-11-12) — QUESTIONS #149–#160
- **Live updates (#36–#37):** `public/sw.js` compares the fresh page with the saved one (ETag or text) and tells the page (`'mu'`); the page reloads when nothing is open (guard `sessionStorage.mu`, 10 s) and `sheet.js` shows "Menu updated".
- **Branches (#38):** no picker; `.sub` branch name under the h1 when there are several branches; `otherBranches` (flag `show_other_branches`, default on) in the footer; QR rows carry `branch` (main branch for restaurant codes).
- **Guest page UI (#39–#43):** `.st` pill with a dot (CSS only); `w.mL` scroll lock + swipe-to-close and back-to-top in `sheet.js`; dish sheet styles injected once (`#sst`), sticky `.sf` bar with `.oa`; footer `footer.ft#info` replaces the info `<details>` (`hoursSummary` groups days), PDF link no longer tied to branding, `.nr[hidden]` fix.
- **Style D (#44):** `minimal` in `TenantAppearance::STYLES` / `PageTheme::STYLES`, feature `style_minimal`, `style-minimal.css`; no dish/special photos; `sizes` line ("Small 60 · Large 80", `MenuPageData::number()`); flags `show_logo` / `show_banner`; demo "Nine Wells Coffee".
- **Link preview (#45):** `App\Support\PublicMenu\ShareImage` (banner og → platform `share_image` → `public/images/share-default.jpg`), `ImageProcessor::shareImage()` ≤ 300 KB, absolute APP_URL addresses with `?v=`; Super Admin upload `POST/DELETE platform/settings/share-image`.
- **LCP without banner (#46–#47):** Blade picks `$lcp` (first special, else first dish), preloads it with high priority, defers all other photos; equal-height special cards; `$lcpSizes` per style (A `44vw` → 320 px file on phones). CI matrix: style × banner/no-banner + editorial no-banner-no-specials (`DEMO_MENU_BANNER`, `DEMO_MENU_SPECIALS`).

## Super Admin dashboard (#30)
- `Platform\DashboardWidgetController` (revenue, expiring, inactive, funnel?days=30|90, top, signups, health, plan-mix, POST email/{tenant}); routes under `platform/widgets/*`; cron heartbeat `Cache::forever('cron:last_run')` and `queue:work --stop-when-empty` every minute in `routes/console.php`; indexes migration `2026_11_11_210001`.
- Frontend `features/platform/widgets.tsx` (lazy from `features/platform/dashboard.tsx`), shared `features/dashboard/widget-card.tsx` (`Widget`, `Bar`). Decisions #135–#139.

## Restaurant dashboard (#29)
- `Api\Restaurant\DashboardWidgetController` (setup, hideSetup, trend, missingPhotos, orders, plan); routes `t/{id}/widgets/*`; tenants `qr_downloaded_at`, `setup_hidden_at` (`Tenant::markQrDownloaded()` in QR downloads).
- Frontend `features/dashboard/widgets.tsx` (lazy default export; `Widget` card with loading/error/content; plain SVG bars; inline styles for one-off layout values to keep the shared CSS small).

## "My order" (#28)
- `show_order_list` (appearance, default on), feature `order_list`; off when prices hidden. Page JSON: `o` (per dish `[price, [[variantId, price]], [[min, max, [[optionId, price, soldOut]]]], soldOut]`), `cu` (currency symbol), `q` (QR code → table label), `t.o` texts (`lang/en/public.php` → `order`). Rows: `.it.ao` + `<span class="ad" data-x="A">`.
- On-demand `sheet.js`: choices + Add in `mS`; `mC` (cart: fmt/price/line/add), `mA` (quick add), `mO` (list sheet), waiter view; UI built lazily; styles injected from the file. Inline exposes `w.mQ` (scanned code) and `w.mD` (parsed page data).
- Beacon: `c` → `order_add` (subject = dish, by quantity), `w` → `order_shown` + `order_value` (sum, minor units); `Stats::hit(..., $by)`.

## Share menu (#26) and PDF menu (#27)
- Share: `show_share` (appearance, default on), button `.ib[data-x=H]`, `window.mH` in the on-demand `sheet.js`; inline `run(f, a)` calls `window['m'+f]` after loading the on-demand file. Better og tags (description, banner og image).
- PDF: `App\Support\PublicMenu\PdfMenu` (FPDF, columns, keep-together blocks), `Api\Restaurant\PdfMenuController` (`GET t/{id}/menu-pdf`, feature `pdf_menu`), guest `/{slug}[/{branch}]/menu.pdf` (`PublicMenuController::pdf`, cached in `menu-cache/{slug}[/{branch}]/menu.pdf`), `show_pdf_link` (default off). Admin dialog `features/menu/editor/pdf-menu-dialog.tsx` (inside the editor chunk: a lazy split moved shared chunks into the first load).

## Photo-free menus (feat/photo-free)
- `items.show_photo` (default true): `MenuPageData` shows no media/sheet photos when off; API + dish editor switch ("Show photo on menu", hint when off).
- Guest page: `body.NP` when no dish photo is shown; style CSS scoped to `li>.it` (specials untouched); A hairline list, B one-column cards with name/price row, `li>.it.np` card layout in B.
- Checked A/B/C at 375 px light/dark and 1280 px, photos off and mixed: no empty boxes, no overflow.
- Photos off (demo with banner): A/B/C 100, LCP 0.82–0.96 s, CLS 0, 19.3–19.5 KB loaded, HTML 10.1 KB gzip.
- Tests: backend 289, frontend 190. Admin first load 224.5 KB.

## media:regenerate (feat/media-regenerate)
- `ImageProcessor::addMissing()` + `app/Console/Commands/RegenerateMediaSizes.php` (`--dry-run`, `--tenant=`); rebuilds affected menus. Run once on the server after deploying (docs/deploy.md → Updating).

## Partition 14 — final QA (qa/final)
- All CI checks on the final branch: Pint, Larastan, Pest **279**, composer validate, ESLint, Prettier, build, Vitest **189**, admin first load **224.5 KB** / 225 KB.
- Browser smoke test (390 px and 1280 px): every admin screen as the demo owner and every platform screen as the demo Super Admin (2FA code computed) — no console errors. Fixed: Appearance scrolled sideways on phones (photo row now wraps; left column `min-w-0`).
- Guest menu (fresh demo with banner): A/B/C 100 ×4, LCP 0.89–0.90 s, CLS 0.
- Local note: the workspace `.env` has `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000`; when serving on another port, set it (or sign-in won't stick).

## Partition 13 — docs (docs/deploy)
- `docs/deploy.md` (referenced by `.htaccess`), README (demo logins, deploy link), scheduler runs the queue every minute (`routes/console.php`, `tests/Feature/ScheduleTest.php`), docker-compose vite prompts fixed.

## Partition 12 — demo data (feat/demo-data)
- `DemoSeeder::restaurant()` (look, profile, hours, Piassa branch, tables + QR codes, promotions, galleries) and `superAdmin()` (admin@example.com / password / 2FA `JBSWY3DPEHPK3PXP`, skipped in production). `tests/Feature/DemoSeederTest.php`.
- Fresh demo (Lighthouse mobile, median of 3): A/B/C all 100 ×4, LCP 0.89–0.90 s, CLS 0, HTML 11.2 KB gzip.
- Tests: backend 278.

## Change 5 — banner (feat/banner)
- `tenant_appearance.banner_text` (over|under|null). `PageTheme::STYLES` has banner ratio, default text position and `sizes` per style; `PageTheme::cover()`; `MenuPageData::srcset()`. Blade: preload link in head, `.top.hc.ov`, cover `<img>` width/height per style.
- `ImageProcessor::WIDTHS` = 320/640/800/1280; `purpose=banner` upload → WebP q64.
- CSS: overlay in base (`.ov` grid cell), per-style tweaks.
- Admin: banner crop aspect per style, recommended size hint, "Logo and name" choice, wide thumbnail (inline styles).
- Speed with a worst-case busy banner: LCP 1.11–1.26 s; normal banner 0.90–0.96 s (QUESTIONS #114, needs owner confirmation).
- Tests: backend 276, frontend 189.

## Change 4 — SMTP settings (feat/smtp-settings)
- `App\Support\MailSettings` (stored under `platform_settings.key = mail`, cached forever, password `Crypt::encryptString`), applied in `AppServiceProvider` via `afterResolving('mail.manager')`. API `GET/PUT/DELETE platform/settings/mail`, `POST platform/settings/mail/test` (throttle 6/min).
- Frontend `features/platform/email-settings.tsx` under the Settings form.
- Platform test helpers (`superAdmin`, `browser`, `p`) moved to `tests/Pest.php`.
- Tests: backend 270, frontend 188. First load 229,854 B.

## Change 3 — "Viewing as" badge (feat/viewing-as)
- `components/layout/viewing-as.tsx` (lazy, in `AdminHeader` when `/me.impersonation` is set); `impersonation-banner.tsx` removed. `/me.impersonation.tenant_name` added. Server-side logging/limits/blocks untouched.
- Admin-only texts moved from `common.json` to `screens.json` (QUESTIONS #110): first load 229,760 B.
- Tests: backend 260, frontend 185.

## Change 2 — dish photos (feat/dish-photos)
- `item_photos` (tenant_id, item_id, media_id, sort; composite FKs; backfilled from `items.media_id`). `Item::photos()`; `photo_ids` in item create/update (max 5, distinct, own media), legacy `media_id` keeps extras; janitor checks `item_photos`; copier copies photos; `ItemResource.photos`.
- Guest page: sheet data `g` = photos 2–5 (1280 px); swipe gallery in `sheet.js` + CSS (`.gw/.gl/.gc/.ga`).
- Admin: `components/photo-gallery.tsx` (take/choose, crop, progress, make main, move earlier, replace, remove), `components/crop-dialog.tsx` (shared with PhotoField), `upload.ts` (XHR with progress). Gallery uses inline styles for new layout values (shared CSS unchanged).
- Tests: backend **259**, frontend **183**. Admin first load ≤ 230,400 B.

## Change 1 — menu styles (feat/menu-styles)
- `tenant_appearance.style` (editorial|grid|bistro, default grid) replaces `layout` (list→bistro, grid→grid, large→editorial); theme presets moved too. Plan features `style_editorial/grid/bistro` (on by default) + per-restaurant overrides; `Features::styles()`; `TenantAppearance::effectiveStyle()` falls back when a style is off.
- Guest page: `resources/public-menu/base.css` + one `style-{style}.css` inlined (`InlineAssets::css($style)`); `body.S-{style}`; one shared markup (dish rows are a CSS grid, `.tx{display:contents}`), info button in the search bar, open/closed pill space reserved (no CLS), eager photos only on the first screen per style, nicer empty state, reduced-motion-safe sheet animation.
- `PageTheme::STYLES`: per-style contrast surfaces + heading font ("Style default" font keeps the style's heading font).
- Admin: Appearance → "Menu style" cards with drawings (`features/appearance/style-picker.tsx`), instant preview on style change; Appearance texts load with the screen (`locales/en/appearance.json`, `loadPageTexts`). Platform: themes use styles; plans/restaurant features list the three styles.
- CI `guest-menu-speed` is a matrix over the three styles (`DEMO_MENU_STYLE`).
- Speed (local, Nginx static copy, Lighthouse 12.8.2 mobile simulated, median of 3): A 100/100/100/100, LCP 0.73 s, CLS 0, 10.4 KB gzip HTML; B 100 ×4, LCP 0.69 s, CLS 0, 10.5 KB; C 100 ×4, LCP 0.73 s, CLS 0, 10.3 KB. Inline JS 4.2 KB. Admin first load 230,364 B of 230,400.
- Tests: backend **253**, frontend **178**.

## What Partition 11 contains (PR #15)
- Tables `payments`, `platform_settings`, `platform_announcements` (+ `announcement_reads`), `impersonations`. Plan features (`App\Support\Features`, `feature:` middleware, `features` in `/me` tenants, guest page footer/promotions).
- API `/api/v1/platform/*` (middleware `platform`: Super Admin + 2FA, never while logged in as): dashboard, restaurants (list/filter, create with owner + set-password mail, edit, suspend/unsuspend, delete/restore, features, payments, impersonate), payments + CSV, plans (limits + features), themes, announcements, activity log, settings. Restaurant side: `t/{id}/announcements` (+ read), `POST impersonation/stop`.
- "Log in as": `App\Support\ImpersonationManager` + `GuardImpersonation` (api + web groups): 60 minutes, blocked URIs, `impersonator_id` in every log entry; `/me.impersonation` drives the red banner. **Changed 2026-11-13 (QUESTIONS #162):** one click, no reason, no limit, no blocks, nothing recorded; `GuardImpersonation` removed. **Then (#56/#57, QUESTIONS #169–#170):** still no reason and nothing recorded; 2-hour limit (redirect to /platform) and the blocks are back.
- SPA `/platform` (own texts `locales/en/platform.json`, loaded by the route), one `/platform/$section` route; 2FA gate; restaurant sheet (plan, trial date, payments, features, team, log in as, suspend, delete/restore); plans, payments (CSV), themes, announcements, activity, settings, security.
- Admin: feature-aware sidebar, lazy "logged in as" banner, platform link for Super Admins without a restaurant, dashboard news (lazy).
- Tests: backend **225**, frontend **177**. Admin first load **224.9 KB** / 225 KB (QUESTIONS #97).
- Local Super Admin used in the browser check: admin@example.com / password, 2FA secret `JBSWY3DPEHPK3PXP` (only in Claude's local DB; Partition 12 adds it to the demo seeder).

## What Partition 10 contains (PR #14)
- `stats_daily` counters (one upsert per event). Beacon `POST /api/v1/beacon` `{t, b, q, i}`: menu view per page load, QR scan when the page came from `/q/CODE` (`#q=` in the address), item view from the on-demand sheet script. Bots/headless/link previews ignored; no cookies, no IPs; excluded from Sanctum stateful/CSRF (test proves it). Nightly prune of counters > 400 days.
- API `GET insights?days=7|30|90&branch_id`, `GET dashboard` (staff allowed).
- Admin `/admin/insights` (totals, SVG bar chart, top dishes, scans per code) and a real dashboard (today vs yesterday, sold-out list with "Available again", links).
- Tests: backend **215**, frontend **174**. Admin first load **221.5 KB** (dashboard is the first page and now has content). Guest inline script unchanged (item-view beacon lives in the on-demand sheet file).

## What Partition 9 contains (PR #13)
- `promotions` (special / announcement, title, text, photo, linked dish, branch, local start/end, on/off, order); API `promotions` (+ `reorder`); admin `/admin/promotions` with dialog.
- Guest page: announcement lines and special cards at the top; dates checked in the browser (`data-w`); a special with a dish opens its sheet.
- **Load on demand** (owner's rule): item sheet → `public/menu-assets/sheet.{hash}.js` (written on first page build, immutable caching in `.htaccess`/Nginx, cache-first in the service worker), fetched after load; Lighthouse CI allows 1 script file. Inline script **4.2 KB**.
- Admin screen texts → `src/locales/en/screens.json`, loaded by the `/admin` route's `beforeLoad` in parallel with the user check; tests merge it in `test-setup.ts`. Admin first load **217.8 KB**.
- Tests: backend **208**, frontend **172**.

## PR #12 — per-size branch prices (owner decision #78)
- `branch_variant_prices` (branch, size, price). Overrides API takes/returns `sizes: {variantId: price}`; sizes are checked to belong to the item. Guest page uses them (and the "from" price follows). Item lists now carry `variants` (id, name, price).
- Admin: one small price field per size in Prices & availability.
- Partition 8 (translations) stays deferred (English only, owner 2026-11-10): nothing to build; languages UI hidden while one language.

## What Partition 7 contains (PR #11)
- Tables `tenant_profiles`, `opening_hours` (breaks, past midnight; per branch or restaurant-wide), `closures`.
- API `GET/PUT profile`, `GET/PUT hours[?branch_id]` (overlap check), `GET/POST/DELETE closures`; owners and managers.
- Guest page: open/closed pill (browser-side, restaurant time zone, `hours.js`), closure notices, "Info & opening hours" section (about, week table, address + map, call/WhatsApp/Telegram/e-mail, socials, Wi-Fi when switched on).
- Inline JS minifier is now token-aware (strings kept, comments and spacing removed): page script **5.0 KB raw / 2.3 KB gzip** — at the 5 KB limit; trim before adding more guest-page JS (Partition 10 beacon: keep it tiny).
- Fixed: the controller served a stale static copy even with static pages switched off (affected tests).
- Admin `/admin/info`: about/contact/socials/Wi-Fi form, week editor (add break, copy Monday to Tue–Fri, next-day hint, per-branch hours), holiday list.
- Tests: backend **202**, frontend **170** (incl. 9 unit tests of the guest hours logic). Admin first load **222.3 KB** / 225 KB: Super Admin strings must load lazily in Partition 11.

## What Partition 6 contains (PR #10)
- Tables `branches` (every restaurant has a main branch), `dining_tables`, `qr_codes` (soft-deleted, never reused), `menu_branch`, `branch_item_overrides`.
- Guest pages per branch: `/{slug}` (main) and `/{slug}/{branch}`, each with its own static copy (`menu-cache/{slug}/{branch}/`), overrides (price for items without sizes, sold out, hidden), menus limited to branches, branch switch.
- `/q/{code}`: `public/qr.php` reads `storage/app/qr-map.php` (rebuilt after every code/branch/slug change) and redirects in one step (~1.5 ms, no Laravel, no DB) to `…#q=CODE`; Laravel route as fallback. `.htaccess` + Nginx rules added.
- QR images (chillerlan/php-qrcode 6.0.1): SVG, PNG, single-card PDF; table-card sheets (FPDF 1.9.0), 4 × A6 per A4 with cut lines. ECC Q, quiet zone 4, vector modules; scanning checked with zxing-cpp (QUESTIONS #75).
- API: `branches` (+ tables, overrides, table-cards.pdf), `tables/{id}`, `menus/{id}/branches`, `qr-codes` (+ `.svg/.png/.pdf`). Owners and managers; plan limits `branches`, `tables`.
- Admin: `/admin/branches` (cards, branch dialog incl. menus shown, tables sheet with numbered ranges and card printing, prices & availability sheet), `/admin/qr-codes` (previews, downloads, table cards per branch, extra codes).
- Tests: backend **196**, frontend **158**. Admin first load **221.5 KB** (budget 225 KB — margin is getting small; if a later partition needs room, move feature texts into lazily loaded i18n namespaces).

## What Partition 5 contains (PR #9)
- `themes` (5 presets) and `tenant_appearance` (colour, font, layout list/grid/large, show prices/photos/descriptions/calories, logo, cover, theme).
- API `GET/PUT appearance`, `GET appearance/preview` (guest page with unsaved settings, `no-store`, no service worker or beacon). Owners and managers only (`restaurant.manage`).
- `App\Support\Contrast` (WCAG maths): the guest page nudges the brand colour to ≥ 4.5:1 on white and in dark mode; the admin warns when it was too light (QUESTIONS #71–74).
- Guest page: theme colours and system font stack via CSS variables, cover (fetchpriority high) and logo, grid / large-photo layouts with matching `sizes`, the switches.
- Admin `/admin/appearance`: theme cards, colour picker + hex, font, layout, switches, logo/cover upload with crop, sticky Save/Undo bar, live phone preview (debounced; "Preview" toggle on phones).
- Tests: backend **187** (contrast unit, appearance API × roles, validation, logo replacement, preview, guest page look); frontend **155** (appearance page). Admin first load 219.5 KB.

## What Partition 4 contains (PR #8)
- **Guest page `/{slug}`** (`PublicMenuController`, `resources/views/public/menu.blade.php`): one HTML file with inline CSS (`resources/public-menu/menu.css`) and inline JS (`menu.js`, 4.7 KB raw), system fonts, light/dark, sticky search + tag filters + category chips, item sheet built from embedded JSON (sizes, add-ons, allergens, kcal, prep time), photos with WebP/AVIF `srcset`, width/height, < 300-byte placeholder, first 4 eager (first with `fetchpriority=high`), rest lazy; detail photo prefetched on hover/touch.
- **Static copy** (`App\Support\PublicMenu\MenuPageCache`): `public/menu-cache/{slug}/index.html` + `.gz`, written atomically on the first visit, deleted after the commit of any change (`Tenant::bumpCacheVersion`) and by the scheduler (`menu:refresh-pages` hourly for expired subscriptions, `--all` daily at 00:05 so "New" badges expire). `.htaccess` and `docker/nginx/default.conf` send it **without PHP**; PHP also serves it if the rewrite rules are missing. ETag + `max-age=0, must-revalidate`.
- Data in a **fixed number of queries** (`MenuPageData`, query-count test); old slugs 301 (keeping `?table=…`); suspended/unavailable → friendly 503 + noindex; unknown → 404.
- **Service worker** `public/sw.js`: stale-while-revalidate for guest pages, cache-first for `/media` (max 300 entries); offline note on the page.
- **Beacon** `POST /api/v1/beacon` after load (204 stub; counting in Partition 10).
- **DemoSeeder** "Buna & Bread" (`slug demo`, 58 items, 2 menus incl. scheduled Breakfast, sizes, add-ons, tags, allergens, 7 generated photos; login demo@example.com / password — never on a live server, QUESTIONS #69).
- **CI job `guest-menu-speed`**: demo → static page → Nginx (no PHP) → `@lhci/cli` 0.16.0, 3 mobile throttled runs, median; fails on score < 98, LCP > 1.2 s, CLS > 0.02, TBT > 100 ms, document > 30 KB, any script file or third party (`ci/lighthouse/`).
- Not in #8 (by plan): `/q/{code}` → Partition 6 (QR table); appearance/themes → 5; restaurant info + hours → 7.

## Speed numbers (§15.5) — Partition 4
| Measure | Result | Target |
|---|---|---|
| Guest page HTML+CSS+JS (demo, gzip) | **8.4 KB** (37 KB raw) | ≤ 30 KB |
| Inline JS | 4.7 KB raw / 1.9 KB gzip | < 5 KB |
| Whole first load (with 4 thumbnails) | 15 KB | – |
| Lighthouse mobile (perf / a11y / BP / SEO) | **100 / 100 / 100 / 100** | ≥ 98 |
| FCP / **LCP** | 0.7 s / **0.7 s** | LCP < 1.2 s |
| CLS | **0** | < 0.02 |
| TBT (INP stand-in) | 10–50 ms | < 100 ms |
| Server time, PHP render / static copy | ≈ 20 ms / ≈ 1 ms | < 200 / < 50 ms |
| Admin first load | 218 KB gzip | ≤ 225 KB |
Measured locally with Nginx serving the static copy (same setup as CI). Note: without gzip (`php artisan serve`) LCP rises to ~1.4 s — compression on the server matters (on by default in `.htaccess`/Nginx).

## Packages added in this build (all pinned by the lockfiles)
| Package | Version | Licence | Where |
|---|---|---|---|
| openspout/openspout | 5.3.0 | MIT | backend, XLSX import/export |
| @dnd-kit/core | 6.3.1 | MIT | admin, drag reorder |
| @dnd-kit/sortable | 10.0.0 | MIT | admin |
| @dnd-kit/utilities | 3.2.2 | MIT | admin |
| react-easy-crop | 6.2.5 | MIT | admin, photo crop |
| chillerlan/php-qrcode | 6.0.1 | MIT / Apache-2.0 | backend, QR codes |
| chillerlan/php-settings-container | 3.3.0 | MIT | dependency of php-qrcode |
| setasign/fpdf | 1.9.0 | MIT | backend, table-card PDFs |
| @lhci/cli | 0.16.0 | Apache-2.0 | CI only, `ci/lighthouse/package.json` + lockfile (puppeteer-core held at 25.16.0) |
Removed: axios. Composer constraints are `~6.0.1` / `~1.9.0` (patch updates only; `composer validate --strict` rejects exact constraints) and the lockfile pins the exact versions. They were added to `composer.lock` from the GitHub tags (Packagist is blocked in Claude's workspace). `package.json` uses caret ranges for the dnd-kit packages; the exact versions are pinned in `pnpm-lock.yaml` (`--frozen-lockfile` in CI).

## Claude's workspace notes
Workspace can run `pnpm install`, `composer install --prefer-source` (Packagist blocked: new Composer packages need the owner's PC or the lock-entry trick used for openspout), MariaDB (`service mariadb start` after idle), Chromium (`PLAYWRIGHT_BROWSERS_PATH=/tmp/pw` with a `chromium_headless_shell-1217` → `-1243` symlink), Nginx, Lighthouse (`npx lighthouse@12.8.2 --chrome-path=/opt/pw-browsers/chromium-1243/chrome-linux64/chrome`).

## Pinned references
- shadcn-admin `e16c87f` (v2.2.1, MIT); Laravel skeleton v13.11.0 `06d016a`; design mockups https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5
