---
title: Backups
topic: data
summary: Nightly backups, download, off-site copy, restore.
order: 20
pages: /platform/backups
---

Every night at 02:30 the database and every photo are saved in one zip, outside the public folder.

- **Back up now** makes one at once.
- **Download** needs your password or a two-factor code. The link works for 5 minutes.
- **Backup settings**: how many days, weeks and months to keep.
- **Off-site copy**: copy each backup to S3-compatible storage (Backblaze B2, Cloudflare R2, AWS). Needs the [backup key](help:backup-key).

If no backup works for 26 hours, the dashboard shows a warning and every Super Admin gets one e-mail a day.

**Restore** has no button on purpose: it replaces everything. Follow "Restoring a backup" in `docs/deploy.md`.

Test a restore once, on a copy of the site.

## Alerts

Under the backups, **Alerts** tells you when something goes wrong on the server: errors (grouped, at most one alert every 30 minutes), a red go-live check (once a day) and backup problems.

- **E-mail every Super Admin** is on from the start.
- **Telegram** (optional): in Telegram, ask **@BotFather** for a new bot and copy its token. Add the bot to your group (or start a chat with it) and put the chat ID here. **Send a test alert** shows where it arrived.

On the server, `php artisan menu:doctor` lists everything that needs fixing, with how. For an uptime monitor use **{{site_url}}/health**.
