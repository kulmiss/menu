# Progress — layouts, new styles R–T, new Appearance page (21 Nov 2026)

📍 Now: PR 2 (styles R–T with demos) into develop, CI running
📊 Progress: ▓▓▓▓░░░░░░ 40%
✅ Just finished: styles R · Burger & Pizza, S · Injera & Tibs, T · Juice & Drinks; demos /demo-bigbite (43 dishes), /demo-shekla (12, short on purpose), /demo-spris (36), a real photo on every dish (QUESTIONS #243)
⏭️ Next: layout CSS per style; then the new Appearance page
⚠️ Waiting for the owner: nothing (merges later)
⏱️ Time left: several PRs

## Steps
- [x] 1. Layout setting, plan feature, shared CSS, preview light/dark (QUESTIONS #242)
- [x] 2. New styles R, S, T with demo menus and photos (QUESTIONS #243)
- [ ] 3. Layout CSS fine-tuned per style (A–G, H–L, M–T) with phone screenshots
- [ ] 4. New Appearance page: style, layout, colours & font, logo & banner, what guests see; light/dark phone preview
- [ ] 5. Grid · List · Text switch on the demo bar and /phone/ page; help article

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
