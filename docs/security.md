# Security: what the app does and what the server must do

Background: [SECURITY_REPORT.md](../SECURITY_REPORT.md) (audit, 2026-11-14). This page is the
short operator view; each section names the finding it closes.

## Security headers (H1)
- Laravel adds them to its own answers (`App\Http\Middleware\SecurityHeaders`):
  `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options: DENY`,
  `Cross-Origin-Opener-Policy`, and a strict `Content-Security-Policy`:
  - admin and site pages allow scripts only from this site, plus Cloudflare Turnstile on sign-up;
  - inline scripts on the site pages use a fresh nonce on every request;
  - API answers send `default-src 'none'`.
- Guest menu pages carry their policy in a `<meta>` tag in the page itself, so the static copy
  (served without PHP) has it too. The one inline script is allowed by its SHA-256 hash.
  The page can still be embedded in a restaurant's own website (no `frame-ancestors`).
- Static files: `public/.htaccess` (Apache/cPanel) and `docker/nginx/security-headers.conf` (Nginx)
  send the same headers. **Nginx:** keep the `include` line in every `location` that has its own
  `add_header`, because Nginx drops the server-level ones there.
- **HSTS** (`Strict-Transport-Security: max-age=31536000`) is sent only over HTTPS, by Laravel and
  by `.htaccess`. For Nginx, uncomment the line in `security-headers.conf` once HTTPS works.
  `SECURITY_HSTS=false` turns it off in Laravel. `SECURITY_HSTS_SUBDOMAINS=true` adds `includeSubDomains`
  (only if every subdomain has HTTPS).
- **Check after deploy:** `curl -sI https://YOUR-DOMAIN/ | grep -i -E 'content-security|strict-transport|nosniff'`
  and the same for a menu page (`/your-restaurant`).

## Cookies (M8)
- The session cookie is `Secure` by default when `APP_URL` starts with `https://`
  (`SESSION_SECURE_COOKIE` still overrides it). It is also `HttpOnly` and `SameSite=Lax`.

## Backups (H4)
- Set **`BACKUP_KEY`** and every backup zip is encrypted (`backup-….zip.enc`, libsodium XChaCha20-Poly1305,
  streamed, so memory use stays flat). The key is never inside a backup.
  ```
  php artisan backup:key           # prints a new key
  php artisan backup:key --write   # …and adds it to .env (only if there is none yet)
  php artisan config:cache         # if you cache the config
  ```
- **Keep a copy of the key outside the server** (password manager). Without it no backup can be restored.
  If the key changes, old backups still need the old key.
- Without a key, backups stay plain zips, the Backups screen shows a warning, and **no off-site copy** is made.
  Every restaurant's data never goes to a third party unencrypted.
- Restore: `php artisan backup:decrypt storage/app/backups/backup-….zip.enc` writes the `.zip` next to it
  (or `--key=base64:…` on another machine). Then follow docs/deploy.md, "Restoring a backup".
- Downloads keep the 5-minute signed link and the password / two-factor re-check.

## Server settings (M9, L1, L2, L3, L5)
- **Behind Cloudflare or a host proxy:** set `TRUSTED_PROXIES=*` (or the proxy IPs, comma-separated) in `.env`.
  Then rate limits and "is this HTTPS?" see the real visitor. Without a proxy, leave it empty: forwarded
  headers are ignored, so nobody can fake their address.
- **CORS:** off. Other websites can't read the API from a browser. The admin and the app don't need it.
- **`/.well-known/security.txt`** names info@kulmiss.com. It must be renewed yearly: a test fails 12 months
  after the `Expires` date was set, as a reminder.
- **`APP_DEBUG=true` on a live site** is turned off automatically and logged as critical once a day.
  Still fix `.env`.
- **No PHP runs from `media/`, `menu-cache/`, `menu-assets/`, `app/`, `images/`, `landing/`**
  (`.htaccess` answers 403). The Nginx example runs only `index.php` and `qr.php`.
- File permissions (cPanel): folders `755`, files `644`, `.env` `600`, `storage/` and `bootstrap/cache/`
  writable by your user only. The document root must be `backend/public` (docs/deploy.md).

## Security log (M11)
- `storage/logs/security-YYYY-MM-DD.log`, kept 30 days (`SECURITY_LOG_DAYS`). One line per event:
  `login.failed`, `login.locked`, `two_factor.failed`, `password.reset_requested`, `password.reset`,
  `password.changed`, `email_change.requested`, `email_change.confirmed`, `tokens.revoked`,
  `signup.blocked`, `upload.refused`, `outbound.refused`.
- Each line holds only the user id (when known) and short keyed hashes of the e-mail and the IP address, so
  repeats show up without storing personal data. It never holds passwords, codes, tokens or addresses (a test
  checks this). "Log in as" is not recorded (owner's choice).
- Quick look on cPanel: *File Manager → storage/logs*, or `grep login.locked storage/logs/security-*.log`.

## Sessions (L7)
- **Settings → Security → "Sign out of all other browsers"** (password needed): every other website
  session and "remember me" cookie ends, this browser stays. App devices: Account → Devices in the app.
- New installs encrypt the stored session data (`SESSION_ENCRYPT=true` in `.env.example`). **On a running
  site, switching it on signs everyone out once**, so do it at a quiet moment (or leave it: sessions are in the
  database, which the backups now encrypt).
