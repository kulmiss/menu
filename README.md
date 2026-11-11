# Menu

Digital menu SaaS for restaurants and cafés: a fast public QR menu, a phone-friendly restaurant admin and a Super Admin platform. See [PLAN.md](PLAN.md).

## Layout

| Folder | What |
|---|---|
| `backend/` | Laravel 13 JSON API + Blade public menu (PHP 8.3+, MySQL 8 / MariaDB 10.6+) |
| `frontend/` | Admin + Super Admin SPA (React, TanStack Router, shadcn/ui), built into `backend/public/app` |
| `docker/`, `docker-compose.yml` | Local development only — production runs without Docker |
| `scripts/` | Helper scripts |

## Local setup

### Windows + Laragon
1. Start Laragon (**Start All**, so MySQL runs).
2. In a terminal at the repository root: `.\setup-local.bat`
3. Read `setup-log.txt` for the result of every step.

### Docker Desktop (any OS)
1. Start Docker Desktop.
2. Windows: `.\setup-docker.bat` (other systems: run the same `docker compose` commands it lists).
3. Read `setup-log.txt`. App: http://localhost:8080 · Mail: http://localhost:8026 · Admin dev server: `docker compose up -d vite` → http://localhost:5173/app/

### Sales demo (local only)
1. Real photos (once): `powershell -ExecutionPolicy Bypass -File scripts\download-demo-photos.ps1`
   (or `php artisan demo:photos` in `backend/`). It downloads the 110 photos listed in
   `backend/database/seeders/demo-photos/photos.json` (Unsplash / Pexels licence only, credits in `CREDITS.md`)
   as WebP, under 8 MB in all. Commit the `.webp` files. Without them the demo uses simple generated pictures.
2. `php artisan migrate:fresh --seed --seeder=DemoSeeder` (only the café: `--seeder=DemoCafeSeeder`). Refused when `APP_ENV=production`.

What you get: **Buna & Bread** at `/demo` (and `/demo/piassa`) with 67 dishes, 90 days of insights and every field filled;
**Lumière Table** `/demo-fine` (style A), **Stack & Slice** `/demo-pizza` (B), **Crumb & Juice** `/demo-bakery` (C);
about 30 small restaurants in every state with 12 months of payments for the Super Admin dashboard.
All people, e-mails and phone numbers are fictional.

Logins (password `password`):
- Café: `demo@example.com` (owner), `manager@example.com`, `staff@example.com` → `/admin`
- Showcases: `fine@example.com`, `pizza@example.com`, `bakery@example.com`
- Super Admin: `admin@example.com`, two-factor secret `JBSWY3DPEHPK3PXP` (add it to an authenticator app) → `/platform`

## Deploying
Shared hosting (cPanel) or a VPS: see [docs/deploy.md](docs/deploy.md) — build, `.env`, cron, OPcache, Cloudflare, e-mail (SMTP, SPF, DKIM), the first Super Admin, updates and backups.

## Checks (same as CI)
Backend (`backend/`): `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test`
Frontend (`frontend/`): `pnpm lint`, `pnpm format:check`, `pnpm build`, `pnpm test`

## Licence
Proprietary — see [LICENSE](LICENSE). Third-party code: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
