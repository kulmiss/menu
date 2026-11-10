# Handover

_Last updated: 2026-11-10, Step 3 · Partition 1 (project setup) in progress._

## Where we are
- Steps 1–2: done and approved.
- **Partition 1 (project setup)**: code written, waiting for the first local run of `setup-local.bat` on the owner's PC (Windows + Laragon: PHP 8.5.11, Composer 2.10.3, Node 26.13.0).
- Claude's workspaces cannot install packages (cloud container and the desktop VM both blocked), so the owner runs the installs; Claude reads `setup-log.txt` from `C:\laragon\www\menu` and fixes errors.

## Repo
- `main`: bootstrap commit. Owner still to add branch protection.
- `develop`: bootstrap commit. PR #1 (`docs/plan`) **still open** — merge it.
- Partition 1 will be branch `feat/setup` → PR into `develop` (owner commits and pushes from the PC; commands in the chat).

## What Partition 1 contains
- `backend/`: Laravel skeleton v13.11.0 (`06d016a`), MySQL `.env.example`, database cache/sessions/queue, public disk at `public/media` (no symlink), Pest + Larastan (level 6) + Pint, tests: health, MySQL migrations, config, architecture. Sanctum via `php artisan install:api` (run by the setup script).
- `frontend/`: shadcn-admin @ `e16c87f` with Clerk, tasks/apps/chats/users/help-center demos, sign-in-2, notifications/display settings, demo dashboard, faker and unused brand icons removed. i18next wired (en source; so/am/ar marked `machine_draft`), language switch with RTL for Arabic, tests for it and for locale key parity. Vite `base: /app/`, build output `backend/public/app`.
- `docker-compose.yml` (dev only), `.github/workflows/ci.yml` (checks only), `THIRD_PARTY_NOTICES.md`, `setup-local.bat`, `scripts/create-databases.php`.

## Known follow-ups (later partitions)
- Template pages still have English strings (auth forms, settings, errors). They are rewritten through i18n when rebuilt in Partition 2 (auth) and later partitions.
- Social-login buttons (GitHub/Facebook) in the template auth forms are removed in Partition 2.
- `recharts` stays for Insights (Partition 10).
- Routes move under `/admin` and `/platform` in Partition 2.

## Pinned references
- shadcn-admin: `https://github.com/satnaing/shadcn-admin` @ `e16c87f213a5ba5e45964e9b67c792105ec74d26` (v2.2.1, MIT).
- Laravel skeleton: `laravel/laravel` v13.11.0 @ `06d016a364a37430eec9cbc52209adce3d7667ce` (framework `^13.17`; exact versions in `composer.lock` once generated).
- Design mockups: https://claude.ai/artifact/HKJ6v8zV9y2b7oQoYb33B5

## Next steps
1. Owner runs `setup-local.bat`; Claude fixes anything in `setup-log.txt` until every step passes.
2. Owner commits on `feat/setup` (lockfiles included), pushes, opens PR into `develop`; CI must be green.
3. Partition 2: auth & tenancy.
