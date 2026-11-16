---
title: Problems and answers
topic: problems
summary: The most common server problems and what to check.
order: 10
---

- **Menu pages are slow.** The document root must be `backend/public`, and `.htaccess` must be uploaded.
- **"Page expired" (419) in the admin.** `APP_URL` must match the address in the browser (https, www).
- **E-mails don't arrive.** **Settings → Email → Send test email** shows the error. The cron line must run. Check SPF and DKIM.
- **Photos don't upload.** `upload_max_filesize`, `post_max_size`, `memory_limit`, GD with WebP, `public/media/` writable.
- **The menu shows old content.** Run `php artisan menu:refresh-pages --all`. No "Cache Everything" rule for HTML.
- **QR links don't open.** `/q/CODE` needs the `.htaccess` rule and `public/qr.php`.
- **Backups fail.** Free disk space; a valid `BACKUP_KEY`. The Backups page shows the error.
- **The app says "Update required" for everyone.** The minimum version is higher than the store version. Lower it in **Mobile app → Versions**.
- **No push notifications.** `FCM_CREDENTIALS` in `.env`, cron running, **Push notifications** on in Mobile app → App features.
- **A restaurant can't see a feature.** Check Settings → Features (global), the restaurant's Features, then the plan.
