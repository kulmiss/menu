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
