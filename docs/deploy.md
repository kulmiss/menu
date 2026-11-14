# Deploying Menu

Shared hosting (cPanel) is the main target; a VPS with Nginx works the same way with a few extras. Production runs **without Docker**.

Contents: [1 Requirements](#1-requirements) · [2 Build a release](#2-build-a-release-on-your-computer) · [3 Shared hosting](#3-shared-hosting-cpanel) · [4 VPS](#4-vps-nginx--php-fpm) · [5 OPcache](#5-opcache) · [6 Cloudflare](#6-cloudflare-optional) · [7 E-mail](#7-e-mail-smtp-spf-dkim-dmarc) · [8 First Super Admin](#8-the-first-super-admin) · [9 Updating](#9-updating-to-a-new-version) · [10 Backups](#10-backups) · [11 Troubleshooting](#11-troubleshooting)

## 1. Requirements

- PHP **8.3+** with: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd` (WebP support), `intl`, `zip`, `exif`, `bcmath`. AVIF photos are made automatically when GD supports AVIF; otherwise WebP only.
- MySQL **8** or MariaDB **10.6+**, `utf8mb4`.
- Apache with `mod_rewrite` (shared hosting) or Nginx + PHP-FPM (VPS).
- HTTPS.
- Node is **not** needed on the server: the admin app is built on your computer.

## 2. Build a release (on your computer)

```bash
git checkout main && git pull
cd frontend && pnpm install --frozen-lockfile && pnpm build && cd ..   # → backend/public/app
cd backend && composer install --no-dev --optimize-autoloader --classmap-authoritative && cd ..
```

Upload the whole `backend/` folder (it now contains `vendor/` and `public/app/`). Do **not** upload `backend/.env` from your computer, `node_modules/` or `frontend/`.

## 3. Shared hosting (cPanel)

1. **Upload** `backend/` to a folder **outside** `public_html`, e.g. `/home/USER/menu/backend`.
2. **Document root**: point the domain (cPanel → Domains) to `/home/USER/menu/backend/public`.
   If the host doesn't allow that, replace `public_html` with a symlink: `ln -s /home/USER/menu/backend/public /home/USER/public_html` (after moving the old folder away).
   The fast static menu pages and QR redirects (no PHP) **need `public/` to be the document root** (`.htaccess` rules).
3. **Database**: create a MySQL database + user (cPanel → MySQL Databases), all privileges.
4. **`.env`**: copy `.env.example` to `.env` and set at least:

   ```dotenv
   APP_NAME="Menu"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://menu.example.com
   APP_KEY=            # filled by key:generate below

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...

   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   CACHE_STORE=database          # or file
   QUEUE_CONNECTION=database     # emptied every minute by the scheduler (cron below)

   MAIL_MAILER=smtp              # used until the Super Admin saves SMTP settings (section 7)
   MAIL_HOST=mail.example.com
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=no-reply@example.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```

   If the admin runs on another subdomain than `APP_URL`, also set `SANCTUM_STATEFUL_DOMAINS=admin.example.com` and `SESSION_DOMAIN=.example.com`.
5. **Terminal** (cPanel → Terminal, or SSH), in `/home/USER/menu/backend`:

   ```bash
   php artisan key:generate --force
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
   ```

   Never run the demo seeders on a live server: they create restaurants with known passwords (`DemoSeeder` refuses when `APP_ENV=production`).
6. **Writable folders** (755 for folders, owned by your cPanel user): `storage/`, `bootstrap/cache/`, `public/media/`, `public/menu-cache/`, `public/menu-assets/`.
7. **Cron** (cPanel → Cron Jobs, every minute) — the only cron line needed:

   ```cron
   * * * * * cd /home/USER/menu/backend && php artisan schedule:run >> /dev/null 2>&1
   ```

   It sends queued e-mails (invitations) every minute, rebuilds menu pages whose subscription changed (hourly) and every page at 00:05 (so "New" badges expire), cleans old statistics and deleted restaurants (03:15, 03:30) and removes self sign-ups whose e-mail was not confirmed within 7 days (`signup:prune`, 03:20; the menu address becomes free again, each removal is in the activity log). Sign-up e-mails (confirm, welcome, approval) go through the same queue, and so do Super Admin → Messages campaigns (a few per minute, set on the Messages screen; keep it under your mail provider's hourly limit). Time-based prices: `menu:price-periods` (every minute) rebuilds a restaurant's page when a happy hour starts or ends. Monthly Insights e-mail: `insights:monthly` (hourly) sends last month's PDF report on the 1st from 07:00 restaurant time, once per month, to restaurants that switched it on. Check with `php artisan schedule:list`.
   Some hosts need the full PHP path, e.g. `/usr/local/bin/php` or `/opt/cpanel/ea-php83/root/usr/bin/php`.
8. **PHP settings** (cPanel → MultiPHP INI Editor): `upload_max_filesize = 10M`, `post_max_size = 12M`, `memory_limit = 256M` (photo resizing), and OPcache on (section 5).
9. Open `https://menu.example.com/up` → "Application up". Then create the Super Admin (section 8).

## 4. VPS (Nginx + PHP-FPM)

- Use [`docker/nginx/default.conf`](../docker/nginx/default.conf) as the site config: document root `backend/public`, static menu pages from `menu-cache/` with `gzip_static on`, year-long caching for `/app/assets`, `/media`, `/menu-assets`, and `/q/CODE` handled by `qr.php`. Change `root` and `fastcgi_pass` to your paths/socket.
- Add HTTPS (e.g. `certbot --nginx`) and HTTP/2 or HTTP/3.
- Cron: the same `schedule:run` line as above (as the web user). With Supervisor you may also run `php artisan queue:work` permanently; the scheduler line can stay.
- Give the PHP-FPM user write access to the folders listed in 3.6.

## 5. OPcache

PHP's OPcache keeps compiled code in memory: the admin API answers several times faster. Recommended (`php.ini` / MultiPHP INI Editor / `opcache.ini`):

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1   ; 0 is faster, but then reload PHP-FPM after every upload
opcache.revalidate_freq=60
opcache.jit=0                   ; JIT gives nothing for this app
```

Guest menu pages don't use PHP at all (static copies), so they are fast either way.

## 6. Cloudflare (optional)

- DNS record **proxied** (orange cloud); SSL/TLS mode **Full (strict)**.
- Speed: Brotli **on**; **Rocket Loader off** and Auto Minify off (they rewrite the page's tiny inline script and slow it down).
- Caching: keep Cloudflare's default (it does **not** cache HTML). Menu pages already revalidate cheaply (ETag), and edits must show at once. Static files under `/app/assets/`, `/menu-assets/` and `/media/` are sent with year-long cache headers and are cached by Cloudflare automatically.
- If you add a "Cache Everything" rule, exclude `/api/*`, `/admin*`, `/platform*`, `/auth/*` and the menu pages — otherwise guests see old menus after edits.

## 7. E-mail (SMTP, SPF, DKIM, DMARC)

**Where the settings live:** Super Admin → **Settings → Email (SMTP)** (server, port, encryption, username, password, from address and name). The password is stored encrypted and never shown again. While nothing is saved there, the `.env` `MAIL_*` values are used. Press **Send test email** after any change; the result (or the server's error) is shown right away.

Every e-mail the platform sends uses these settings: owner invitations, "choose your password" for new restaurants, password resets and e-mail checks.

**Deliverability** — add these DNS records for the domain of the from address (ask your mail provider for exact values):

| Record | Example | Why |
|---|---|---|
| SPF (TXT on `example.com`) | `v=spf1 include:_spf.your-mail-provider.com ~all` | which servers may send for the domain |
| DKIM (TXT on `selector._domainkey.example.com`) | value from the provider / cPanel → Email Deliverability | signs each e-mail |
| DMARC (TXT on `_dmarc.example.com`) | `v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com` | tells receivers what to do with failures |

On cPanel, **Email Deliverability** shows and can install SPF and DKIM for you. Use a from address on the same domain as SPF/DKIM.

## 8. The first Super Admin

Super Admins are created on the server, not in the app. In `backend/`:

```bash
php artisan tinker --execute="\$u = App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => 'a-long-password']); \$u->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();"
```

Then sign in at `https://menu.example.com/auth/sign-in` and open `https://menu.example.com/platform`. Until two-factor sign-in is on, the platform shows only **Set up two-factor**: scan the QR code with an authenticator app, enter the code and keep the recovery codes. After that the whole platform opens.

## 9. Updating to a new version

```bash
cd /home/USER/menu/backend
php artisan down --retry=30
# upload the new backend/ files (keep .env, storage/, public/media/)
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan menu:refresh-pages --all     # rebuild the static menu pages with the new design
php artisan invoices:backfill            # once after the invoices release: invoices for older payments (no e-mails; safe to repeat)
php artisan up
```

When a release adds a photo size (the 800 px size was added on 2026-11-11), make it for photos uploaded earlier — once, safe to repeat:

```bash
php artisan media:regenerate --dry-run   # how many photos miss a size
php artisan media:regenerate             # make them and rebuild those menus (--tenant=slug for one restaurant)
```

If OPcache has `validate_timestamps=0`, reload PHP (cPanel: change the PHP version and back, or ask the host).

## 10. Backups

**Automatic (Super Admin → Backups).** Every night at 02:30 the scheduler (the same cron line) writes one zip to `storage/app/backups/` — outside the public folder, never downloadable without signing in:

- `database.sql` — the whole database as SQL (made in PHP, no `mysqldump` needed; caches and sessions are left empty),
- `media/…` — every uploaded photo and logo,
- `manifest.json` — date, row and file counts.

Old zips are thinned out to the newest one of each of the last **7 days, 4 weeks and 3 months** (change it on the Backups screen). Optional off-site copy: switch on "Copy to S3-compatible storage" and fill in the endpoint, region, bucket, access key and secret key (stored encrypted). Examples: Backblaze B2 `https://s3.<region>.backblazeb2.com` (region e.g. `eu-central-003`), Cloudflare R2 `https://<account-id>.r2.cloudflarestorage.com` (region `auto`), AWS `https://s3.<region>.amazonaws.com`.

If a backup fails, or none succeeded for 26 hours, the dashboard's System health shows a warning and every Super Admin gets one e-mail a day. **Also keep a copy of `.env`** somewhere safe (password manager): the `APP_KEY` in it is needed to read saved SMTP passwords, two-factor secrets and the off-site secret key.

Run one by hand: `php artisan backup:run`.

### Restoring a backup

There is no restore button on purpose: restoring replaces everything. Tested on the demo data on 2026-11-13 (34 restaurants, 30,451 statistics rows, 257 photos: every table count and every photo identical after restore).

1. **Download** the zip (Backups → Download, then your password or a two-factor code) or take it from `storage/app/backups/` / the off-site bucket.
2. **Put the site in maintenance mode:** `cd /home/USER/menu/backend && php artisan down`
3. **Unzip** it somewhere outside `public`: `unzip backup-YYYYMMDD-HHMMSS-N.zip -d ~/restore`
   (an **encrypted** backup, `….zip.enc`, first: `php artisan backup:decrypt backup-….zip.enc` — needs the same `BACKUP_KEY`, see [security.md](security.md#backups-h4))
4. **Database** — either
   - cPanel → phpMyAdmin → your database → *Import* → choose `database.sql` (the file drops and recreates every table), or
   - Terminal: `mysql -u DB_USER -p DB_NAME < ~/restore/database.sql`
   (To try it first, import into a new empty database and point a copy of the site at it.)
5. **Photos:** `rm -rf public/media && cp -a ~/restore/media public/media` (keep permissions 755 / owner = your cPanel user).
6. **`.env`:** keep the current one; if the server is new, restore your saved `.env` (same `APP_KEY`).
7. **Rebuild what isn't backed up:** `php artisan optimize:clear && php artisan migrate --force && php artisan menu:refresh-pages --all`
8. **Back online:** `php artisan up`, then open a menu, sign in to the admin and check Backups shows the restored list.

## 10a. Mobile app push notifications (optional)

The Menu Admin app works without this; pushes then only show in the app's inbox.

1. Firebase console → your project → Project settings → **Service accounts** → **Generate new private key**. A JSON file downloads. Keep it secret.
2. Upload it to the server **outside `public_html`**, e.g. `storage/app/private/firebase.json`, permissions `600`.
3. In `.env`: `FCM_CREDENTIALS=storage/app/private/firebase.json`, then `php artisan config:cache`.
4. Nothing else: the existing cron line sends the queued pushes every minute. Test: sign in on the app, then record a payment as another Super Admin, or wait for the 08:00/09:00 reminders.

Never commit this file. It is not inside the backup zip; keep a copy in your password manager.

## 11. Troubleshooting

| Problem | Check |
|---|---|
| Menu pages slow / PHP runs for every guest | Document root must be `backend/public` (3.2); `.htaccess` must be uploaded (hidden file). |
| `419` / "page expired" in the admin | `APP_URL` must match the address in the browser (https, www or not); see `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` in 3.4. |
| E-mails don't arrive | Settings → Email → **Send test email** shows the server's error; the cron line must run (queued invitations); check SPF/DKIM. |
| Photos fail to upload | `upload_max_filesize`/`post_max_size`, `memory_limit`, GD with WebP, `public/media/` writable. iPhone HEIC photos: the admin explains how to pick JPEG. |
| Menu shows old content | `php artisan menu:refresh-pages --all`; make sure no "Cache Everything" rule caches HTML (section 6). |
| QR links don't open | `/q/CODE` needs the `.htaccess` rule and `public/qr.php`; `storage/app/qr-map.php` must be writable (rebuilt automatically). |
