# Handover

_Last updated: 2026-11-10 16:10 EAT. Clean stopping point: Partition 3 complete (#5 ready), CI green._

## Where we are
| Part | Branch | PR | State |
|---|---|---|---|
| 2 backend (auth & tenancy) | `feat/auth-tenancy` | #3 | green, contained in #4 |
| 2b SPA auth, switcher, team | `feat/auth-spa` | #4 | **ready**, CI green |
| 3a menu data model + API | `feat/menu-data` | #5 | CI green |
| 3b admin menu editor | `feat/menu-data` | #5 | CI green |
| 3c photos | `feat/menu-data` | #5 | CI green |
| 3d import/export (openspout) | `feat/menu-data` | #5 | CI green |

**#5 is ready** and contains #3 and #4: merge only #5, with *Create a merge commit*.

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

## Next steps (in order)
1. **Partition 2 leftovers** (small PR stacked on `feat/menu-data`): personal settings pages — profile, password, **2FA setup** (Fortify endpoints exist), interface language — and translate the config drawer. Needed before Super Admin (2FA required).
2. **Partition 4 — customer menu page**: Blade page `/{slug}`, inlined CSS + ~5 KB JS, layouts, search/filter, item sheet, HTML cache by `cache_version`, view beacon, SEO, `/q/{code}`; budget check in CI.

## Blockers / owner actions
- Merge #5 (contains #3 and #4).
- `main` branch protection still to add.

## Pinned references
- shadcn-admin `e16c87f` (v2.2.1, MIT); Laravel skeleton v13.11.0 `06d016a`; design mockups https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5

## Prompt for the next chat
> Continue the Menu project in kulmiss/menu from handover.md (branch feat/menu-data, PR #5 ready). Same rules as PLAN.md and QUESTIONS.md: stacked PRs (I merge only the newest), CI checks every PR, tests in every partition, shared hosting is the main target. Do the Partition 2 leftovers (settings + 2FA setup), then Partition 4 (customer menu page). Stop only for new packages, big architecture choices, licence doubts, or steps that can't be undone.
