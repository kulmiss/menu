# Final report (PLAN.md §12, partition 15)

_Written 2026-11-12, updated 2026-11-13 after the pre-launch check (PRs up to #86)._

This is the go-live checklist and the test summary. Deployment details are in [`deploy.md`](deploy.md); link previews in [`share-preview.md`](share-preview.md); every decision in [`../QUESTIONS.md`](../QUESTIONS.md); the build history in [`../handover.md`](../handover.md).

## 1. Go-live checklist

The step-by-step checklist now lives in [`deploy.md` §12](deploy.md#12-go-live-checklist), next to the details it refers to (updated 2026-11-13 for the pre-launch check).

## 2. Test summary

All checks run on every PR in GitHub Actions (`.github/workflows/ci.yml`) and were green on the last PRs.

### Backend
| Check | Result |
|---|---|
| Composer validate (strict) | pass |
| Pint (code style) | pass |
| Larastan level 6 | 0 errors |
| Migrate → rollback → migrate | pass |
| Pest | **460 tests**, ~3,650 assertions, all passing (with the pre-launch fix PRs #76–#86) |

The tests cover: auth, 2FA and tenancy isolation; roles and permissions per endpoint; menus, items, photos, import/export; branches, tables and QR codes (scanned back with a QR reader); opening hours; promotions; insights and beacon; Super Admin, plans, features and one-click "log in as" (not recorded, 2-hour limit, safety blocks); idempotency keys; the guest page (static copy, styles A–D, photo loading, branch URLs, footer, link-preview tags read from the page `<head>`); PDF menus; demo seeders; e-mail settings and owner e-mails.

### Admin and guest-page scripts
| Check | Result |
|---|---|
| ESLint, Prettier | pass |
| TypeScript build | pass |
| Admin first load | **228.5 KB** gzip (budget 240 KB since 2026-11-13) |
| Vitest (browser mode, Chromium) | **278 tests** in 68 files, all passing |

The guest-page script tests include opening-hours logic, service-worker updates, sheet scroll lock and swipe-to-close, back to top, the "Add to my order" bar, and contrast ≥ 4.5:1 of the hours pill in all styles, light and dark.

### Guest menu speed (CI, Lighthouse mobile, simulated 4G and 4× CPU, median of 5 runs)
The demo menu, served as a static file by Nginx like production. Limits: LCP ≤ 1.3 s (target 1.2 s), TBT ≤ 200 ms (target 100 ms), CLS ≤ 0.02, page ≤ 30 KB, one script file, no third parties.

| Style | With banner | Without banner | Without banner and specials |
|---|---|---|---|
| A editorial | 1.05 s | 0.90 s | 1.20 s |
| B grid | 0.98 s | 0.90 s | – |
| C bistro | 1.28 s* | 0.90 s | – |
| D minimal | 0.98 s | 0.78 s | – |

All runs score 100. TBT is 0–49 ms and CLS is 0. The page is 19.9 KB gzip of the 30 KB budget, and the inline script is 4.80 KB of 5 KB.

\* LCP on CI moves between about 0.98 and 1.28 s from run to run for the same code. It is always under the 1.3 s limit. The owner closed speed work on 2026-11-12 with these limits.

## 3. Known limits and open items
- Optimistic price edits in the admin (PLAN.md §15.3): not built (owner, 2026-11-12: skipped for now).
- English only. Translation screens stay hidden until a second language is switched on; texts are ready in `t()` / `__()`.
- Style A without banner and specials: 1.20 s, accepted (decision #160).
- Demo photos are Pexels License (`CREDITS.md`); the demo is for sales and local use only.
