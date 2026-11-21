<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# 1. Choose hosting

*Shared hosting (cPanel) or a VPS. What the server needs.*

Menu runs on **shared hosting with cPanel** or on a **VPS** with Nginx. No Docker is needed.

The server needs:

- PHP **8.3** or newer, with `pdo_mysql`, `mbstring`, `openssl`, `gd` (with WebP), `intl`, `zip`, `exif`, `bcmath`, `fileinfo`.
- MySQL **8** or MariaDB **10.6+**.
- **HTTPS** (a free certificate is fine).
- PHP settings: `upload_max_filesize = 10M`, `post_max_size = 12M`, `memory_limit = 256M`, OPcache on.

Node is **not** needed on the server. You build the admin on your computer.

**Build the release on your computer:**

```bash
git checkout main && git pull
cd frontend && pnpm install --frozen-lockfile && pnpm build && cd ..
cd backend && composer install --no-dev --optimize-autoloader --classmap-authoritative && cd ..
```

Upload the whole `backend/` folder. Don't upload `.env`, `node_modules/` or `frontend/`.

Next: [Domain and HTTPS](domain-https.md).
