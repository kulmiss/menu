# Security hardening — final report (Step 2)

Date: 2026-11-14. Audit: [SECURITY_REPORT.md](SECURITY_REPORT.md). Operator notes: [docs/security.md](docs/security.md).
Your answers were applied:
- Q1, Owners' two-factor: strongly encourage, plus a Super Admin switch that can require it (off);
- Q2, certificate pinning: no;
- Q3, Play Integrity: no, only a warning on rooted or jailbroken phones;
- Q4, stricter passwords: new and changed passwords only, plus a banner;
- Q5: nobody is signed out.

**Result:** all 4 high and all 17 medium findings are fixed. 12 of 14 low findings are fixed; L6 (push token) and L9 (app cache) are accepted with reasons, and L4 is mitigated (see "Remaining risks"). Every PR has tests, every finished CI run is green, and the speed budgets hold.

## 1. Score per area (0–10, before → after)

| Area | Before | After | What changed |
|---|---|---|---|
| Authorization / IDOR | 8 | 9.5 | Route-by-route matrix test: new routes fail CI until classified (#112) |
| Authentication, brute force | 6 | 9 | Per-account limit across IPs, reset limits, same timing for unknown e-mails (#102) |
| Account takeover | 4 | 9 | E-mail change needs the password, a new-address link and an old-address notice (#100); a reset revokes app tokens (#99); "was this you?" e-mails (#104); sign out other browsers (#113) |
| User enumeration | 5 | 8.5 | Generic forgot-password answer, constant-time sign-in (#102). Sign-up kept, rate-limited |
| Passwords | 6 | 8.5 | Local common/breached list for new passwords, banner for old ones (#103) |
| Two-factor | 7 | 9 | Hashed, single-use recovery codes (#105); Owner encouragement plus optional requirement (#111) |
| HTTP headers / CSP | 1 | 9 | HSTS, CSP (nonce/hash), XFO, nosniff, Referrer, Permissions, COOP, CORP, also in `.htaccess` and Nginx (#98) |
| Cookies, CSRF, CORS | 6 | 9 | Secure cookie on https (#98), CORS off (#108) |
| Uploads | 7 | 9 | Decompression-bomb guard (#106), no PHP in upload folders (#108) |
| SSRF | 5 | 9 | Private hosts and non-mail ports refused, https-only S3, checked again before connecting (#107) |
| Backups | 4 | 9 | Encrypted with `BACKUP_KEY`, never off-site unencrypted (#101) |
| Logging | 3 | 8 | Security log without personal data or secrets (#110) |
| Error handling, config | 7 | 9 | `APP_DEBUG` guard, trusted proxies, `security.txt` (#108) |
| Dependencies | 5 | 9 | 0 known issues in shipped JS, `composer`/`pnpm` audit in CI, Dependabot (#97, #109) |
| CI security | 3 | 8.5 | gitleaks, audits, authorization matrix, weekly ZAP (#97, #112) |
| App: storage and screens | 6 | 9 | FLAG_SECURE / cover on secret screens, clipboard cleared (#5) |
| App: local auth | 6 | 9 | PIN waits and sign-out after 10 wrong tries (#6) |
| App: privacy | 6 | 9 | Nothing sent to Firebase before consent, crash switch, scrubbed reports (#7) |
| App: platform / WebView | 7 | 9 | WebView locked down, release signing enforced (#8) |
| App: resilience | 4 | 6 | Root/jailbreak warning (#9). No pinning or Play Integrity, by your choice |
| App: network | 7 | 7 | Unchanged: HTTPS only, system TLS validation (pinning declined) |

## 2. PRs in merge order

Merge in this order. After each merge, press **Delete branch**; the next PR then moves to `develop` by itself. If GitHub shows a conflict (most likely in `docs/deploy.md` or `frontend/src/locales/en/platform.json` after #87–#91), tell me and I'll rebase.

**kulmiss/menu**:
1. Your open PRs #87–#91 (independent) and the mobile stack #92 → #93 → #94 → #95.
2. #96 docs: security audit (independent, any time).
3. #97 CI: lean jobs, gitleaks, composer audit, Dependabot.
4. #98 headers + CSP, Secure cookie (H1, M8).
5. #99 a password reset or change revokes app tokens (H2).
6. #100 safe e-mail change (H3).
7. #101 encrypted backups (H4).
8. #102 no enumeration, per-account limits (M1, M2).
9. #103 common-password list + banner (M3).
10. #104 account-change e-mails (M4).
11. #105 hashed recovery codes (M5).
12. #106 image bomb guard (M6).
13. #107 SSRF guards (M7, L4).
14. #108 server config: proxies, CORS, security.txt, APP_DEBUG, no PHP in uploads (M9, L1–L3, L5).
15. #109 dependency updates + pnpm audit (M10).
16. #110 security log (M11, L14).
17. #111 Owner two-factor: encourage plus switch (M17).
18. #112 authorization matrix + weekly ZAP (M12, L13).
19. #113 sign out other browsers (L7).
20. #114 Privacy Policy default text (update-only-if-unedited).
21. This report.

**kulmiss/menu-admin-app**: your #1 → #2 → #3, then:
1. #4 CI: stacked PRs, gitleaks, leaner Android build, Dependabot.
2. #5 secret screens and clipboard (M13, M14).
3. #6 PIN limits (M15).
4. #7 telemetry switches (M16).
5. #8 WebView, signing, updates (L8, L10, L11).
6. #9 root/jailbreak warning (L12).
7. #10 app support for e-mail change, weak password and Owner two-factor (H3, M3, M17). Release it together with or after the server PRs.

## 3. Speed before → after

**Guest menu** (CI Lighthouse, mobile, 4G, 4× CPU, median of 5 runs). Before = #97, after = #109 (which contains every change up to and including #108; #109–#114 don't touch the guest page):

| Style | LCP ms | TBT ms | Score | Page gzip |
|---|---|---|---|---|
| bistro, banner | 977 → 1276 | 0 → 1 | 100 → 100 | 19.6 → 19.7 KB |
| bistro, no-banner | 902 → 1051 | 0 → 0 | 100 → 100 | 19.4 → 19.5 KB |
| editorial, banner | 1276 → 1053 | 0 → 3 | 100 → 100 | 19.7 → 19.8 KB |
| editorial, no-banner | 902 → 976 | 15 → 20 | 100 → 100 | 19.5 → 19.7 KB |
| editorial, no-banner-no-specials | 1202 → 1050 | 78 → 17 | 100 → 100 | 19.2 → 19.4 KB |
| grid, banner | 1276 → 1053 | 0 → 12 | 100 → 100 | 19.7 → 19.8 KB |
| grid, no-banner | 902 → 903 | 0 → 6 | 100 → 100 | 19.5 → 19.6 KB |
| grid, price-period | 1127 → 1053 | 0 → 0 | 100 → 100 | 19.8 → 19.9 KB |
| minimal, banner | 1116 → 976 | 0 → 0 | 100 → 100 | 14.6 → 14.8 KB |
| minimal, no-banner | 783 → 840 | 0 → 0 | 100 → 100 | 14.2 → 14.4 KB |

- The mean LCP over the 10 cases went from **1046 ms to 1023 ms**. Lighthouse's simulation puts LCP into a few steps (≈ 0.9 / 1.05 / 1.13 / 1.28 s), so a single case can move a step either way with the same code. Every run is below the 1.3 s CI limit, and every score is **100**.
- The page grew by **≈ 0.17 KB gzip** (the CSP `<meta>`), to 14.4–19.9 KB of the 30 KB budget. The inline script is unchanged at **4.80 KB** of 5 KB, and there's still one script and no third parties.
- **Admin first load:** 227.6 KB → **228.5 KB** gzip (budget 240 KB). The two-factor card and the gate are lazy-loaded.
- **API latency** (local, `php -S`, 60 requests per endpoint, median):

  | Endpoint | Before | After |
  |---|---|---|
  | `/me` | 12.6 ms | 11.4 ms |
  | `t/{id}/items` | 18.5 ms | 15.7 ms |
  | `t/{id}/dashboard` | 13.2 ms | 11.1 ms |
  | `t/{id}/menus` | 12.5 ms | 13.2 ms |

  The differences are within run-to-run noise (±3 ms). The only request-path additions are headers and, on sign-in, one cache counter.
- **App:** the unlock speed is unchanged (PBKDF2 was kept at 20,000 on purpose), and the cold start is unchanged because all checks run after the first frame.

## 4. Remaining risks (accepted or for later)

- **Certificate pinning: off**, your choice (Q2). The app uses HTTPS only with system TLS validation. A mis-issued certificate or a user-installed CA could intercept traffic. If you want pinning later, I'd pin root CAs with backup pins and a kill switch in `mobile/config`.
- **No Play Integrity**, your choice (Q3). A modified app can talk to the API, but the server enforces every permission.
- **"Remember me" lasts 400 days** (Laravel default). Shortening it (to 30 or 90 days) would make owners sign in more often. **Your call.**
- **Sign-up can hint that an e-mail exists** (a new account gets a session at once). This is accepted: it's limited to 3 per e-mail and 10 per IP, with optional Turnstile.
- **CSP keeps `style-src 'unsafe-inline'`** (Radix inline styles and photo placeholders). Scripts are strict (nonce or hash), and CSS can't run code.
- **Guest menus can be embedded** on other sites (no `frame-ancestors`), so restaurants can put their menu on their own website. The menu has no actions worth clickjacking.
- **App cache (SQLite) is not encrypted.** It sits in the app sandbox with backups off, and is wiped at sign-out and on 401. SQLCipher would add about 3 MB and slow the cold start.
- **A push token moves to whoever registers it.** Only the phone holding an FCM token knows it.
- **Existing servers keep `SESSION_ENCRYPT=false`** (switching it on signs everyone out once; see docs/security.md).
- **The weekly ZAP scan runs on the default branch only** (GitHub rule), so it starts after the merge to `main`. It can also be started by hand from the Actions tab.
- **Secret scanning on private repos:** GitHub's version isn't free, so gitleaks runs in CI instead, plus the optional local hook `scripts/pre-commit-secrets.sh`.

## 5. Server to-dos (cPanel)

1. **HTTPS on** (AutoSSL). Then check: `curl -sI https://YOUR-DOMAIN/ | grep -iE 'strict-transport|content-security|nosniff'`.
2. **`.env`:**
   - `APP_ENV=production`, `APP_DEBUG=false` and an https `APP_URL` (the Secure cookie and HSTS follow from it);
   - behind Cloudflare, `TRUSTED_PROXIES=*`;
   - then run `php artisan config:cache`.
3. **Backup key:** `php artisan backup:key --write`, then **save the key in a password manager** (without it no backup can be restored). Then press "Back up now" and check that the Backups screen shows no warning. Turn on the off-site copy only after this.
4. **Migrations:** `php artisan migrate --force`. This adds the new columns, hashes the existing recovery codes, and updates the Privacy Policy only if it's the unedited default.
5. **File permissions:** folders 755, files 644, `.env` 600; `storage/` and `bootstrap/cache/` writable only by your user; document root `backend/public`.
6. **Cron:** unchanged (`schedule:run` every minute). The new daily job sends the one two-factor reminder.
7. **GitHub:** in each repo, Settings → Advanced Security → turn on **Dependency graph, Dependabot alerts, Dependabot security updates**.
8. **Yearly:** move the `Expires` date in `public/.well-known/security.txt` (a test fails as a reminder).
9. **Later, optional:**
   - turn on "Require two-factor sign-in for restaurant owners" (Super Admin → Settings → Sign-up);
   - set `SESSION_ENCRYPT=true` at a quiet moment (signs everyone out once);
   - Nginx only: uncomment HSTS in `docker/nginx/security-headers.conf`.
10. **App store build:** `android/key.properties` must exist; a release build now stops without it.
