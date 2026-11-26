# Firebase setup (your job — about 15 minutes)

The app works without Firebase. Push notifications, Crashlytics and Analytics switch on as soon as these steps are done. Firebase's free Spark plan covers all three, so there is no cost.

## 1. Create the project
1. Go to https://console.firebase.google.com → **Add project** → name it e.g. `menu-admin` → Google Analytics **on** (the default account is fine) → Create.

## 2. Add the Android app
1. Project overview → **Add app** → Android.
2. Android package name: `com.kulmis.menu_admin` (exactly). Nickname: `Menu Admin Android`.
3. **Debug signing certificate SHA-1** (optional, only needed for some sign-in features): you can skip it.
4. **Register app** → **Download google-services.json**.
5. Put the file at `android/app/google-services.json` in your laptop's copy of the repo. **Never commit it** (it is already in `.gitignore`, and CI fails if it is committed).
6. Skip the "Add Firebase SDK" steps: the project already has them. Gradle applies the Firebase plugins only when that file exists.

## 3. Add the iOS app (later, when you build for iPhone)
1. Add app → iOS → Bundle ID `com.kulmis.menuadmin` → download `GoogleService-Info.plist` → put it in `ios/Runner/` with Xcode (also git-ignored).
2. For pushes on iPhone: Project settings → Cloud Messaging → upload an **APNs key** (.p8) from your Apple Developer account.

## 4. Push notifications from the server
1. Project settings → **Service accounts** → **Generate new private key** → a JSON file downloads. It is a secret.
2. Upload it to the server outside `public_html`, e.g. `storage/app/private/firebase.json` (permissions 600).
3. In the server's `.env`: `FCM_CREDENTIALS=storage/app/private/firebase.json`, then run `php artisan config:cache`.
4. Details: `kulmis/menu` → `docs/deploy.md` §10a.

## 5. Crashlytics
1. Firebase console → Crashlytics → **Enable**.
2. Release builds are obfuscated. Upload the symbols after each release build so crash reports are readable:
   ```powershell
   npm install -g firebase-tools   # once
   firebase login                  # once
   firebase crashlytics:symbols:upload --app=<your Android app ID, 1:…:android:…> build\symbols
   ```
   The Android app ID is under Project settings → Your apps.

## 6. Analytics
Nothing to do. The app sends only anonymous screen names (`/r/:id/menu`) and a few feature events. It sets no user ID and never sends restaurant names or menu content. Users can switch it off under Settings → Privacy. Ad ID collection is disabled in the manifest.

## Check it works
- Install a build with `google-services.json` and sign in. Notifications should show as **on** (no "not set up" note) under Profile › Notifications.
- On the server, record a payment as another Super Admin. Your phone should get "Payment: …".
- Crashlytics shows the app after its first launch from a release build.
