# Progress — menu styles E, F, G (round of 18 Nov 2026)

📍 Now: Step 8 of 10 — Real-browser runs (every feature × style × width)
📊 Progress: ▓▓▓▓▓▓▓░░░ 70% done / 30% left
✅ Just finished: automated tests for E/F/G (features, photo sizes, dark palette, prices on one line, 500 dishes, empty menu, CSP); page-size test now covers all 7 styles
⏭️ Next: cache/offline, style switching A→E→F→D, slow 3G, happy hour, Firefox
⚠️ Waiting for the owner: nothing
⏱️ Time left (estimate): about 2–3 hours

## Steps
- [x] 1. One merge per round: top PR holds the stack; workflow closes included PRs (website #156, app #37)
- [x] 2. Style plumbing: one list of styles (A–G), theme surfaces, feature switches (Plus and up, off on Free, editable per plan / restaurant / globally)
- [x] 3. Guest-menu CSS: E · Mesob, F · Night Lounge (prices never wrap), G · Street Board — with and without photos
- [x] 4. Admin: style picker cards, "Preview on a computer", Super Admin switches, themes, restaurant filter
- [x] 5. Demo restaurants per style, landing "More looks" links, help articles, CI speed matrix
- [x] 6. Flutter app: style picker shows E/F/G (it had a fixed list of four: fixed, with a test)
- [x] 7. Automated tests: feature, screenshot (phone + desktop, photos / photo-free, light / dark), CSP, Lighthouse CI
- [ ] 8. Real-browser runs: every feature × style × width, Chrome + Firefox, slow 3G, offline → online, style switching A → E → F → D
- [ ] 9. Fix every bug found, then re-run A–G from a clean start
- [ ] 10. One top PR into develop (CI green, not merged) and the report with screenshots
