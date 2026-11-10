# Handover

_Last updated: 2026-11-10 17:20 EAT. Clean stopping point after Partition 4 (PR #8)._

> **Speed is the top priority (owner, 2026-11-10): PLAN.md §15 applies to every partition and every partition report includes the §15.5 numbers.**
> **English only for now (owner, 2026-11-10):** texts stay in `t()` / `__()` and the database keeps per-language fields; language switches and translation screens are hidden while one language is enabled.
> **Working mode (owner):** go through all remaining partitions without stopping for OK, then write the Step 4 final report. Stop only for: changes outside this project, steps that can't be undone, licence doubts, the same CI failure 3 times, or the working limit.

## Where we are
| Part | Branch | PR | State |
|---|---|---|---|
| 2 backend (auth & tenancy) | `feat/auth-tenancy` | #3 | contained in newer PRs |
| 2b SPA auth, switcher, team | `feat/auth-spa` | #4 | contained |
| 3 menu data, editor, photos, import/export | `feat/menu-data` | #5 | contained |
| Performance plan + admin budget (option A) | `feat/performance` | #6 | contained |
| Settings + 2FA; English only | `feat/settings` | #7 | CI green, contained |
| **4 Customer menu page** | `feat/public-menu` | **#8** | newest |

**Merge only the newest PR (#8)** with *Create a merge commit*; it contains all earlier ones.

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

## Checks at this point
Backend: Pint, Larastan 6, Pest **170**. Frontend (unchanged since #7): lint, Prettier, build, Vitest **148**, bundle 218 KB. Lighthouse CI passes locally. Browser check of the guest page at 390 px: list, search, sheet with photo/sizes/allergens, no console errors.

## Packages added in this build (all pinned by the lockfiles)
| Package | Version | Licence | Where |
|---|---|---|---|
| openspout/openspout | 5.3.0 | MIT | backend, XLSX import/export |
| @dnd-kit/core | 6.3.1 | MIT | admin, drag reorder |
| @dnd-kit/sortable | 10.0.0 | MIT | admin |
| @dnd-kit/utilities | 3.2.2 | MIT | admin |
| react-easy-crop | 6.2.5 | MIT | admin, photo crop |
| @lhci/cli | 0.16.0 | Apache-2.0 | CI only, via `npx` (not a dependency) |
Removed: axios. `package.json` uses caret ranges for the dnd-kit packages; the exact versions are pinned in `pnpm-lock.yaml` (`--frozen-lockfile` in CI).

## Claude's workspace notes
Workspace can run `pnpm install`, `composer install --prefer-source` (Packagist blocked: new Composer packages need the owner's PC or the lock-entry trick used for openspout), MariaDB (`service mariadb start` after idle), Chromium (`PLAYWRIGHT_BROWSERS_PATH=/tmp/pw` with a `chromium_headless_shell-1217` → `-1243` symlink), Nginx, Lighthouse (`npx lighthouse@12.8.2 --chrome-path=/opt/pw-browsers/chromium-1243/chrome-linux64/chrome`).

## Next steps (in order)
5 Appearance + live preview → 6 Branches + QR (`/q/{code}` without the database) → 7 Restaurant info + hours → 8 Translations (deferred; note only) → 9 Promotions → 10 Insights (beacon counting) → 11 Super Admin → 12 Demo data (extend DemoSeeder) → 13 Docs (deploy, OPcache, Cloudflare, cron for `schedule:run`) → 14 Final QA → Step 4 final report.
Still open from §15.3: optimistic price edits in the admin.

## Blockers / owner actions
- Merge #8 (contains #3–#7).
- `main` branch protection still to add.
- Production needs the Laravel scheduler cron (`* * * * * php artisan schedule:run`) for `menu:refresh-pages` (documented in Partition 13).

## Pinned references
- shadcn-admin `e16c87f` (v2.2.1, MIT); Laravel skeleton v13.11.0 `06d016a`; design mockups https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5

## Prompt for the next chat
> Continue the Menu project in kulmiss/menu from handover.md (newest PR #8, branch feat/public-menu). Speed is the top priority (PLAN.md §15); English only. Work through all remaining partitions (5 → 14) without stopping for my OK, then write the Step 4 final report. Same rules: stacked PRs (I merge only the newest), CI checks every PR, tests in every partition, shared hosting is the main target.
