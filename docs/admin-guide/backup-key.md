<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# 5. Backup key

*Encrypt the backups. Save the key in a password manager.*

Backups hold every restaurant's data. Encrypt them:

```bash
php artisan backup:key --write
php artisan config:cache
```

This adds `BACKUP_KEY` to `.env`.

**Save the key in your password manager now.** Without it, no backup can be restored. If you make a new key later, old backups still need the old one.

Without a key, backups are plain zips, the Backups page shows a warning, and no off-site copy is made.

Next: [Cron](cron.md).
