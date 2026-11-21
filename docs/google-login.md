# Continue with Google — setup

“Continue with Google” lets restaurant owners and their team sign up and sign
in with their Google account, on the website and in the Menu Admin app.
Super Admins never use it: they always sign in with their password and
two-factor code.

You need about 20 minutes. You do this once.

---

## What you need

- A Google account (yours or the company's).
- Your website address, for example `https://menu.example.com`.
- For the app: its package name (`com.example.menuadmin` — see
  `android/app/build.gradle` → `applicationId` in the app repo) and its key
  fingerprints (step 6).

---

## 1. Make a Google Cloud project

1. Open <https://console.cloud.google.com/>.
2. At the top, click the project list → **New project**.
3. Name: `Menu` (any name). Click **Create**.
4. Make sure the new project is selected at the top.

## 2. Set up the consent screen

This is the window people see: “Sign in to Menu with Google”.

1. Menu ☰ → **APIs & Services** → **OAuth consent screen**
   (in the new console: **Google Auth Platform** → **Branding**).
2. **App name**: your platform name, e.g. `Menu`.
3. **User support email**: your support e-mail.
4. **App logo**: optional.
5. **App domain**: your website, your Privacy Policy page
   (`https://menu.example.com/privacy`) and Terms page
   (`https://menu.example.com/terms`).
6. **Authorized domains**: `example.com` (your domain without `menu.`).
7. **Developer contact**: your e-mail. Save.
8. **Audience** (or **User type**): choose **External**. Click **Publish app**
   (status **In production**). While it is “Testing”, only the test users you
   add can sign in.
9. **Data access** (or **Scopes**): add only `openid`, `.../auth/userinfo.email`
   and `.../auth/userinfo.profile`. These are basic scopes: Google does not
   need to review the app.

## 3. Make the web client (for the website)

1. **APIs & Services** → **Credentials** (or **Google Auth Platform** →
   **Clients**) → **Create credentials** → **OAuth client ID**.
2. **Application type**: **Web application**. Name: `Menu website`.
3. **Authorized JavaScript origins**: `https://menu.example.com`
4. **Authorized redirect URIs**: `https://menu.example.com/auth/google/callback`
   — exactly this, with `https` and no `/` at the end.
5. Click **Create**. Copy the **Client ID** and the **Client secret**.

Testing on your computer (Laragon)? Add a second redirect URI:
`http://localhost:8000/auth/google/callback` (and the origin
`http://localhost:8000`). Google allows `http` only for `localhost`.

## 4. Put the keys in `.env`

On the server, in the `backend/.env` file:

```
GOOGLE_CLIENT_ID=123456789-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
```

Then run:

```
php artisan config:clear
php artisan config:cache
```

Never put the secret in git, in a screenshot or in a chat.

## 5. Switch it on

1. Sign in as Super Admin → **Settings** → **Sign-up**.
2. Turn on **Continue with Google**. Save.
   - If you see “Google keys are missing”, step 4 is not done yet (or the
     config cache is old: run step 4's commands again).
3. Open your landing page, the sign-in page and the sign-up page in a private
   window: each shows **Continue with Google**.

Turn it off at any time: all Google buttons disappear and the Google
addresses stop working. People who signed up with Google can still sign in
with a password once they set one (Account → Security, or **Forgot password**).

## 6. The Android app

The app gets a Google token on the phone and sends it to the server, which
checks it. Google only gives tokens to apps whose **package name + SHA-1
fingerprint** it knows. Each key that signs the app needs its own Android
client:

| Key | When it is used | Where to find its SHA-1 |
|---|---|---|
| **Debug key** | `flutter run` on your computer | `keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android` (Windows: `%USERPROFILE%\.android\debug.keystore`) |
| **Upload key** | the `.aab` you build and upload | `keytool -list -v -keystore upload-keystore.jks -alias upload` (your keystore and alias) |
| **Play App Signing key** | the app people install from Google Play | Play Console → your app → **Test and release** → **Setup** → **App signing** → *App signing key certificate* → **SHA-1** |

For each key:

1. **Credentials** → **Create credentials** → **OAuth client ID**.
2. **Application type**: **Android**. Name: e.g. `Menu app (Play signing)`.
3. **Package name**: the app's `applicationId`, e.g. `com.example.menuadmin`.
4. **SHA-1 certificate fingerprint**: paste it (`AA:BB:...`).
5. **Create**. (Android clients have no secret.)

Then, in `backend/.env`, list the three Android client IDs (comma-separated).
This makes the server accept app tokens only from your app:

```
GOOGLE_ANDROID_CLIENT_IDS=111-debug.apps.googleusercontent.com,222-upload.apps.googleusercontent.com,333-play.apps.googleusercontent.com
```

Run `php artisan config:cache` again.

The app reads the **web** client ID from the server (`/api/v1/mobile/config`
→ `google_client_id`) and asks Google for a token *for that web client*.
Nothing to type in the app itself.

## 7. Check it

- Website, new e-mail: **Continue with Google** → choose an account → “Almost
  done” → restaurant name, tick the Terms → you land in your new admin.
- Website, existing account with the same Google e-mail: you are signed in and
  see “Google is now linked”.
- Two-factor account: after Google you still type your 6-digit code.
- Super Admin's Google account: refused with a message.
- App: sign-in screen → **Continue with Google**.

## Rules (what the code does)

- Only Google accounts whose e-mail Google has **verified**. Unverified: refused.
- Same verified e-mail as an existing account → linked automatically.
- New e-mail → new restaurant on the sign-up plan, after the restaurant name
  and the Terms. Respects **Sign-up off / approval / open**: off → refused;
  approval → the restaurant waits for you like any sign-up.
- **Account → Security**: link or unlink Google. Unlink needs a password
  (Google-only accounts get “E-mail me a link to set a password”).
- Security log (`storage/logs/security-*.log`): `login.google`,
  `google.linked`, `google.unlinked`, `google.signup`, `google.refused`
  (with the reason). Linked/unlinked also show in the Activity log. Codes and
  tokens from Google are never written anywhere.
- Limits: 20 Google redirects per minute per address, 10 sign-up finishes per
  minute, 10 app attempts per minute.

## Problems

| You see | Fix |
|---|---|
| Google says **redirect_uri_mismatch** | The redirect URI in step 3 must be exactly `https://<your domain>/auth/google/callback`. `APP_URL` in `.env` must be the same domain with `https`. |
| “Google sign-in did not work” right after Google | The session cookie was lost (e.g. `SESSION_DOMAIN` wrong, or http vs https). Check `APP_URL` and `SESSION_SECURE_COOKIE=true` on https. |
| **Access blocked: app not verified** / only some people can sign in | The consent screen is still **Testing**: publish it (step 2.8). |
| App: **DEVELOPER_ERROR** or code 10 | The SHA-1 of the key that signed this build is missing (step 6), or the package name differs. The Play Store build needs the **Play App Signing** SHA-1, not the upload one. |
| App: “Google sign-in is not set up” | `GOOGLE_CLIENT_ID` is empty or the switch is off. |
