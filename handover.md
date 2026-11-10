# Handover

_Last updated: 2026-11-10, end of Step 2._

## Where we are
- **Step 1** (safety check + questions): done; answers in QUESTIONS.md.
- **Step 2** (PLAN.md + mockups): done, **waiting for owner approval**.
- **Step 3** (build): not started. Owner will enable package installs and start a new session.

## Repo
- `main`: one bootstrap commit (README, LICENSE, .gitignore). Protected.
- `develop`: created from `main`. All work arrives here through small PRs.
- PR #1 `docs/plan` → `develop`: PLAN.md, QUESTIONS.md, handover.md.

## Design
- Mockups on the design canvas: https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5 (customer menu light + dark, item sheet, admin menu editor desktop + phone, Super Admin dashboard).

## Pinned references
- shadcn-admin: `https://github.com/satnaing/shadcn-admin` @ `e16c87f213a5ba5e45964e9b67c792105ec74d26` (v2.2.1, 2026-06-11, MIT).
- Laravel 13 (latest stable at build time; pin exact version in composer.lock in Partition 1).

## Next session: start Partition 1 (project setup)
1. Confirm package installs work (`composer --version`, `pnpm --version`, network to packagist/npm).
2. Branch `feat/setup` from `develop`.
3. Create `backend/` with Laravel 13, `frontend/` from shadcn-admin at the pinned commit; remove Clerk and demo features; add i18n wiring.
4. Docker dev compose, CI workflow (checks only), THIRD_PARTY_NOTICES, `.env.example`.
5. Ask before adding any package not listed in PLAN.md §3.

## Notes for whoever continues
- Shared hosting is the main target: never depend on Node, Redis, queue workers or Docker at runtime.
- Every restaurant-owned table needs `tenant_id` + `UNIQUE(tenant_id, id)` + composite FKs.
- Every mutating endpoint needs the idempotency middleware (architecture test will enforce it).
