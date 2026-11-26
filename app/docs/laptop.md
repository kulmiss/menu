# Laptop steps (Windows, PowerShell)

You have Flutter 3.47, the Android SDK and JDK 21. The cloud work used Flutter **3.47.9**; if `flutter --version` shows an older 3.47.x, run `flutter upgrade` first (3.47 → 3.47.9 is a patch update).

## 1. Get the code
```powershell
cd C:\dev                      # any folder
git clone https://github.com/kulmiss/menu-admin-app.git
cd menu-admin-app
git checkout develop           # or the PR branch you want to try, e.g. feat/app
flutter pub get
flutter doctor                 # Android toolchain must be ✓
```

## 2. Backend with the mobile endpoints (your Laragon copy)
The app needs backend PRs #92–#95 (`kulmiss/menu`). Until they are merged, use the last branch, which contains all four:
```powershell
cd C:\laragon\www\menu
git fetch origin
git checkout feat/mobile-config      # after merging: git checkout develop; git pull
cd backend
composer install
php artisan migrate
php artisan db:seed --class=DemoSeeder   # demo restaurant: demo@example.com / password; Super Admin admin@example.com / password (2FA secret JBSWY3DPEHPK3PXP)
```
Find your PC's Wi-Fi address: `ipconfig` → "IPv4 Address", e.g. `192.168.1.20`.
In `backend\.env` set `APP_URL=http://192.168.1.20:8000` so photo links work on the phone, then:
```powershell
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```
When Windows asks, allow PHP through the firewall for **Private networks**. Check from the phone's browser: `http://192.168.1.20:8000/up` should answer.

## 3. Connect your phone
1. Phone: Settings → About phone → tap **Build number** 7 times → Developer options → **USB debugging** on.
2. Plug in the USB cable → accept "Allow USB debugging" on the phone.
3. `flutter devices` should list the phone.

## 4. Run the app against your laptop
```powershell
cd C:\dev\menu-admin-app
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```
Debug builds allow plain `http` (only debug). You can also change the server in a debug build: tap the logo 5 times on the sign-in screen.
Sign in with `demo@example.com` / `password`, or the Super Admin. For the Super Admin's 2FA code, add the secret `JBSWY3DPEHPK3PXP` to an authenticator app.

## 5. Run all tests
```powershell
flutter analyze
flutter test --exclude-tags golden          # unit + widget tests (goldens are Linux-only: font rendering differs on Windows)
flutter test integration_test -d <device-id> # the 4 main flows on your phone (fake server, nothing touches your data)
```
Backend:
```powershell
cd C:\laragon\www\menu\backend
php vendor\bin\pest
```

## 6. Release build
Follow `docs/release.md` (keystore once, then):
```powershell
flutter build appbundle --release --obfuscate --split-debug-info=build\symbols --dart-define=API_BASE_URL=https://YOUR-DOMAIN
```
The AAB is in `build\app\outputs\bundle\release\app-release.aab`.

To try the release on your phone first:
```powershell
flutter build apk --release --split-per-abi --dart-define=API_BASE_URL=https://YOUR-DOMAIN
flutter install --use-application-binary build\app\outputs\flutter-apk\app-arm64-v8a-release.apk
```

## 7. Firebase (optional, any time)
Put `google-services.json` in `android\app\` (docs/firebase.md), then build again. Without it, everything works except push, Crashlytics and Analytics.
