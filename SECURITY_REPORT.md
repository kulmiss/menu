# Security audit — Step 1 (no fixes yet)

Date: 2026-11-14. Scope: **kulmiss/menu** (Laravel 13 API + React admin + static customer menu), at `develop` 764925f, plus the open mobile PRs #92–#95. Also **kulmiss/menu-admin-app** (the Flutter app), at `feat/store`.
Checked against the OWASP Top 10 (2021), OWASP ASVS 4.0.3 Level 2 (web) and OWASP MASVS 2.x (app). I read the code, the config and the docs. I also ran `composer audit`, `pnpm audit`, `flutter pub outdated` and a scan of the git history for secrets.

Severity: **Critical**: can be exploited remotely without an account, or exposes many tenants at once. **High**: real account takeover or bulk data exposure, under realistic conditions. **Medium**: needs an extra condition (a stolen session, physical access, a misconfigured server) or the impact is limited. **Low**: hardening / defence in depth.

**Result: 0 critical, 4 high, 17 medium, 14 low.** The base is solid. Tenant isolation fails closed, invitation tokens are hashed, uploads are re-encoded and CSV export escapes formulas. The legal and campaign renderers escape their output, and "log in as" has strong safety blocks. The gaps are mostly the missing layers around that base: HTTP security headers, account-recovery hygiene, encrypted backups, and anti-automation.

---

## 1. Summary table

| ID | Sev | Area | Finding |
|---|---|---|---|
| H1 | High | Headers (A05) | No security headers at all: no CSP, HSTS, X-Frame-Options/frame-ancestors, nosniff, Referrer-/Permissions-Policy or COOP/CORP. This holds in Laravel, `.htaccess` and the Nginx example. |
| H2 | High | Auth (A07) | A password reset or a web password change keeps every mobile API token alive (60-day sliding, up to 365 days). |
| H3 | High | Account takeover (A07) | An e-mail change needs no password and sends no notice to the old address. The same applies in the mobile `account/profile` endpoint. |
| H4 | High | Backups (A02) | Backup zips (whole DB + photos) are not encrypted, locally or in the off-site S3 copy. |
| M1 | Med | Enumeration | `forgot-password` answers "We can't find a user with that e-mail". Mobile login and web login run faster for unknown e-mails, and sign-up behaves differently for an existing e-mail. |
| M2 | Med | Brute force | Login limits are keyed on *e-mail + IP* only. Spreading attempts across IPs means one account can be tried without limit. `forgot-password` has no IP limit. |
| M3 | Med | Passwords | Minimum 10 characters, no common/breached-password list. |
| M4 | Med | Notifications | No e-mail on password change, 2FA on/off, recovery-code regeneration or a new mobile sign-in. |
| M5 | Med | 2FA | Recovery codes are stored encrypted (Fortify default), not hashed. |
| M6 | Med | Uploads | No pixel limit before GD decodes an image, so a "decompression bomb" can exhaust PHP memory on shared hosting. |
| M7 | Med | SSRF | The SMTP host (any name or IP) and the S3 endpoint (http allowed, any host) can point at internal services. The SMTP test echoes the raw error. |
| M8 | Med | Cookies | `SESSION_SECURE_COOKIE` is off unless the operator sets it. There is no HSTS. |
| M9 | Med | Rate limits | Trusted proxies are not configured. Behind Cloudflare or a host proxy, every IP-based limit sees one IP, which means lockouts for everyone or no limits at all. |
| M10 | Med | Dependencies | `pnpm audit --prod`: 2 critical and 12 high. Most are build-time only (vite, postcss, nanoid, source-map-js). `seroval` (through TanStack Router) and `zod` ship in the bundle. |
| M11 | Med | Logging (A09) | No security events: failed logins, lockouts, 2FA failures, token revocations and password resets are not logged. |
| M12 | Med | CI | No `composer audit`, `pnpm audit`, secret scanner, security static analysis or authorization-matrix test in CI. Dependabot status unknown. |
| M13 | Med | App: screens | No `FLAG_SECURE`/iOS screen protection on the recovery codes, 2FA secret/QR or token screens. The app-switcher cover only appears when the app lock is on. |
| M14 | Med | App: clipboard | Copied recovery codes stay on the clipboard indefinitely. |
| M15 | Med | App: PIN | The app-lock PIN has no attempt limit or delay. |
| M16 | Med | App: privacy | The analytics off switch is not applied to Firebase at start-up, so automatic events still go out. Crashlytics has no off switch. |
| M17 | Med | App: Owner 2FA | Owners and Managers can run without 2FA. Account takeover of an Owner exposes the restaurant. **Ask-me item Q1.** |
| L1 | Low | CORS | No `config/cors.php`, so the framework default (`*` on `api/*`, no credentials) applies. |
| L2 | Low | Disclosure | No `/.well-known/security.txt`. |
| L3 | Low | Config | Nothing stops a production boot with `APP_DEBUG=true`. |
| L4 | Low | Errors | The SMTP test returns the raw transport error to the browser (Super Admin only). |
| L5 | Low | Uploads | `.htaccess`/Nginx would execute any `.php` under `public/`, including `media/` and `menu-cache/`. Today nothing can write one there, but there is no guard. |
| L6 | Low | Push | `push-tokens` moves an existing FCM token to the calling user. Someone who knows a victim's FCM token could receive that victim's notifications. |
| L7 | Low | Session | Session payload is not encrypted at rest. The remember-me cookie lasts 400 days. There is no "sign out other browsers" on the web. |
| L8 | Low | App: WebView | The live preview allows `data:` navigations. File access is not explicitly disabled, and the Android default is *on* for API 26–29 (minSdk 26). |
| L9 | Low | App: cache | The SQLite cache (menus, team e-mails, Super Admin lists) is not encrypted. It sits in the app sandbox, and backups are off. |
| L10 | Low | App: build | A release build silently falls back to the debug signing key when `key.properties` is missing. |
| L11 | Low | App: deps | Transitive `sqlite3` (bundled SQLite) is 3.5.2; 3.9.1 is available. Direct deps are up to date. |
| L12 | Low | App: hardening | No certificate pinning, no root/jailbreak warning, no Play Integrity. **Ask-me items Q2, Q3.** |
| L13 | Low | Authz | Staff can read the full menu tree, tags, add-on groups and the tenant summary (needed for the sold-out switch). This is intended, but no test pins it down. |
| L14 | Low | Secrets hygiene | The audit-log metadata is safe today (it records "password changed", not the value). Nothing *enforces* that secrets stay out of logs and `activity_log`. |

Passed / good already (kept as is): tenant scope **fails closed** (no tenant → exception), the tenant-isolation test suite, hashed, expiring single-use invitations, Laravel hashed reset tokens (60 min), and session regeneration on login (no fixation). Also: Sanctum `AuthenticateSession` logs other *web* sessions out on password change, and the mobile tokens have sliding expiry and per-device revoke. Uploads use a real-content MIME allow-list (JPEG/PNG/WebP), are re-encoded, get random names and have no SVG/HTML. CSV export escapes `= + - @`, the campaign Markdown escapes HTML and safe links, the legal renderer escapes, and JSON in `<script>` uses `JSON_HEX_TAG`. Public links are `https`-only and the SPA uses `safeRedirect`. SMTP/S3/FCM secrets are encrypted and never sent back. Backup downloads use 5-minute signed links behind password/2FA re-auth. "Log in as" keeps its 2-hour limit and blocked actions, and is not recorded. Guests get no cookies, no IP storage and no trackers. `composer audit` is clean and the git history contains no secrets. In the app: tokens live in Keychain/Keystore, a 401 wipes the tokens and cache, cleartext is off in release, `allowBackup` is off, R8 is on, deep links and push links are allow-listed, external links are http(s) only, and the WebView has no JS bridge.

---

## 2. Findings in detail

### H1 — No HTTP security headers (OWASP A05, ASVS V14.4)
**Where:** `bootstrap/app.php` (no header middleware), `public/.htaccess`, `docker/nginx/default.conf`. Only the appearance preview sets `X-Frame-Options`.
**Exploit:**
- **Clickjacking:** the admin can be framed by any site. A Super Admin can be tricked into clicking "Log in as", "Delete restaurant" or "Approve" inside an invisible frame. Owners can be tricked into "Delete category".
- **No HSTS:** the first visit or a typed `http://` address on restaurant Wi-Fi can be SSL-stripped, and with M8 the session cookie leaks.
- **No CSP:** any future XSS bug (or a malicious npm update) runs with full rights and can send data anywhere.
- **No nosniff:** browsers may sniff uploaded files.

**Fix:** one `SecurityHeaders` middleware for Laravel answers, plus the same headers in `.htaccess` (`mod_headers`) and the Nginx example for the static files. Static files are the guest menu, assets and media, which never reach PHP.
- Admin SPA: `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'`. The `style-src` allowance is for Radix inline styles; styles cannot run code. Also `img-src 'self' data: blob:; connect-src 'self'; frame-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'`. The Vite `index.html` has no inline scripts, so no nonce is needed.
- Customer menu (static file): `script-src 'sha256-…'` for the single inline script. The hash is computed when the page is built and checked in CI. The page already declares the hash, so there are no extra script bytes. Also `style-src` with the inline style's hash (the theme CSS differs per restaurant, so the hash goes in a `<meta http-equiv="Content-Security-Policy">` written into each static page, about 150 bytes of HTML), `img-src 'self' data:`, `connect-src 'self'`, `frame-ancestors 'none'` (header) and `worker-src 'self'` for `sw.js`.
- Everywhere: `Strict-Transport-Security: max-age=31536000; includeSubDomains` (HTTPS only, no `preload` until you decide), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()` (camera stays allowed only if the admin's QR scanner uses it; I will check). Also `Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Resource-Policy: same-origin` (`cross-origin` for `/media` so link previews still load). `X-Frame-Options: DENY` stays as a fallback.
- **Speed:** headers add about 0.6 KB per response and nothing to the page bytes. Lighthouse "Best practices" goes up. I will measure before and after in the guest-menu-speed job.

### H2 — Password reset does not revoke API tokens (A07, ASVS V3.3.3 / V2.5)
**Where:** `ResetUserPassword::reset`, `UpdateUserPassword::update` (the mobile `account/password` revokes them; the web forms don't).
**Exploit:** an attacker gets the password (phishing, reuse) and signs in on the app, which gives them a 60-day token that renews on use. The owner notices and resets the password on the website. The attacker's token keeps working for up to 365 days.
**Fix:** after a reset, delete all of the user's `personal_access_tokens` and `push_tokens`. After a web password change, delete all of them except the current session. Send a notice (M4). Add a test for each.

### H3 — E-mail change without password and without an old-address notice (A07, ASVS V2.5.x, V3.7.1)
**Where:** `UpdateUserProfileInformation` (Fortify `PUT user/profile-information`) and mobile `PUT account/profile`.
**Exploit:** someone has a session for a few minutes (an unlocked laptop, a stolen app token, XSS). They change the e-mail to their own and confirm it, then use "Forgot password" on the new address and take the account permanently. The owner gets no warning.
**Fix:** require `current_password` for an e-mail change (web and mobile). Keep the old address active until the *new* one is confirmed through a signed, single-use 60-minute link. Send the *old* address a "your e-mail is being changed — not you? secure your account" notice. On confirmation, revoke the other tokens. *This changes user behaviour slightly (a password prompt).* I consider it covered by the brief ("email change requires password"), so no question is needed.

### H4 — Backups not encrypted at rest (A02, ASVS V6.1 / V8.1)
**Where:** `app/Support/Backups/*` writes a plain zip to `storage/app/private/backups` and optionally to S3.
**Exploit:** a leaked bucket key or a misconfigured bucket (public R2/B2 bucket, a shared Backblaze key), or a hosting-account compromise, exposes every restaurant's data at once. That data includes owner e-mails, password hashes, invoices and encrypted 2FA secrets, which can be decrypted if `APP_KEY` leaks too.
**Fix:** encrypt each zip with **libsodium secretstream** (built into PHP; streaming, so memory stays flat and no daemon is needed). Use a separate key `BACKUP_KEY` that lives only in `.env` and never in the backup. The download stays a 5-minute signed link plus re-auth. The restore doc gets a `php artisan backup:decrypt` step. The Backups screen warns: "write BACKUP_KEY down outside the server, or the backups can't be restored". Old plain zips are kept but marked "not encrypted". *Server to-do: store the key in a password manager.*

### M1 — User enumeration (ASVS V2.2.x / V3.x)
- `POST /api/v1/auth/forgot-password` returns 422 "We can't find a user with that email address" for unknown e-mails. **Fix:** always answer "If an account exists, we sent a link" (override Fortify's failed-response binding), still with the per-user broker throttle.
- Mobile `auth/login`: an unknown e-mail skips `Hash::check`, so it answers about 80 ms faster. The web login probably behaves the same way; I will confirm with a test. **Fix:** check against a dummy hash when there is no user, or use `Timebox`, so both take the same time.
- Sign-up: for an existing e-mail it sends a notice (good) but doesn't log in, while a new e-mail gets a session cookie, so a script can tell them apart. **Fix:** make both answers identical (no session until the e-mail link is clicked, or a session in both cases). I'll pick the option that keeps the current sign-up flow.

### M2 — Brute force and anti-automation (ASVS V2.2.1, V11.1.4)
Limits today: web/mobile login 5/min per e-mail+IP (+30/min per IP on mobile), 2FA 5/min per challenge/session, sign-up 10/IP + 3/e-mail, slug check 30/min, SMTP test 5/min, beacon 120/min. Missing: a **per-account** limit across IPs, an IP limit on `forgot-password` and `reset-password`, limits on CSV/PDF exports and table-card PDFs (CPU-heavy), and on platform search (120/min is fine).
**Fix:** add `Limit::perHour(20)->by('acct:'.email)` with a temporary lock and a "too many attempts, wait N minutes" message. It never locks permanently, so it can't be abused for a DoS lasting more than an hour. Add `throttle:5,1` to forgot/reset per IP and e-mail, `throttle:10,1` to exports and PDFs, and a test per limit. The QR redirect runs without PHP, so no limit is needed. Leave that to the server or Cloudflare.

### M3 — Password rules (ASVS V2.1.7)
**Fix:** keep the minimum of 10 and add a **local** list of the top ~100k common and breached passwords. That is a gzip file of about 300 KB in `storage/`, checked only when a password is set, so the cost is zero elsewhere. Add `Password::uncompromised()`? No: that calls an external API, so I'll use the local list only. It applies to *new* passwords only. **Q4** asks about existing users.

### M4 — Security notifications (ASVS V2.2.3, V2.5.x)
**Fix:** queued e-mails (queue already exists; works with `sync`/cron on cPanel) for: password changed/reset, e-mail change requested (to the old address), 2FA on/off, recovery codes regenerated, and a new device signed in to the app (device name, not IP — keeps the "no IP storage" stance). Each mail has a "not you?" link to the account page.

### M5 — Recovery codes encrypted, not hashed (ASVS V2.6.2)
**Exploit:** a DB dump plus `APP_KEY` (both in one cPanel account) gives working 2FA bypass codes for every user.
**Fix:** store `bcrypt`/sha256-HMAC hashes of the codes. Show them once; using one removes it. Fortify's `RecoveryCode`/`two_factor_recovery_codes` handling needs a small custom provider. Existing codes are migrated by hashing the decrypted values (one migration, so no user action). There is a risk to the existing recovery flow, so a test covers web, mobile and the backup re-auth.

### M6 — Image decompression bomb (ASVS V12.1.x)
**Where:** `ImageProcessor::load` and `EmailLogo::store` call `imagecreatefromstring` on any file ≤ 8 MB.
**Exploit:** a staff-level account uploads a 40 KB PNG of 30 000 × 30 000 px. GD tries to allocate about 3.6 GB and the PHP worker dies. Repeating it ties up shared-hosting workers.
**Fix:** run `getimagesize()` first (reads only the header) and reject anything above 40 MP or with a side above 10 000 px, with a clear message. No speed cost.

### M7 — SSRF via SMTP and S3 settings (A10)
**Exploit (needs a Super Admin session):** set the SMTP host to `127.0.0.1`/`10.x`/`169.254.169.254` and the port to 22/3306/6379, then read the echoed error to map the internal network. Set an S3 endpoint `http://internal-host/…` to send the backup there in cleartext.
**Fix:** resolve the host and refuse private, loopback, link-local and reserved ranges (IPv4 and IPv6). Allow SMTP ports 25/465/587/2525 only, require `https` for S3, show a generic "could not connect (code)" instead of the raw error, and log the full error server-side.

### M8 — Secure cookies not forced (ASVS V3.4.1)
**Fix:** default `secure` to `true` when `APP_URL` starts with `https://`, and add HSTS (H1). `SameSite=Lax` and `HttpOnly` are already right. `XSRF-TOKEN` stays readable by JS by design.

### M9 — Proxies
**Fix:** `$middleware->trustProxies(at: env('TRUSTED_PROXIES'))` plus a deploy-doc line: "behind Cloudflare set `TRUSTED_PROXIES=*` or the CF ranges". The `/up` page warns when every request arrives from one proxy IP. No speed cost.

### M10 — Frontend dependencies
`pnpm audit --prod` gives 22 advisories (2 critical, 12 high). Most are in **build tools** (`vite`, `postcss`, `nanoid`, `source-map-js`) listed under `dependencies` through `@tailwindcss/vite`. The shipped ones are `seroval` (TanStack Router; its `fromJSON` isn't fed attacker input in an SPA without SSR, so practical risk is low) and `zod` (DoS on oversized input). **Fix:** update TanStack Router, zod, vite and @tailwindcss/vite. Move build tools to `devDependencies`. Use `pnpm.overrides` for anything that can't update. Add `pnpm audit --prod --audit-level high` in CI. Re-check the 240 KB admin budget after the update.

### M11 — Security event logging (A09, ASVS V7.1–7.2)
**Fix:** one `security` log channel (daily file, 30 days, no extra daemon). It records: login failed, account locked, 2FA failed, password reset requested and done, e-mail change, token revoked, sign-up blocked and a refused upload. Entries carry user id and event; the IP is stored as the existing salted `IpHash`, not the raw IP. No passwords, tokens or e-mail bodies. "Log in as" stays unrecorded, as you chose. An optional Super Admin "Security events" list can come later.

### M12 — CI security checks (minutes-friendly)
**Fix, in the existing `ci.yml` jobs:** `composer audit` and `pnpm audit --prod --audit-level high` (a few seconds each, no new job). Add `gitleaks` (one step on PRs, scanning only the diff) and a Larastan security ruleset or `psalm --taint-analysis` (only on PRs that touch `backend/`). Add Pest **authorization-matrix tests** (every route × guest/Staff/Manager/Owner/other Owner/Super Admin, generated from `route:list`, so new routes fail CI until they are classified). A separate `security-weekly.yml` runs the OWASP ZAP baseline against a local Nginx copy (weekly cron and manual trigger). App repo: `flutter pub outdated` plus gitleaks in the existing job. **Dependabot and secret scanning:** both repos are public, so secret scanning with push protection and Dependabot alerts are free. Turn them on in *Settings → Code security → Dependabot alerts: Enable; Secret scanning: Enable; Push protection: Enable*. I'll add a `.github/dependabot.yml` (monthly, grouped, so few PRs).

### M13 — App: screen protection (MASVS-PLATFORM-3, MASVS-STORAGE)
**Fix:** a tiny platform channel that sets `FLAG_SECURE` only while the recovery codes, 2FA setup or "log in as" screens are open, plus an iOS blur on resign-active for those screens. Show the app-switcher cover for those screens even when the app lock is off.

### M14 — App: clipboard
**Fix:** after copying recovery codes (or the 2FA secret), clear the clipboard after 60 s if it still holds them. On Android 13+ mark the clip `IS_SENSITIVE` so the system preview hides it.

### M15 — App: PIN attempts (MASVS-AUTH)
**Fix:** after 5 wrong PINs add a 30 s → 5 min wait (stored in secure storage, survives a restart). After 10, sign out and wipe the cache, so the server password is needed again. Increase PBKDF2 from 20 000 to 100 000 iterations; that measures at about 60 ms on a mid-range phone and still feels instant. I'll check it against the cold-start budget.

### M16 — App: analytics/Crashlytics off switch (MASVS-PRIVACY)
**Fix:** set `firebase_analytics_collection_enabled=false` (Android meta-data / iOS plist) so nothing is sent before the app reads the user's choice. Then call `setAnalyticsCollectionEnabled(enabled)` on start. Add a "Send crash reports" switch next to analytics. Crash reports drop request URLs and query strings, and `reportNonFatal` (currently unused) sends only the status code and route name.

### M17 — 2FA for Owners
Super Admin 2FA stays required. See **Q1**.

### Low items — fixes
- **L1:** add `config/cors.php` with no cross-origin access (`allowed_origins` empty; the admin and the app don't need CORS).
- **L2:** add `public/.well-known/security.txt` (Contact: mailto:info@kulmiss.com, Expires, Preferred-Languages: en). Nginx already allows `/.well-known`.
- **L3:** add a boot check: in production with `APP_DEBUG=true`, refuse to render traces and log an error. Show it on `/up` and the Super Admin dashboard.
- **L4:** covered by the M7 fix.
- **L5:** in `.htaccess`, add `<FilesMatch "\.(php|phtml|phar)$">Require all denied</FilesMatch>` in `media/` and `menu-cache/`. In Nginx, allow only `index.php` and `qr.php` in the `\.php$` location.
- **L6:** only move a push token when the request also proves possession; the app sends the token's install-id. Otherwise refuse with 409.
- **L7:** set `SESSION_ENCRYPT=true` (about 0.1 ms per request; I'll measure it), cut the remember-me lifetime to 30 days, and add "Sign out of other browsers" to Settings → Security (password required).
- **L8:** add `setAllowFileAccess(false)`/`allowsContentAccess=false` via `webview_flutter_android` and allow only `about:blank` navigations. Mixed content is already blocked by the `https` base URL.
- **L9:** keep the cache unencrypted (sandbox, no backup, wiped on sign-out). Cache the Super Admin lists (payments, invoices) in memory only. SQLCipher would add about 3 MB and slow the cold start.
- **L10:** fail the release build when the signing key is missing (CI artifacts become `--debug` builds instead).
- **L11:** `flutter pub upgrade` for the transitive packages and commit the lockfile.
- **L12:** add a root/jailbreak **warning only** (one banner, no blocking), using a small local check with no new SDK. See Q2/Q3 for pinning and Play Integrity.
- **L13:** the authorization-matrix tests pin the Staff read access down.
- **L14:** a test fails if a password, token, secret or recovery code appears in `activity_log` metadata or in the security log.

---

## 3. Authorization matrix (what Step 2 will test)
There are 229 routes. Groups and the expected answer per role (✓ allowed, ✗ 403/404, – not applicable):

| Group | Guest | Staff | Manager | Owner | Other restaurant's Owner | Super Admin |
|---|---|---|---|---|---|---|
| Public pages, menu, QR, beacon, signup/config | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/api/v1/me`, own account, own devices/push | ✗ 401 | ✓ own | ✓ own | ✓ own | ✓ own | ✓ own |
| `t/{tenant}` read menu (items, categories, tags, add-ons) | ✗ | ✓ | ✓ | ✓ | ✗ 404 | ✓ (via "log in as" only) |
| `t/{tenant}` sold-out toggles | ✗ | ✓ | ✓ | ✓ | ✗ | – |
| `t/{tenant}` menu/restaurant edit, branches, QR, promotions, insights | ✗ | ✗ | ✓ | ✓ | ✗ | – |
| Prices / price periods | ✗ | ✗ | ✓ if "can edit prices" | ✓ | ✗ | – |
| Team, invitations | ✗ | ✗ | ✓ lower roles | ✓ | ✗ | – |
| Billing, invoices PDF, account/slug, delete | ✗ | ✗ | ✗ | ✓ | ✗ | – |
| `platform/*` | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ (2FA on) |
| Blocked during "log in as" (password, 2FA, e-mail, delete, billing…) | – | – | – | ✗ when impersonated | – | – |

Object level (IDOR): every `{id}` inside `t/{tenant}` is resolved under the tenant scope (fail-closed), so another restaurant's id gives 404. This covers images/media, branch tables, QR PDFs, invoice PDFs, CSV/XLSX exports, bulk actions (`items/bulk`, `bulk-price`) and price periods. Signed URLs (backup file, unsubscribe, e-mail verify) carry a signature and expiry where needed. Queue jobs (campaigns, push, insights reports) set the tenant explicitly. The matrix test checks each of these with two restaurants.

---

## 4. Questions for you (Step 2 waits for these)

**Q1 — 2FA for Owners.** Options: (a) **strongly encourage**: a dashboard card until it's on, a one-tap setup in web and app, and a reminder e-mail after 7 days; (b) require it for Owners (Managers and Staff optional); (c) leave it as now. **My recommendation: (a)** now, with (b) as a Super Admin switch per plan you can turn on later. Forcing it on small restaurants creates "I lost my phone" support work, and a lockout stops them editing the menu.

**Q2 — Certificate pinning in the app.** Risk: on cPanel AutoSSL/Let's Encrypt the certificate, and often its key, changes every 60–90 days, and the intermediate CA changes too (R10/R11 → others). A pin on the leaf or intermediate would lock every app user out until they update from the store. A safe option: pin the **public keys of the root CAs** (ISRG Root X1 + X2, plus a backup root from a second CA you might move to). Add a **remote kill switch** in `mobile/config` (`pinning: off`), fetched without pinning, and fail *open* with a warning if the config says so. The default would be off in the first release, then on. **My recommendation: don't enable pinning yet.** The app already rejects cleartext and uses system TLS validation. The added protection (against a mis-issued certificate or a user-installed CA) is small compared with the lock-out risk on shared hosting. If you want it, I'll build it with root pins and the kill switch, default off.

**Q3 — Play Integrity API.** It needs a Google Cloud project (free up to 10 000 checks a day), a server-side verification endpoint and a decision on what to do with failures. The server already enforces every permission, so integrity only slows down scripted abuse of the API. **My recommendation: no for now.** Add only the root/jailbreak **warning** (L12).

**Q4 — Stricter password rules for existing users.** **My recommendation:** apply the common-password list to new and changed passwords only. No forced reset. A gentle "your password is on a common-password list, please change it" banner appears after sign-in.

**Q5 — Log out sessions when the fixes ship?** **My recommendation: no global logout.** The new rules (H2/H3) revoke tokens on the next password reset or e-mail change.

---

## 5. Speed baseline (before) and how Step 2 keeps it
From `docs/final-report.md` and the last green CI:

| Budget | Now |
|---|---|
| Guest menu Lighthouse (mobile) | 100 in all styles |
| Guest LCP (CI limit 1.3 s, target 1.2 s) | 0.98–1.28 s (median of 5) |
| Guest page before images (≤ 30 KB) | 19.9 KB gzip |
| Inline script (≤ 5 KB) | 4.80 KB |
| Admin first load (≤ 240 KB) | 225.0 KB gzip |
| Dashboard/API latency | unchanged baseline taken from the Pest timing and a local `ab` run before Step 2 |

Rules for Step 2:
- Headers and CSP add no bytes to the page body; the per-page CSP `<meta>` is about 150 B of HTML.
- No new JS in the guest page.
- The common-password check runs only when a password is set.
- Backup encryption streams the data.
- `getimagesize` runs before decoding.
- Rate-limit checks use the existing cache store.

I'll re-run the guest-menu-speed job and the admin bundle budget on each PR and put the numbers in the final report.

## 6. Proposed PR order (Step 2)
**kulmiss/menu**:
1. Security headers + CSP + cookie defaults + trusted proxies + CORS + security.txt (H1, M8, M9, L1, L2, L5).
2. Token revocation on reset/password change + notifications (H2, M4).
3. E-mail change flow (H3).
4. Encrypted backups (H4).
5. Enumeration + rate limits + per-account lock (M1, M2).
6. Password list (M3).
7. Hashed recovery codes (M5).
8. Upload pixel limit (M6).
9. SSRF guards (M7, L4).
10. Security log + no-secrets test (M11, L14).
11. Dependency updates (M10).
12. CI: audits, gitleaks, authorization matrix, weekly ZAP, Dependabot (M12, L13).
13. Session encryption + "sign out other browsers" + APP_DEBUG check (L3, L6, L7).
14. Privacy Policy default text (update-only-if-unedited) for the security log and new-device e-mails.

**kulmiss/menu-admin-app**:
1. Screen protection + clipboard (M13, M14).
2. PIN limits (M15).
3. Analytics/Crashlytics off switch (M16).
4. WebView + release signing + deps (L8, L10, L11).
5. Root warning (L12).
6. CI: gitleaks + Dependabot.
