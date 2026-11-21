# Launch to-do — only the things that need you

Everything else is built and tested. Tick these in order. Details: `docs/deploy.md` (section numbers below).

## 1. Before the server

- [ ] **Merge** the top PR into `develop` (then the release PR `develop` → `main`) and **protect `main`** on GitHub (Settings → Branches: require a pull request, no force push, no deletion). Claude's access can't change branch settings.
- [ ] **Domain:** buy it (or use a subdomain such as `menu.yourcompany.com`).
- [ ] **Hosting:** either a small VPS with Ubuntu 24.04 (1 vCPU, 1–2 GB RAM; recommended) or cPanel hosting with PHP 8.3, MySQL 8 and SSH/Terminal.
- [ ] **DNS:** an **A record** for the domain → the server's IP address.

## 2. Install (about 15 minutes)

- [ ] **VPS:** on your computer (Windows: in **Git Bash**) `bash scripts/make-release.sh`, copy `menu-release.tar.gz` and `scripts/deploy-vps.sh` to the server, then
  `sudo DOMAIN=your-domain EMAIL=you@example.com bash deploy-vps.sh install menu-release.tar.gz` (section 4).
  **cPanel:** follow section 3.
- [ ] **Save in your password manager:** the database password and Super Admin address the script prints, and a copy of `.env` (it holds `APP_KEY` and `BACKUP_KEY`; without them backups can't be restored).
- [ ] **Your Super Admin:** `php artisan super:create`, then sign in at the printed address and set up two-factor; keep the recovery codes.
- [ ] Never run the demo seeder on the live database (it adds demo restaurants and a demo admin).

## 3. E-mail (so mails don't land in spam)

- [ ] Choose a mail provider (e.g. Brevo, Mailgun, Zoho Mail, Amazon SES, or your host's SMTP).
- [ ] Add their **SPF** and **DKIM** records and a **DMARC** record at your DNS (section 7).
- [ ] Super Admin → Settings → Email: fill in SMTP → **Send test email** to yourself (also check the spam folder once).

## 4. Settings in Super Admin

- [ ] Settings → General: platform name, support e-mail, **WhatsApp number** for upgrades, currency.
- [ ] **Terms and Privacy:** read them and have a lawyer check them (they say "Draft").
- [ ] Plans: check the **prices and limits** (the sales sheet and landing page use them).
- [ ] Settings → Sign-up: public sign-up on/off, "Require my approval", **Welcome e-mails (day 1, 3, 7)** on if you want them, **"Add my menu for me"** (on by default).
- [ ] Backups: **Back up now**, then **Download** once. Optional: off-site copy (Backblaze B2 / Cloudflare R2 keys) and **Alerts → Telegram** (create a bot with @BotFather).
- [ ] Optional: Google sign-in keys (`docs/google-login.md`), then switch on "Continue with Google".

## 5. Check

- [ ] `php artisan menu:doctor --production` (VPS: `sudo bash deploy-vps.sh doctor`): no red lines left.
- [ ] Add `https://your-domain/health` to a free uptime monitor (UptimeRobot, Better Stack): it alerts you when the site is down.
- [ ] Smoke test on your phone (section 12, steps 15–20): sign up as a test restaurant, add 5 dishes, print a table tent, scan it.
- [ ] Open a demo menu on an **iPhone (Safari)** and a **Samsung phone (Samsung Internet)**: Claude could only test Chrome and Firefox.

## 6. Play Store (Menu Admin app)

- [ ] Google Play developer account (one-time fee) and the app's signing key (see the app repository's `docs/` and `store/listing.md`).
- [ ] Upload the screenshots in `store/screenshots/` (refreshed 2026-11-19) and the feature graphic.
- [ ] Firebase project for push notifications (section 10a) — optional; the app works without it.

## 7. Tell restaurants

- [ ] Super Admin → Landing page → **Sales sheet** → print or save as PDF.
- [ ] Use the texts in `docs/launch/README.md` (WhatsApp invite, Facebook, TikTok, 30-second video script). Replace [Name], [site] and [WhatsApp].
