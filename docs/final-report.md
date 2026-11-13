# Final report (PLAN.md §12, partition 15)

_Written 2026-11-12. Covers everything in `develop` up to PR #47 (plus the docs/e-mail PRs of the same day)._

This is the go-live checklist and the test summary. Deployment details are in [`deploy.md`](deploy.md); link previews in [`share-preview.md`](share-preview.md); every decision in [`../QUESTIONS.md`](../QUESTIONS.md); the build history in [`../handover.md`](../handover.md).

## 1. Go-live checklist

### Before the release
- [ ] Merge the release PR `develop` → `main`. Deployment builds from `main`.
- [ ] Protect `main` in GitHub (Settings → Branches): require a pull request, no force push, no deletion.

### Server (cPanel; details in `deploy.md` §3)
- [ ] PHP 8.3 with `gd`, `intl`, `pdo_mysql`, `zip`, `exif`, `bcmath`; OPcache on (`deploy.md` §5).
- [ ] Domain document root → `backend/public`; HTTPS certificate active.
- [ ] `.env`:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generated
  - `APP_URL=https://your-domain` (exactly as people type it, https). Admin login and link previews depend on it.
  - Database, `QUEUE_CONNECTION=database`; `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` if the admin is on another subdomain.
- [ ] `php artisan migrate --force`, then `php artisan optimize`.
- [ ] Build the admin (`pnpm install && pnpm build`) and upload it with the release (`deploy.md` §2).
- [ ] **Never** run `DemoSeeder` / `DemoCafeSeeder` on the live server. They create restaurants with known passwords and refuse to run when `APP_ENV=production`.
- [ ] Cron, every minute: `* * * * * php /home/USER/menu/backend/artisan schedule:run >> /dev/null 2>&1`. It is needed for page refresh at midnight, the e-mail queue and the health widget.
- [ ] If photos came from an older install: `php artisan media:regenerate` (800 px sizes and link-preview pictures), then `php artisan menu:refresh-pages --all`.

### Platform set-up
- [ ] Create the first Super Admin (`deploy.md` §8) and turn on two-factor login.
- [ ] Super Admin → Settings: platform name, support e-mail, domain, currency, terms, privacy.
- [ ] Super Admin → Settings → Email: SMTP details, then **Send test email**. Add SPF and DKIM DNS records for the sender's domain (`deploy.md` §7).
- [ ] Optional: Super Admin → Settings → Link preview: upload your own default share picture.
- [ ] Plans and features (menu styles A–D, PDF menu, "My order", branding) as you sell them.
- [ ] Create a test restaurant from Super Admin. Check that the owner receives the welcome e-mail and can set a password; use **Resend set-password e-mail** if not.

### Smoke test on the live site (phone and desktop)
- [ ] Owner: log in, add a dish with a photo, set opening hours, download a QR code and a PDF menu.
- [ ] Guest: open the menu from the QR code; search; open a dish; "My order" → show to waiter; share; back to top; light and dark mode.
- [ ] Branches: the branch URL shows only that branch; "Other branches" in the footer.
- [ ] Change a price, reopen the menu on the phone: the new price shows ("Menu updated").
- [ ] Facebook Sharing Debugger on one menu URL (`share-preview.md`).
- [ ] Super Admin dashboard: the health widget shows the cron as running.
- [ ] Backups set up (`deploy.md` §10): database daily, `backend/public/media` and `.env`.

## 2. Test summary

All checks run on every PR in GitHub Actions (`.github/workflows/ci.yml`) and were green on the last PRs.

### Backend
| Check | Result |
|---|---|
| Composer validate (strict) | pass |
| Pint (code style) | pass |
| Larastan level 6 | 0 errors |
| Migrate → rollback → migrate | pass |
| Pest | **365 tests**, ~2,300 assertions, all passing |

The tests cover: auth, 2FA and tenancy isolation; roles and permissions per endpoint; menus, items, photos, import/export; branches, tables and QR codes (scanned back with a QR reader); opening hours; promotions; insights and beacon; Super Admin, plans, features and "log in as" limits; idempotency keys; the guest page (static copy, styles A–D, photo loading, branch URLs, footer, link-preview tags read from the page `<head>`); PDF menus; demo seeders; e-mail settings and owner e-mails.

### Admin and guest-page scripts
| Check | Result |
|---|---|
| ESLint, Prettier | pass |
| TypeScript build | pass |
| Admin first load | **224.9 KB** gzip (budget 225 KB) |
| Vitest (browser mode, Chromium) | **228 tests** in 50 files, all passing |

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
