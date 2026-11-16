<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# 2. Domain and HTTPS

*Point the domain at the public folder and turn on HTTPS.*

1. Upload `backend/` **outside** `public_html`, for example `/home/USER/menu/backend`.
2. In cPanel → **Domains**, point the domain to `/home/USER/menu/backend/public`.
3. Turn on HTTPS: cPanel → **SSL/TLS Status → Run AutoSSL**, or `certbot --nginx` on a VPS.
4. Open `https://your-domain/up`. It must say "Application up" (after the next steps).

The `public` folder **must** be the document root. The fast menu pages and QR links need its `.htaccess` file. It is a hidden file: make sure it was uploaded.

**Cloudflare?** Use SSL mode **Full (strict)**. Turn off **Rocket Loader**. Set `TRUSTED_PROXIES=*` in `.env` (next page).

Next: [The .env file](env-file.md).
