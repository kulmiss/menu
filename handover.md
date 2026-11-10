# Handover

_Last updated: 2026-11-10 16:30 EAT. Clean stopping point: speed plan + first budgets (#6), CI green._

> **Speed is now the top priority (owner, 2026-11-10): PLAN.md §15 applies to every partition and every partition report must include the §15.5 numbers.**

## Where we are
| Part | Branch | PR | State |
|---|---|---|---|
| 2 backend (auth & tenancy) | `feat/auth-tenancy` | #3 | green, contained in #4 |
| 2b SPA auth, switcher, team | `feat/auth-spa` | #4 | **ready**, CI green |
| 3a menu data model + API | `feat/menu-data` | #5 | CI green |
| 3b admin menu editor | `feat/menu-data` | #5 | CI green |
| 3c photos | `feat/menu-data` | #5 | CI green |
| 3d import/export (openspout) | `feat/menu-data` | #5 | CI green |

| Performance plan + first budgets | `feat/performance` | #6 | CI green |

**#6 is the newest** and contains #3, #4 and #5: merge only #6, with *Create a merge commit*.

## Claude's workspace can now run everything
Since 2026-11-10 the cloud workspace can: `pnpm install` (npm registry), `composer install --prefer-source` (git clones of public GitHub repos; Packagist metadata is blocked, so **`composer require` of new packages still needs the owner's PC**), MariaDB 10.11 (`apt-get install mariadb-server`), Chromium for Vitest browser tests (`PLAYWRIGHT_BROWSERS_PATH` pointing at `/opt/pw-browsers` with a `chromium_headless_shell-1217` symlink to `-1243`), and a real-browser smoke test against `php artisan serve`.
Setup recipe used: `composer config -g use-github-api false`; `phpstan/phpstan` has no git source, so its zip was put into Composer's cache (`~/.cache/composer/files/phpstan/phpstan/<sha1 of dist url>.zip`, built with `git archive` of the tag).

## What 2b contains (PR #4)
- Routes `/admin/*`, `/auth/*` (sign-in, sign-up, forgot/reset password, two-factor, invite/$token); `/admin` guard on `GET /api/v1/me`; safe `?redirect=`.
- Fortify forms with CSRF cookie and 422 → field errors; 419 refresh-and-retry once with the same Idempotency-Key.
- Restaurant switcher (zustand, persisted), role-filtered sidebar, verify-email and subscription banners, "no restaurant" screen.
- Team page (members, roles, can-edit-prices, invite, pending, revoke, leave).
- Backend: reset/invite emails link to `/auth/...`; sign-up with `invitation_token` joins the inviting restaurant (QUESTIONS #56).

## What Partition 3 contains so far (PR #5)
- **3a API** under `/api/v1/t/{tenant}`: `menus` (+ `reorder`, `{id}/tree`, `{id}/duplicate`, `{id}/schedules`), `categories` (+ `reorder`, `duplicate`), `items` (+ `reorder`, `bulk`, `bulk-price`, `{id}/duplicate`, `{id}/sold-out`, `{id}/price`), `addon-groups` (+ options, `reorder`), `addon-options/{id}/sold-out`, `tags`. Composite FKs, plan limits (menus, items → 402), activity log + cache_version bump on every change.
- **3b SPA**: `/admin/menu` (editor), `/admin/addons`, `/admin/tags`; item sheet with details / prices & sizes / add-ons / tags & allergens; drag reorder (dnd-kit); bulk actions; staff see sold-out switches only (plus a price-only form with *can edit prices*).
- Decisions: QUESTIONS #56–61 (GD instead of Intervention, items may have no price, menu↔branch in Partition 6, feature switches in Partition 11).

- **3c photos**: `media` table, `POST/DELETE media`, GD sizes (320/640/1280 WebP, 640 JPEG, 1200×630 OG, dominant colour), HEIC refused with advice, `photos` plan limit, replaced photos deleted; SPA crop + downscale (react-easy-crop) on items and categories, thumbnails in the list.
- **3d import/export**: `GET export`, `GET import/template` (xlsx|csv), `POST import/preview` → per-row plan, `POST import/commit` (token, re-checked, all-or-nothing); `/admin/import`. Rules in QUESTIONS #63.
- **openspout ^5.3** was added from Claude's workspace: Packagist is blocked there, so it was resolved from the GitHub repo and only the openspout entry plus the content-hash were added to `composer.lock` (`composer validate --strict` passes, CI installs it).

## Checks at this point
Backend: Pint, Larastan 6, migrate → rollback → migrate, Pest **150**. Frontend: lint, Prettier, build, Vitest **150**. Browser smoke (375 px): sign-up → menu editor (categories, items, tags, allergens, sold out, keyboard/mouse reorder, bulk +10 %) → import CSV → export Excel → photo upload (3000×2000 → cropped 2048×1536, all sizes written). No errors.
Vitest browser tests pre-bundle a few deps in `vite.config.ts` (`optimizeDeps.include`) so Vite never reloads mid-run; add a dep there if CI ever logs "Vite unexpectedly reloaded".

## Speed work done (#6)
- PLAN.md §15 written (targets, honest limits with closest achievable targets, design, CI gates).
- Admin first load 285 → 241 KB gzip; CI ceiling 245 KB (`frontend/scripts/bundle-size.mjs`, `pnpm size`).
- Query-count tests (no N+1); photos WebP q72 / JPEG q75 / AVIF when available / < 300-byte placeholder (`media.placeholder`); `.htaccess` + Nginx immutable caching and compression.

## Next steps (in order)
1. **Owner decision: admin budget option (PLAN §15.6).** If A: replace axios with a small fetch wrapper (keep the interceptor features: Idempotency-Key, 419 retry; tests use `fakeApi` — adapt it), lazy-load more widgets, lower the CI ceiling to 225 KB.
2. **Partition 2 leftovers**: personal settings + 2FA setup (+ optimistic price edits, §15.3).
3. **Partition 4 — customer menu page per §15.2**: static files in `public/menu-cache` (+ `.gz`/`.br`) served by `.htaccess`/Nginx without PHP, rebuilt on every change and by the daily cron; inline CSS; ≤ 5 KB JS; service worker; `/q/{code}` via a tiny PHP file + pre-built map; demo menu seeder; **Lighthouse CI** (`@lhci/cli`, mobile throttled, 3 runs) and the 30 KB gate in CI; report the §15.5 numbers.

## Blockers / owner actions
- Merge #6 (contains #3, #4, #5).
- Choose the admin budget option (PLAN §15.6): A (recommended) / B / C.
- `main` branch protection still to add.

## Pinned references
- shadcn-admin `e16c87f` (v2.2.1, MIT); Laravel skeleton v13.11.0 `06d016a`; design mockups https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5

## Prompt for the next chat
> Continue the Menu project in kulmiss/menu from handover.md (branch feat/performance, PR #6). Speed is the top priority: follow PLAN.md §15 in everything and report the §15.5 numbers. Admin budget: option <A/B/C>. Then the Partition 2 leftovers, then Partition 4 (customer menu page) with Lighthouse CI. Same rules: stacked PRs (I merge only the newest), CI checks every PR, tests in every partition, shared hosting is the main target. Stop only for new packages, big architecture choices, licence doubts, or steps that can't be undone.
