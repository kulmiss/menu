<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# 9. Firebase for the mobile app

*Push notifications for the Menu Admin app (optional).*

The app works without Firebase. Then notifications only show in the app's inbox.

**For push notifications on phones:**

1. In the Firebase console, open your project → **Project settings → Service accounts**.
2. Tap **Generate new private key**. A JSON file downloads. Keep it secret.
3. Upload it **outside** `public_html`, for example `storage/app/private/firebase.json`, permissions `600`.
4. In `.env`: `FCM_CREDENTIALS=storage/app/private/firebase.json`, then `php artisan config:cache`.

The cron line sends the pushes. Keep a copy of the file in your password manager: it is not in the backups.

The app itself needs `google-services.json` when it is built: see `docs/firebase.md` in the app repository.

Next: [Go-live checklist](go-live-checklist.md).
