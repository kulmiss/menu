# Handover

_Last updated: 2026-11-10 15:40 EAT. Clean stopping point: Partition 3b pushed, CI green._

## Where we are
| Part | Branch | PR | State |
|---|---|---|---|
| 2 backend (auth & tenancy) | `feat/auth-tenancy` | #3 | green, contained in #4 |
| 2b SPA auth, switcher, team | `feat/auth-spa` | #4 | **ready**, CI green |
| 3a menu data model + API | `feat/menu-data` | #5 (draft) | CI green |
| 3b admin menu editor | `feat/menu-data` | #5 (draft) | CI green |
| 3c photos | — | — | next |
| 3d import/export | — | — | **waiting for owner: XLSX library** |

Stacked PRs: merge only the newest (#5 once ready; #4 now if you want 2b in `develop` first), *Create a merge commit*.

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

## Checks at this point
Backend: Pint, Larastan 6, migrate → rollback → migrate, Pest **136**. Frontend: lint, Prettier, build, Vitest **141**. Browser smoke (375 px): sign-up → menu → categories → items with tags/allergens → sold out → keyboard and mouse reorder → bulk +10 % → item sheet. No errors.

## Next steps (in order)
1. **3c photos** (branch on `feat/menu-data`): `media` table + `media_id` FKs, upload endpoint (≤ 8 MB, jpeg/png/webp; HEIC refused with a message), GD: orient, strip EXIF, 320/640/1280 WebP + 640 JPEG + 1200×630 OG, dominant colour; plan limit `photos`; SPA: crop + downscale ≤ 2048 px in the browser with **react-easy-crop** (approved in PLAN §3.2), photo field on items and categories, thumbnails in the editor list.
2. **3d import/export**, once the owner picks the XLSX approach (see Blockers): template download, upload CSV/XLSX → preview diff → commit; export.
3. Mark #5 ready, then Partition 4 (customer menu page).
4. Partition 2 leftovers, to do before Partition 11: personal settings pages (profile, password, **2FA setup**, which Super Admin needs) and the config drawer are still template stubs with English text.

## Blockers / owner actions
- **Decide the XLSX library for import/export (3d).** Options: `openspout/openspout` (MIT, streaming, low memory, good for shared hosting; installed from the owner's PC with `composer require`), `phpoffice/phpspreadsheet` (MIT, heavier on memory), or no package (CSV only, or a small XLSX reader on PHP's built-in zip extension).
- `main` branch protection still to add.

## Pinned references
- shadcn-admin `e16c87f` (v2.2.1, MIT); Laravel skeleton v13.11.0 `06d016a`; design mockups https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5

## Prompt for the next chat
> Continue the Menu project in kulmiss/menu from handover.md (branch feat/menu-data, draft PR #5). Same rules as PLAN.md and QUESTIONS.md: stacked PRs (I merge only the newest), CI checks every PR, tests in every partition, shared hosting is the main target. Do Partition 3c (photos), then 3d with the XLSX choice I give below, then mark #5 ready and start Partition 4. Stop only for new packages, big architecture choices, licence doubts, or steps that can't be undone.
