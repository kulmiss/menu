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

## Checks (same as CI)
Backend (`backend/`): `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test`
Frontend (`frontend/`): `pnpm lint`, `pnpm format:check`, `pnpm build`, `pnpm test`

## Licence
Proprietary — see [LICENSE](LICENSE). Third-party code: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
