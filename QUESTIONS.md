# QUESTIONS

Format: question → **decision** (who decided). Owner = repository owner. Rec = Claude's recommendation, accepted by default.
Open items are at the bottom.

## Answered at Step 1 (2026-11-10)

| # | Question | Decision |
|---|---|---|
| 1 | Product name | **Platform setting; working name "Menu".** (Rec, accepted) |
| 2 | URL structure | **One domain: `/{slug}`, `/{slug}/{branch}`, `?table=`, `/q/{code}`, `/admin`, `/platform`, `/auth`, `/api`; reserved slug list.** (Rec) |
| 3 | Custom domains per restaurant | **No.** (Rec) |
| 4 | User in several restaurants | **Yes, with a switcher.** (Rec) |
| 5 | Sign-up | **Self-service + email verification + trial; Super Admin can also create.** (Rec) |
| 6 | Router | **TanStack Router.** (Rec) |
| 7 | Admin + Super Admin apps | **One SPA, two lazy route trees.** (Rec) |
| 8 | Sanctum mode | **Cookie SPA auth, same domain.** (Rec) |
| 9 | Public menu tech | **Blade + ~5 KB vanilla JS + inlined CSS, HTML cache.** (Rec) |
| 10 | Roles | **Custom: owner/manager/staff + `can_edit_prices`, policies.** (Rec) |
| 11 | Activity log | **Custom table.** (Rec) |
| 12 | Auth flows | **Laravel Fortify (headless).** (Rec) |
| 13 | Image storage | **Local `public/media` disk, swappable to S3; 320/640/1280 WebP + JPEG; 8 MB max.** (Rec) |
| 14 | Menu languages | **English only for now** (owner, 2026-11-10; was: any number, RTL). Texts stay stored per language (JSON per field), so languages can be added later without data changes; the customer language switch and translation screens stay hidden until a restaurant has more than one language. |
| 15 | Admin UI languages | **English only for now** (owner, 2026-11-10; was: en + so/am/ar with RTL). All texts stay in `t()`; Somali/Amharic/Arabic files, fonts and right-to-left work removed. Adding a language later = one locale file; the switch appears by itself. |
| 16 | Currency | **Platform default ETB; each restaurant picks its own; no conversion.** (Owner) |
| 17 | Allergens | **Fixed EU-14 + custom tags.** (Rec) |
| 18 | Map location | **Maps link or lat/lng; "Open in Maps" link; no embedded map.** (Rec) |
| 19 | Import format | **CSV + XLSX, preview before commit.** (Rec) |
| 20 | Default plans | **Trial 14 d (Pro limits); Starter 1 br/100 items/2 lang/2 members; Pro 3/500/5/5; Business unlimited.** (Rec) |
| 21 | Over limit after downgrade | **Keep data, block new.** (Rec) |
| 22 | Plan expired | **7 days grace (full admin + banner) → admin read-only, public menu still shown for 14 more days with an owner-only "subscription expired" note → then "Menu unavailable".** (Owner) |
| 23 | "Powered by" footer | **On Trial/Starter; removal is a plan feature.** (Rec) |
| 24 | Switchable features | **Multiple menus, branches, translations, promotions, insights, import/export, custom slug, team, custom fonts, remove branding.** (Rec) |
| 25 | Email | **SMTP via `.env`.** (Rec) |
| 26 | Delete restaurant | **Soft delete, purge after 30 days, double confirm.** (Rec) |
| 27 | Activity log retention | **12 months.** (Rec) |
| 28 | 2FA | **Required for Super Admin, optional for owners.** (Rec) |
| 29 | Minimum versions | **PHP 8.3+, MySQL 8.0+ / MariaDB 10.6+.** (Rec) |
| 30 | Repo bootstrap | **One first commit on `main`, then `develop`, protect `main`.** (Owner) |
| 31 | Package installs | **Owner enables them; Step 3 starts in a new session.** (Owner) |
| 32 | Legal pages | **Simple Terms and Privacy pages; owner supplies text; clear placeholders until then.** (Owner) |

New rules added by the owner (now in PLAN.md §2): Docker is for local development only; shared hosting is the main target (no Node.js, Redis, queue workers or Docker on the server).

## Smaller decisions taken at Step 2 (Claude, changeable)

| # | Question | Decision |
|---|---|---|
| 33 | How to store translations | **JSON columns per field, custom cast, no package.** Fast public render, simple import/export. |
| 34 | Cross-tenant references | **Composite FKs `(tenant_id, parent_id)`** so the DB itself blocks cross-tenant links. |
| 35 | Add-on groups | **Reusable across items** (pivot), since "Extra toppings" repeats. |
| 36 | Item text | **`summary` (short, list) + `description` (full, sheet).** |
| 37 | Price when variants exist | Item price empty; list shows **"from" lowest variant**. |
| 38 | Restaurant-level QR with several branches | Opens the **default branch** with a branch switch. |
| 39 | Fonts for Amharic / Arabic | *Not needed while English only (#14).* When a language needs them: Noto Sans Ethiopic / Arabic via `unicode-range`, downloaded only when shown. |
| 40 | Calendar & digits | **Gregorian calendar, Western digits** in all locales (Ethiopian calendar not in scope). |
| 41 | Default time zone | **Africa/Addis_Ababa**, per restaurant. |
| 42 | Who can invite | Owner: managers + staff; Manager: staff only. One owner per restaurant. |
| 43 | Impersonation limits | 60 min; no password/email/2FA, ownership, slug or delete actions; all logged. |
| 44 | Expired subscription write response | HTTP **402** with an i18n message; reads still work. |
| 45 | Image upload memory | Browser crops and downscales to ≤ 2048 px before upload; server limit 8 MB. |
| 46 | HEIC uploads | Rejected with a clear message (GD can't read HEIC); browser-side conversion where the browser supports it. |
| 47 | Admin font list for menus | Inter, Manrope, Lora, Nunito, System. |
| 48 | Repo layout | Monorepo: `backend/` (Laravel), `frontend/` (SPA), `public-menu/` assets built into `backend/public`. |
| 49 | Demo photos | Generated or royalty-free food images only, licences listed; no people. |

## Decisions taken at Step 3 · Partition 1 (Claude, changeable)

| # | Question | Decision |
|---|---|---|
| 50 | Package versions when Claude can't reach the registries | The setup script runs `composer require` / `pnpm add` without guessed versions; the **lockfiles pin the exact versions** and are committed. |
| 51 | Test database | **MySQL `menu_test`** locally and in CI (composite FKs and JSON need MySQL, not SQLite). |
| 52 | Laravel skeleton extras | Removed the skeleton's own Vite/npm setup, CLAUDE.md/AGENTS.md, StyleCI and GitHub files; public-menu CSS/JS tooling arrives in Partition 4. |
| 53 | Social login buttons in template auth forms | Not in scope; removed in Partition 2. |
| 54 | Local PHP version | Owner's Laragon has **PHP 8.5**; CI runs **8.3** (the minimum we support) so we never depend on newer features. |
| 55 | How PRs are merged | **Stacked PRs** (owner, 2026-11-10): each new branch is built on the previous one, so the newest PR always contains all earlier ones. The owner merges **only the newest PR** with *Create a merge commit* (not squash); GitHub then marks the earlier PRs as merged automatically. PRs stay small for review because each shows only its own commits once the earlier ones are in `develop`. |

## Decisions taken in Partitions 2b–4 (Claude, changeable)

| # | Question | Decision |
|---|---|---|
| 56 | Invited person without an account | **Signs up from the invitation** (`invitation_token`): no restaurant is created, they join the inviting one, and the email counts as verified. |
| 57 | Image processing library | **Plain GD, no package** (PLAN §3.1 named Intervention Image). Resize, orientation fix, WebP + JPEG and EXIF stripping are a few GD calls; Composer can't reach Packagist from Claude's workspace anyway. Imagick support can be added later if wanted. |
| 58 | Items without a price | **Allowed** (e.g. "ask the waiter"); the public page shows no price. With sizes, the item price is empty and the list shows "from" the lowest size (#37). |
| 59 | Menu ↔ branch links (`menu_branch`) | Built with branches in **Partition 6**; until then every menu is shown at every branch. |
| 60 | Plan feature switches (#24) | **Counts** (menus, items, photos…) are enforced from Partition 3 via `plans.limits` (402 on create). On/off **features** (`plan_features`, overrides) arrive with the Super Admin in Partition 11. |
| 61 | Who may bulk-change prices | Anyone allowed to edit prices: owners, managers and staff with *can edit prices*. Staff use a price-only endpoint; full item edits stay owner/manager. |
| 62 | XLSX library for import/export | **openspout/openspout `^5.3`** (MIT, streaming, low memory) — owner's choice, 2026-11-10. 5.3 is the last line that runs on PHP 8.3; `config.platform.php = 8.3.0` keeps `composer update` from moving past it. Needs ext-zip, xmlreader, dom (present in CI and the Docker image; standard on cPanel). |
| 63 | Import rules | One row per item; matched by menu + category + item name in the main language; **blank cell = keep current value**; sizes as `Small=60; Large=80`; unknown tags become custom tags; any row error blocks the whole import (fix the file, upload again). Preview kept 30 min. |
| 65 | Admin first-load budget (PLAN §15.6) | **A: keep the stack, 225 KB ceiling** (owner, 2026-11-10). Now 221 KB after replacing axios with `fetch` and lazy-loading rare UI. |
| 64 | Speed targets (owner, 2026-11-10) | **PLAN.md §15 is the top priority.** Targets that physics or the approved stack rule out are stated there with the closest achievable target (server time vs. network TTFB, "Slow 3G" first visit, 30 KB for very large menus, admin 150 KB). |
| 66 | When the static guest page is rebuilt | **Deleted on every change (after the database commit) and rebuilt by the next visitor**, not rebuilt at once in the background: no queue worker is needed on shared hosting, and the first visitor after a change still gets a ~20 ms PHP render. Hourly cron removes pages of restaurants whose subscription ran out; a daily cron at 00:05 removes all pages so "New" badges expire. Easy to change: add a rebuild job later. |
| 67 | Menus served at set times (e.g. Breakfast) on the guest page | **Shown with a "Not served right now" label**, not hidden: the page is one static file for all hours, and hiding sections after load would cause layout shift. Easy to change. |
| 68 | INP target in CI | Lighthouse lab runs can't measure INP (it needs real taps), so CI gates **TBT ≤ 100 ms** as the stand-in; real INP comes from the beacon later (Partition 10). |
| 69 | Demo login | `DemoSeeder` creates **demo@example.com / password** for the demo café. It is only run by hand (`php artisan db:seed --class=DemoSeeder`) and in CI; **never run it on a live server**, or change the password right after. |
| 70 | Lighthouse CI package | **`@lhci/cli` 0.16.0 (Apache-2.0)**, CI only, in its own `ci/lighthouse/package.json` + lockfile (`npm ci`), not in the app. `puppeteer-core` is held at 25.16.0 because 25.17.0 points at a broken npm upload. |
| 71 | Menu fonts | **System font stacks only** (Modern, Classic serif, Rounded): no font files to download, so the 30 KB budget and LCP hold (§15.2.3). A web font could be added later as an opt-in with a size warning. |
| 72 | Brand colours that are hard to read | The owner may pick any colour. The guest page **automatically uses the nearest darker shade on white and a lighter shade in dark mode** so text stays ≥ 4.5:1; the admin shows a warning only for the light page. |
| 73 | Live preview | The preview frame loads the real guest page with the unsaved settings in the address (`GET appearance/preview`), never cached or counted; saving clears the static page so guests see it at once. |
| 74 | Theme presets | Five starting looks (Classic, Bistro, Fresh, Espresso, Berry) seeded by the migration; Super Admin manages them in Partition 11. Picking one fills colour, font and layout; the owner can still change each. |
| 75 | QR and PDF libraries | **Owner, 2026-11-10: chillerlan/php-qrcode 6.0.1** (MIT/Apache-2.0) for QR codes. For PDFs Claude chose **setasign/fpdf 1.9.0** (the original FPDF: MIT, pure PHP, one file, core fonts built in, no external binaries, used since 2001). QR settings for reliable printing: plain `https://…/q/CODE` link, error correction **Q** (25 %), full 4-module quiet zone, black on white, vector squares in PDFs, ≥ 70 mm on table cards. Checked: 400 random codes decode with zxing-cpp; 180/180 distorted reads (tilt, blur, dim light, noise, low resolution) of printed-card renders. |
| 76 | Table / scan in the guest page address | A QR code redirects to the branch page with `#q=CODE`. The part after `#` never reaches the server, so the **static page is still served without PHP**; the page reads it for the visit beacon (Partition 10) and removes it from the address so shared links don't count as scans. |
| 77 | QR code format | 8 characters from `23456789ABCDEFGHJKMNPQRSTUVWXYZ` (no 0/O, 1/I/L): easy to read aloud and type from the small text under each code. Codes are soft-deleted, never reused. Each restaurant gets one code for the whole restaurant; each new branch and each table gets its own. Extra codes (window, flyer) can be added and deleted. |
| 78 | Branch price for items with sizes | **Owner, 2026-11-10: allowed — a price per size per branch.** Table `branch_variant_prices` (branch, size, price; no row = main price). The Prices & availability sheet shows one small price field per size; the guest page's "from" price follows the branch prices. |
| 79 | Branch addresses | Main branch = `/{restaurant}`; other branches = `/{restaurant}/{branch}` with their own static page; a switch between branches shows on the guest page when there is more than one active branch. A restaurant-level QR code opens the main branch (#38). |
| 80 | Open / closed on the guest page | **Confirmed by the owner.** Worked out **in the browser** from the hours and closures embedded in the static page, in the restaurant's time zone (PLAN §2). Shows "Open now · until 22:00", "Closed · opens tomorrow 08:00", "Closed · opens Sun 08:00" or "Closed today". The logic lives in `backend/resources/public-menu/hours.js` and is unit-tested by Vitest. Known limit: the "opens …" hint doesn't skip future holidays (it does show "Closed today" on them). |
| 81 | Branch info | A branch's own address, map link and phone replace the restaurant's on that branch's page; WhatsApp, Telegram, e-mail, social links and Wi-Fi are shared. Hours: a branch's own week if set, otherwise the restaurant's. |
| 82 | Wi-Fi on the menu | **Confirmed by the owner.** Off by default; the owner switches it on. The password is shown as text (tap-and-hold to copy) — no copy button, to keep the page script under 5 KB. |
| 83 | Guest page script budget (owner: keep budgets, load on demand) | The **item details sheet** moved out of the inline script into `/menu-assets/sheet.{hash}.js` (1.3 KB, 0.7 KB gzip), fetched in the background right after the page loads and kept by the service worker, so it is normally ready at the first tap; if someone taps before it arrives, the sheet opens as soon as it loads. Lighthouse CI now allows **1 script file** (was 0) for this. Inline script: 4.2 KB. |
| 84 | Admin first-load budget | Screen texts load with the admin area instead of in the first download (see PLAN §15.6). 217.8 KB of 225 KB. |
| 85 | Promotions | Two kinds: **special** (title, text, optional photo and linked dish — tapping it opens the dish) and **announcement** (one line). Optional start/end in the restaurant's local time; the page shows each only inside its dates (checked in the browser), optional branch, on/off, order. No automatic "Special" badge on menu items (the card itself is the highlight). |
| 86 | Guest page database queries | A page build runs a **fixed 17 queries** whatever the menu size (test ceiling 20). Builds only happen when the static copy is missing, so visits never run them. |
| 87 | What Insights counts | **Confirmed by the owner (no category views).** **Menu views** (one per page load), **QR scans** (a view that arrived through `/q/CODE`, per code — so per table/poster) and **dish details opened** (per dish). **Category views are not counted**: it would need scroll tracking in the 5 KB page script. Days are the restaurant's local days. Counters older than 400 days are deleted nightly. |
| 88 | Who is not counted | Requests without a browser user agent and known bots, link previews (WhatsApp, Telegram, Facebook), headless browsers and speed tests (Lighthouse, PageSpeed). The admin's live preview is never counted. No cookies, no IP addresses stored. |
| 89 | Beacon and the admin login | The beacon comes from the same site as the admin, so it is excluded from Sanctum's "stateful" handling (no session, no CSRF token) — otherwise every count would be refused. Covered by a test. |
| 90 | Dashboard | Today's menu views and QR scans (with yesterday), the sold-out list with "Available again" (staff can use it too), links to Insights and QR codes. The chart in Insights is plain SVG (no chart library) to keep the admin's first load within budget. |
| 91 | Plan features | Five switches: **more branches, promotions, insights, import/export, menu without our name** ("Menu by …" footer). A plan without a setting uses the default (all on except "without our name"), so existing restaurants keep everything. The Super Admin can force a feature on/off per restaurant. A switched-off feature hides its admin menu item and answers 402 on its API; existing data stays. |
| 92 | "Log in as" | Opens the restaurant admin **as the owner**, for at most **60 minutes**, with a red banner and "Back to platform". Everything done is logged with the Super Admin as `impersonator_id`. Blocked while logged in as: password, e-mail/profile, two-factor, **any team member change**, the menu address (slug). Needs a reason (kept in `impersonations` and the activity log). |
| 93 | New restaurant from the platform | The Super Admin enters restaurant name, address (slug), owner name and e-mail. A **new owner gets a "choose your password" e-mail**; an existing user is added as owner. Default plan: the "trial" plan with its trial length (changeable). |
| 94 | Payments | Recorded by hand (cash, bank, mobile money, other) with a number of months; the new period **starts where the paid time ends** (or today if it already ran out) and moves `paid_until`. CSV export (UTF-8, opens in Excel). No online payment in this build. |
| 95 | Super Admin access | `is_super_admin` users **with two-factor sign-in**; without it the platform shows only "set up two-factor" with the security page. Super Admin accounts are created on the server (`php artisan tinker` / demo seeder), not from the UI. |
| 96 | Deleted restaurants | Soft delete: the menu and QR codes stop at once; the platform can restore for **30 days**; a nightly job removes them for good after that. |
| 97 | Admin first-load budget | 224.9 KB of 225 KB after Partition 11. Kept within budget by loading on demand: platform screens (one `/platform/$section` route), the "logged in as" banner, the language switch (hidden while English only) and dashboard news. Remaining partitions add no admin code. |

| 98 | Menu styles (owner, 2026-11-11: build all three) | Change list item 1. The old layout switch is replaced by **three menu styles**: A · Warm editorial (`editorial`), B · Fresh grid (`grid`, default for new restaurants), C · Quiet bistro (`bistro`). Existing restaurants were moved list → C, grid → B, large photos → A (migration, reversible). Each page inlines `base.css` plus **only its own** `style-*.css`. Styles are plan features (`style_editorial`, `style_grid`, `style_bistro`, all on by default) with per-restaurant overrides; if a saved style is switched off, the page shows B (or the first allowed). Easy to change: one CSS file per style. |
| 99 | Fonts in the three styles | Options: the font setting replaces every font, or styles keep their own heading font. **Chosen:** "Style default" (was "Modern") keeps the style's heading font (serif for A and C, sans for B) with the system font for text; "Classic serif" or "Rounded" is used for headings **and** text in any style. No web fonts (speed budget). Easy to change in `PageTheme::STYLES`. |
| 100 | Brand colour contrast per style | The brand colour is checked against each style's own least-contrasting surface in light and dark mode (e.g. A's cream cards), not just white, so it always reaches 4.5:1. A test keeps `PageTheme::STYLES` in step with the stylesheets. |
| 101 | Photos that load at once | Only the photos likely on the first screen load immediately: A: first 2 dishes (1 with a banner), B: first 4 (2), C: first 5 (3); the first gets `fetchpriority=high`; the rest load lazily. Before, the first 4 photos loaded at once even when they were far down the page, which slowed the first paint under slow 4G. |
| 102 | Open/closed pill | Its space is kept from the start (invisible until the script fills it), so it no longer moves the page (style C measured CLS 0.06 before the fix, 0 after). |
| 103 | CI speed check per style | The `guest-menu-speed` job now runs once per style (matrix), so the checks are named `guest-menu-speed (editorial|grid|bistro)`. If you add branch protection, require all three. |
| 104 | Appearance texts | The Appearance screen's texts load when the screen opens (`locales/en/appearance.json`), not with the admin area. Admin first load: 230,364 bytes of 230,400 (225 KB); the style picker's drawings use plain SVG colours so they add no shared CSS. |

## Open

- Owner action: protect `main` in GitHub settings (require PR, no force push, no deletion). Claude's access can't change branch settings.
- PR #1 is still open: merge it into `develop`.
- MySQL must be running in Laragon for `setup-local.bat`.
