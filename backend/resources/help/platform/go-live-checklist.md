---
title: 10. Go-live checklist
topic: setup
summary: Tick these on the live server, in order.
order: 100
---

**Server**

1. PHP 8.3 with the extensions, OPcache on, upload limits set.
2. `backend/` outside `public_html`. Document root → `backend/public`. HTTPS works. `.htaccess` uploaded.
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, database, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` if behind a proxy.
4. `php artisan key:generate --force`, `migrate --force`, `platform:aggregate`, `optimize`.
5. **Never** run the demo seeder.
6. Folder permissions. `.env` (with `APP_KEY`) and `BACKUP_KEY` saved in your password manager.
7. Cron line every minute. `/up` says "Application up".
7a. `php artisan menu:doctor --production`: fix every red line (✗); it says how. Put **{{site_url}}/health** in an uptime monitor (UptimeRobot, Better Stack).

On a VPS, `scripts/deploy-vps.sh install` does steps 1–7 for you (docs/deploy.md section 4).

**Platform**

8. First Super Admin, two-factor on, recovery codes saved.
9. The setup wizard: name, logo, contact, plans and prices, e-mail test, sign-up mode, feature switches.
10. Read the Terms and Privacy texts and change them for your business. Ask a lawyer.
11. **Backups → Back up now**, then **Download** it once.

**Smoke test, on a phone and a computer**

12. Sign up with a real e-mail → confirm → set up 5 dishes, a logo, opening hours.
13. Download a QR code, print it, scan it with a phone.
14. Change a price. Open the menu again: the new price shows.
15. Record a test payment: the invoice e-mail arrives.
16. Delete the test restaurant.
