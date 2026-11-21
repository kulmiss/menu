<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# Super Admin guide

How to set up and run the platform, in easy steps. The same pages are inside the Super Admin panel (**Guide**, and the **?** button on every page). One PDF: [admin-guide.pdf](../admin-guide.pdf).

## 1. Setup: from zero to live

- [How the guide works](overview.md) — What is in this guide, and the order to read it.
- [1. Choose hosting](hosting.md) — Shared hosting (cPanel) or a VPS. What the server needs.
- [2. Domain and HTTPS](domain-https.md) — Point the domain at the public folder and turn on HTTPS.
- [3. The .env file](env-file.md) — The settings file of the server. The important lines.
- [4. Database, migrate and cache](migrate-and-cache.md) — Create the tables and make the site fast.
- [5. Backup key](backup-key.md) — Encrypt the backups. Save the key in a password manager.
- [6. Cron: the one scheduled line](cron.md) — One line every minute runs e-mails, backups and menu updates.
- [7. E-mail (SMTP)](smtp.md) — So invitations, invoices and password e-mails arrive.
- [8. The first Super Admin and two-factor](first-super-admin.md) — Make your own account on the server, then turn on 2FA.
- [9. Firebase for the mobile app](firebase.md) — Push notifications for the Menu Admin app (optional).
- [10. Go-live checklist](go-live-checklist.md) — Tick these on the live server, in order.

## 2. Restaurants

- [Restaurants](restaurants.md) — Find, create, change, suspend and delete restaurants.
- [Payments and invoices](payments.md) — Record a payment by hand. The invoice is made by itself.
- [Messages and announcements](messages-and-announcements.md) — Tell restaurants about news: on their dashboard, or by e-mail.
- [Menu requests ("Add my menu for me")](menu-requests.md) — Restaurants send photos of their paper menu; you type it in.

## 3. Plans and limits

- [Plans and limits](plans-and-limits.md) — Prices, limits and features of each plan.
- [Themes](themes.md) — Ready-made looks restaurants can start from.

## 4. Feature switches

- [Feature switches: global, plan and restaurant](feature-switches.md) — Turn features on or off for everyone, a plan or one restaurant.

## 5. Mobile app control

- [Mobile app: access and maintenance](mobile-access.md) — Who may use the app, and maintenance mode.
- [Mobile app: versions and forced updates](mobile-versions.md) — Latest version, minimum version and update prompts.
- [Mobile app: the announcement banner](mobile-banner.md) — A short line at the top of the app, between two dates.
- [Mobile app: app features and links](mobile-features.md) — Biometric lock, push, analytics, crash reports, and store links.
- [Mobile app: send a push notification](mobile-push.md) — Write a message to all app users, or some of them.
- [Mobile app: devices and stats](mobile-devices.md) — Who is signed in on which phone. Sign out one or all.

## 6. Sign-up

- [Sign-up settings](signup-settings.md) — Open or close sign-up, approval, the plan for new restaurants, anti-spam.

## 7. Legal texts, languages and help

- [Legal texts and site pages](legal-texts.md) — Change the Terms of use, the Privacy policy and the About us, Contact us and Cookie info pages.
- [Languages](languages.md) — What language the platform and the menus use.
- [Landing page, logo and link preview](landing-and-branding.md) — The public home page, the e-mail logo and the picture for shared links.
- [Website footer](footer.md) — The footer of the website: columns of links, contact, social links, the app badge, history.
- [Help articles](help-articles.md) — Change the Help centre, add articles, see what helps.

## 8. "Log in as" and support

- ["Log in as" a restaurant owner](log-in-as.md) — See and fix a restaurant exactly as the owner sees it.
- [Helping a restaurant](support-tips.md) — The usual questions and where to look.

## 9. Demo data and backups

- [Demo data](demo-data.md) — The sales demo for testing, never on the live server.
- [Backups](backups.md) — Nightly backups, download, off-site copy, restore.

## 10. Security

- [Security log and activity log](security-log.md) — Failed sign-ins, password resets and who changed what.

## 11. Problems and answers

- [Problems and answers](problems.md) — The most common server problems and what to check.

## 12. Cheat sheet

- [Cheat sheet: daily, weekly, monthly](cheat-sheet.md) — One page of routine jobs.
