<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# Backups

*Nightly backups, download, off-site copy, restore.*

Every night at 02:30 the database and every photo are saved in one zip, outside the public folder.

- **Back up now** makes one at once.
- **Download** needs your password or a two-factor code. The link works for 5 minutes.
- **Backup settings**: how many days, weeks and months to keep.
- **Off-site copy**: copy each backup to S3-compatible storage (Backblaze B2, Cloudflare R2, AWS). Needs the [backup key](backup-key.md).

If no backup works for 26 hours, the dashboard shows a warning and every Super Admin gets one e-mail a day.

**Restore** has no button on purpose: it replaces everything. Follow "Restoring a backup" in `docs/deploy.md`.

Test a restore once, on a copy of the site.
