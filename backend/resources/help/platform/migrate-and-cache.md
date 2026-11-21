---
title: 4. Database, migrate and cache
topic: setup
summary: Create the tables and make the site fast.
order: 40
---

1. In cPanel → **MySQL Databases**, create a database and a user with all privileges (utf8mb4).
2. Put the names in `.env`.
3. Open cPanel → **Terminal** in `backend/` and run:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan platform:aggregate
php artisan optimize
```

`migrate` creates the tables, the plans and the default Terms and Privacy texts. `optimize` caches the config, routes, views and events.

4. Make these folders writable (755): `storage/`, `bootstrap/cache/`, `public/media/`, `public/menu-cache/`, `public/menu-assets/`.

**Never run the demo seeder on the live server.** It makes restaurants with known passwords. It refuses when `APP_ENV=production`, but don't try.

**After every update** run `php artisan migrate --force`, `php artisan optimize` and `php artisan menu:refresh-pages --all`.

Next: [Backup key](help:backup-key).
