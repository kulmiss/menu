# Progress — "My order" switch, website + API (19 Nov 2026)

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
- [ ] 8. CI green on the top PR

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
