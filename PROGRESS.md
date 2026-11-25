# Progress — Owner status e-mails (25 Nov 2026)

📍 Now: PR feat/status-emails (off develop), CI running

- The owner wanted an automatic e-mail when a restaurant is blocked or suspended. Added `RestaurantStatusNotification` (queued) sent to every owner from all four Super-Admin actions (QUESTIONS #269): blocked / suspended carry the reason; unblocked / unsuspended are a short "active again" note with an admin link. Texts in `lang/en/restaurant_status.php`; `Tenant::ownerUsers()` helper added.
- Tests: PlatformTest asserts the right notification + reason on each of the four actions (Notification::fake). Pint, PHPStan clean; all four subjects render.

# Earlier: Feature search (25 Nov 2026)

📍 Now: PR feat/feature-search (off develop), CI running

- Settings → Features now has a search box at the top that filters the switches by label or key and hides empty groups, with a "no matches" line (QUESTIONS #268). Client-side only; the on/off summary is unchanged. Test added to feature-switches.test.

# Earlier: Block action (25 Nov 2026) — merged as #190

- The owner said suspend wasn't working well and asked for two buttons. Suspend stays as-is; added a separate **Block** (QUESTIONS #267).
- Block: Super Admin Block / Unblock button with a reason; the owner can still sign in but the admin is replaced by a full-screen card with the reason (`RestaurantBlocked`); writes are refused (402); the public menu returns **404** (not suspend's 503).
- Backend: `status = 'blocked'` + `blocked_at` / `blocked_reason` migration, `Tenant::isBlocked()`, `block()` / `unblock()` + routes, `TenantResource` exposes `blocked` + reason, `PublicMenuController` 404s blocked, cache cleared + activity logged. Internally maps to the suspended state for write-gating so suspend is untouched.
- Tests: backend PlatformTest (block → owner card payload, guest 404, write 402, unblock); frontend card renders the reason + sign-out.

# Earlier: Combined release: switches + sign-up panel (25 Nov 2026) — merged as #189

📍 branch release/switches-and-signup — one PR carrying extra codes (#186), branch limit + Team (#187), the sign-up panel (#188) and the web-auth/webauthn-lib security bump, so the owner merges once.

- **Extra QR codes (QUESTIONS #262).** New feature switch `extra_codes` (Settings → Features, global + plan + restaurant), on by default. Off: the "Extra code" box on the QR codes page is hidden, the create API answers 402, and existing extra codes are hidden from the list (owner's option 2); printed codes keep opening the menu.
- **Security:** bumped `web-auth/webauthn-lib` to clear the advisory that was failing the backend `composer audit` step on every PR.

# Earlier: Branch limit + Team ON/OFF switch (24 Nov 2026)

- **Branch limit (QUESTIONS #263).** The tenant payload now carries `branch_limit` (the plan's `branches` limit; null = unlimited). The Branches page hides "+ New branch" once the restaurant is at that limit, and the side menu drops the **Branches** item entirely on a single-branch plan (`branch_limit === 1`) — the address/map/phone are already edited in Restaurant info, and the guest menu for a one-branch restaurant already shows that profile address.
- **Team switch (QUESTIONS #264).** New feature `team` (Settings → Features, plans, restaurants), on by default. Off: the Team menu item is hidden and the team/invitation API answers 402 `features.team`; members already on the restaurant keep working, and a pending invitation can still be accepted.
- New sidebar mechanics: `hideWhenOff` (remove, don't badge, when a Super-Admin switch is off) and `hideWhenSingleBranch` (remove on a one-branch plan).
- Tests: backend `TeamSwitchTest` (4), frontend branches "+ New branch" at/under limit and sidebar Team/Branches hidden. Pint, PHPStan, tsc, eslint, prettier all clean; API reference regenerated.

# Earlier: Sign-up / sign-in left side (25 Nov 2026)

- The owner said the sign-up left side looked ugly. It did: the benefits and demo phone floated loose in the gap and the phone was cut off. Showed three mockups; the owner chose the unified panel.
- Fix (QUESTIONS #265): heading + three benefit lines + the full phone now sit in one soft tinted panel (`.aside-card`). CSS/markup only in `account/layout.blade.php`, so it also covers sign-in, forgot/reset and invite pages; the Super Admin `$plain` pages (no aside) are untouched.
- Verified by rendering the real page with Playwright at 1280 px (light + dark) and 430 px: panel reads as one block, mobile still shows the form first with the phone hidden. `AccountPagesTest` asserts the panel markup; full file green, Pint clean.

# Earlier: Tables ON/OFF switch (22 Nov 2026)

- New feature switch `tables` (Settings → Features, plans, restaurants), on by default. Off: no Tables / table cards / table codes in the admin, no table name on "Show to waiter", help article hidden; printed table codes keep opening the menu (QUESTIONS #261).

# Earlier: Landing 2 demo video (22 Nov 2026)

- The 30-second demo video, recorded from the real app (`frontend/scripts/landing2-video.mjs`): sign up → add a dish → pick a style → print the QR → a guest scans it. 801 KB, 720p H.264, captions EN + AM, built into Landing 2 (QUESTIONS #258). Loads only on ▶.
- CI browser check: the video tap now tested (the poster is centred first, so the phone's sticky bar doesn't cover it).

# Earlier: Landing 2 look & feel upgrade, English + Amharic (22 Nov 2026)

- English + Amharic: /landing2 and /am/landing2 (/ and /am once it is home), hreflang + canonical, a switch in the header and footer that keeps the section, Amharic font subset (2 × ~16 KB). Amharic is a draft: docs/landing2-amharic-review.csv, `php artisan landing2:amharic --import=… [--dry-run]` (QUESTIONS #253).
- Super Admin → Landing 2: 22 ON/OFF switches, every text in EN/AM, restaurants, payment methods, numbers, demo video, footer in Amharic (#254–#258).
- The 13 additions: style switcher, price animation, languages, real restaurants (≥ 3 with permission), payment methods (text, no logos), Monthly/Yearly, light bands, blurred hero photo, video player (video not recorded yet), mesob/jebena pattern, QR poster preview, button lift + fade-in, real numbers (#259).
- Speed: first load 394 KB → 55 KB (EN) / 89 KB (AM); Lighthouse 100/100/100/100 both. CI: Lighthouse for both languages with a 150 KB budget, and landing2-checks.mjs (20 cases: axe, CSP, console, layout shift, width, lazy loading, taps).
- Also: Permissions-Policy without the retired "interest-cohort" (#260).
- Report: docs/landing2-upgrade-2026-11-22.md.

# Earlier: admin improvements and Landing 2 (22 Nov 2026)

📍 Now: one PR into develop (feat/admin-landing2), CI running
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 95%
✅ Just finished: collapsible Appearance sections, legal texts closed, theme phone preview, dashboard numbers/charts, Landing 2 with the home page switch
⏭️ Next: CI green → owner merges → deploy → `php artisan platform:aggregate` on the server (fills the new dashboard numbers)
⚠️ Waiting for the owner: merge; push the backup tag `landing-v1-2026-11-22` (blocked from here)
⏱️ Time left: CI only

## Steps
- [x] 1. Collapsible sections in Appearance (QUESTIONS #247)
- [x] 2. Legal texts closed in Settings; saving never touches Terms (QUESTIONS #248)
- [x] 3. Phone preview on Super Admin → Themes (QUESTIONS #249)
- [x] 4. Dashboard: demo data, compare, more numbers and charts, info, CSV (QUESTIONS #250)
- [x] 5. Landing 2, backup of Landing 1, home page switch, Lighthouse CI (QUESTIONS #251)

# Earlier: layouts, new styles R–T, new Appearance page (21 Nov 2026)

📍 Now: PR 5 (demo layout switch) into develop, CI running — all 5 steps built
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 95%
✅ Just finished: styles R · Burger & Pizza, S · Injera & Tibs, T · Juice & Drinks; demos /demo-bigbite (43 dishes), /demo-shekla (12, short on purpose), /demo-spris (36), a real photo on every dish (QUESTIONS #243)
⏭️ Next: CI green on #172–#176 → owner merges in order → deploy → `php artisan demo:live` on the server
⚠️ Waiting for the owner: merge #172 → #173 → #174 → #175 → #176 (each includes the one before)
⏱️ Time left: CI only

## Steps
- [x] 1. Layout setting, plan feature, shared CSS, preview light/dark (QUESTIONS #242)
- [x] 2. New styles R, S, T with demo menus and photos (QUESTIONS #243)
- [x] 3. Layouts fine-tuned for all 20 styles, new browser test (QUESTIONS #244)
- [x] 4. New Appearance page: 5 steps, layout picker, light/dark phone preview (QUESTIONS #245)
- [x] 5. Grid · List · Text switch on the demo bar and /phone/ page; help (QUESTIONS #246)

# Earlier: long demo menus with photos (21 Nov 2026)

📍 Now: PR into develop, CI running
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 90%
✅ Just finished: 35–42 dishes per style demo, a real photo on every dish (not style D), 176 new photos, `demo:live --refresh`
⏭️ Next: CI green → owner merges → release → on the server: Deploy, then `php artisan demo:live --refresh`
⚠️ Waiting for the owner: the merge
⏱️ Time left: CI only

## Steps
- [x] 1. Find photos for the missing cuisines (Ethiopian, Somali, Arabic, Indian/halal, seafood, drinks, fine dining, bowls, desserts) and look at every one
- [x] 2. Add them to photos.json, download as WebP, CREDITS.md
- [x] 3. Long menus for the 16 photo demos; style D without photos
- [x] 4. `demo:live --refresh` (demo restaurants only, photo files removed)
- [x] 5. Tests (fail on develop, pass now), PHPStan, Pint, demo tests
- [x] 6. Real browser: 5 demos in Chrome at 390 px, every image loads
- [ ] 7. CI green

# Earlier: guest menu footer, demo view bar, styles slider (19 Nov 2026)

📍 Now: done — CI running on the top PR
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 90%
✅ Just finished: footer social links (space, centred, every style), right-to-left numbers fixed, Phone / Desktop bar on demo menus with a phone-frame page, "More menu styles" slider
⏭️ Next: CI green, then the owner merges the top PR
⚠️ Waiting for the owner: merge the top PR (it includes #161 and #160)
⏱️ Time left: CI only

## Steps
- [x] 1. Footer: social links with space above, centred, "Follow us"; PDF link centred; test for all 17 styles × widths × directions
- [x] 2. Right-to-left: phone numbers and opening hours read left to right (found by the new test)
- [x] 3. Demo menus: Phone / Desktop bar from tablet width; /phone/{slug} phone frame with QR code
- [x] 4. Landing "More menu styles": arrows, soft edges, progress line, no scroll bar
- [x] 5. Backend 1143, frontend 1233+ tests; lint, types, build; real browser; Lighthouse
- [ ] 6. CI green on the top PR

---

# Earlier: "My order" switch, website + API (19 Nov 2026)

📍 Now: done — CI running on the top PR
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 90%
✅ Just finished: switches (platform, plan, restaurant), guest page without any My order code when off, Super Admin impact/column/filter, logs, help, API field, tests (backend 1134 + new, frontend 1078 + new), browser runs, Lighthouse on/off
⏭️ Next: CI green, then the owner merges the top PR
⚠️ Waiting for the owner: merge the top PR (it includes #160)
⏱️ Time left: CI only

## Steps
- [x] 1. Check what existed (feature `order_list`, `show_order_list`), plan the edge cases
- [x] 2. One rule (`MyOrder::state`) for the guest page, Appearance, `me`, widgets, beacon, Super Admin
- [x] 3. Guest page off: no "+", no order.js / order.css / data / texts; saved list cleared
- [x] 4. Owner switch with reasons; Super Admin impact, column, filter; queued rebuild; logs old → new
- [x] 5. Help article, e-mail text, API (new fields only)
- [x] 6. Tests: 8 combinations, 17 styles on/off, layout off (all styles × widths × RTL), cache, roles, logs
- [x] 7. Real browser: 5 styles × 5 widths on/off, service worker with a saved list, owner and Super Admin screens; Lighthouse
- [x] 8. CI green on the top PR (#161, 48/48)

---

# Earlier: overnight job: menu styles M–Q + go-live pack (19 Nov 2026)

📍 Now: final checks and the report
📊 Progress: ▓▓▓▓▓▓▓▓▓░ 90%
✅ Just finished: styles M–Q (web, app, demos, help, landing), menu:doctor, /health, alerts, VPS script tested from zero, backup → restore test, sales sheet, "Like my menu" print design, "Add my menu for me", welcome e-mails, launch texts, Play screenshots
⏭️ Next: full test runs, CI green, the report
⚠️ Waiting for the owner: LAUNCH_TODO.md, then merge the top PR
⏱️ Time left: about 1 hour

## Steps (overnight job)
- [x] 1. 8 concepts, the best 5 picked (QUESTIONS #232)
- [x] 2. Styles M–Q: original SVG art, category icons, decoration switch, category photos, plans
- [x] 3. Every feature in every style; speed (LCP ≤ 1.2 s, CLS 0, Lighthouse ≥ 95)
- [x] 4. Picker, demos, help, app, landing "More menu styles"
- [x] 5. Tests: backend, frontend layout/contrast/header, Chrome + Firefox sweep, slow 3G, offline, cache, switching
- [x] 6. Go-live: menu:doctor, /health, super:create, alerts, VPS install/update/rollback (tested from zero), restore test (QUESTIONS #233)
- [x] 7. Launch materials: sales sheet, "Like my menu" print design, texts, screenshots (#234)
- [x] 8. First customers: "Add my menu for me", welcome e-mails, journey check (#235)
- [ ] 9. Full re-run, CI green, one top PR, report

---

# Earlier: menu styles H–L and the Super Admin footer (round of 18 Nov 2026)

📍 Now: done — waiting for the owner to merge
📊 Progress: ▓▓▓▓▓▓▓▓▓▓ 100%
✅ Just finished: styles H–L, website footer + Super Admin editor, full re-test (A–L Chromium 372/372, Firefox 36/36; footer 112/112 and 54/54), CI green (34/34)
⏭️ Next: owner merges website #159 and app #39
⚠️ Waiting for the owner: the merge
⏱️ Time left: none

## Steps
- [x] 1. Plan and the concepts image for H–L
- [x] 2. Style plumbing: 12 styles, options (direction, Halal, Ramadan mode, greyscale, accent), plans (Plus and up, off on Free)
- [x] 3. H · Arabic: CSS, Noto Kufi Arabic (OFL, subset), right-to-left
- [x] 4. I · Islamic, J · Somali, K · Black & White, L · Colourful
- [x] 5. Admin picker and options, help articles and screenshots, demos, app style list
- [x] 6. Footer: content, draft / publish / history, shown on every public page (not guest menus)
- [x] 7. Footer editor in Super Admin (Content → Footer)
- [x] 8. Tests, real-browser runs, Lighthouse; fix every bug; clean re-run of A–L and the footer
- [x] 9. One top PR into develop and the report with screenshots
