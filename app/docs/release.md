# Release: keystore, signed AAB, Google Play

## 1. Upload keystore (once; keep it forever)
On your laptop (PowerShell, JDK 21):
```powershell
cd $HOME
keytool -genkeypair -v -keystore menu-admin-upload.jks -keyalg RSA -keysize 4096 -validity 10000 -alias upload
```
- Choose a strong password. Store the `.jks` file **and** the password in your password manager and in a second safe place. Without them you can't publish updates. (Play App Signing can reset a lost upload key, but that takes days.)
- Never put the `.jks` file in the repo.

Create `android\key.properties` in the repo (git-ignored):
```
storePassword=<the password>
keyPassword=<the password>
keyAlias=upload
storeFile=C:\\Users\\<you>\\menu-admin-upload.jks
```

## 2. Version
`pubspec.yaml` → `version: 1.0.0+1`. Raise the part after `+` (versionCode) for every upload, e.g. `1.0.1+2`.

## 3. Build the release AAB
```powershell
flutter build appbundle --release --obfuscate --split-debug-info=build\symbols --dart-define=API_BASE_URL=https://YOUR-DOMAIN
```
Output: `build\app\outputs\bundle\release\app-release.aab`. Then upload the Crashlytics symbols (docs/firebase.md §5).
Size check (optional): `flutter build apk --release --split-per-abi --dart-define=API_BASE_URL=https://YOUR-DOMAIN` → each `app-*-release.apk` should be under 20 MB.

## 4. Google Play Console
1. https://play.google.com/console → **Create app**: name "Menu Admin", default language English (US), App, Free. Accept the declarations.
2. **App access**: "All or some functionality is restricted" → add a **reviewer login**: a restaurant Owner account on your live server with two-step sign-in **off**. Use demo data, never a real customer.
3. **Ads**: No ads.
4. **Content rating**: questionnaire → category "Utility, Productivity, Communication or Other" → answer No to all → rating **Everyone**.
5. **Target audience**: 18+ (business tool); not designed for children.
6. **News app**: No. **COVID-19 tracing**: No. **Government app**: No. **Financial features**: none (payments are only recorded, never processed).
7. **Data safety**: see the table below.
8. **Privacy policy URL**: `https://YOUR-DOMAIN/privacy`.
9. **Store listing**: texts in `store/listing.md`, icon `assets/icon/play_store_512.png`, feature graphic `store/feature_graphic.png`, screenshots in `store/screenshots/` (phone, 7" and 10" tablet).
10. **App category**: Business. Contact e-mail: your support address.

### Data safety answers
| Data type | Collected | Shared | Purpose | Optional? |
|---|---|---|---|---|
| Name | Yes | No | Account management | Required |
| Email address | Yes | No | Account management | Required |
| User IDs | Yes (account ID) | No | Account management | Required |
| Photos | Yes (dish photos the user uploads) | No | App functionality | Optional |
| Crash logs | Yes | No | Analytics (app stability) | Required |
| Diagnostics | Yes | No | Analytics | Required |
| App interactions | Yes (screens viewed, anonymous) | No | Analytics | **Optional** (switch in Settings) |
| Device or other IDs | Yes (push notification token) | No | App functionality (notifications) | Required for push |
- Data is encrypted in transit: **Yes** (HTTPS only in release builds).
- Users can request deletion: **Yes**, by the web link in Settings → Privacy → "Delete my account" (or support e-mail).
- Firebase (Google) processes crash logs, analytics and push tokens on your behalf: this counts as a service provider, not "sharing".

## 5. Testing tracks (personal developer accounts)
Google requires **closed testing with at least 12 testers for 14 continuous days** before production access for personal accounts created after November 2023.
1. Testing → **Closed testing** → create track "Beta" → add an e-mail list of 12+ testers (staff, friendly restaurant owners).
2. Upload the AAB → roll out to Closed testing → share the opt-in link with testers. They must stay opted in for 14 days.
3. After 14 days: Dashboard → **Apply for production** → answer the questions about your test → when approved, promote the release to Production.
(Internal testing, up to 100 testers and live within minutes, is useful for your own checks but doesn't count toward the 14 days.)

## 6. Forced updates
When a release must replace older ones (API change), set **Super Admin → Settings → Mobile app → Minimum app version** to the new version after it is live on Play. Older apps then show "Update required" and open the Play update screen.

## 7. iOS (later)
Same code. On a Mac with Xcode: `flutter build ipa --dart-define=API_BASE_URL=https://YOUR-DOMAIN`. The bundle ID is `com.kulmis.menuadmin`. You need an Apple Developer account (paid) and an APNs key for pushes (docs/firebase.md §3).

## Signing check (SECURITY_REPORT.md L10)
A release build (`flutter build appbundle` / `apk --release`) now **stops** when `android/key.properties`
is missing, instead of silently signing with the debug key (which Play would reject, or worse, which
someone else could reproduce). Only CI's size check sets `ALLOW_DEBUG_SIGNING=true`. For a local test
build without the key: `ALLOW_DEBUG_SIGNING=true flutter build apk --release`.
