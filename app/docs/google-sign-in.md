# Continue with Google (app)

What the app does (QUESTIONS M50) and what you set up once.

## How it works

1. The sign-in screen shows **Continue with Google** only when `GET /api/v1/mobile/config` says `"google": {"enabled": true, "client_id": "…"}`.
2. Tap → Google's account sheet (Android Credential Manager, via `google_sign_in`). The app asks for an **ID token for the server's web client** (`client_id` from the config, used as `serverClientId`).
3. The app sends it to `POST /api/v1/mobile/auth/google` (no token, no Idempotency-Key). The server answers like a password sign-in:
   - `token` → saved in the secure storage as always (`TokenKeys.token`), then `/me`.
   - `two_factor` → the usual code step.
   - `google_signup` → a sheet asks the restaurant name and the Terms → `POST mobile/auth/google/finish` → `token`.
   - an error `code` (`google.super_admin`, `google.unverified`, `google.signup_closed`…) → its message is shown.
4. **Sign out** also calls `GoogleSignIn.signOut()`, so the next time asks which account.

Code: `lib/core/auth/google_auth.dart` (the plugin behind `GoogleIdTokens`, faked in tests), `AuthRepository.google/googleFinish`, `SignInScreen` (`GoogleButton`, `GoogleFinishSheet`). Tests: `test/widgets/google_sign_in_test.dart`, golden `sign_in_google_*`.

## Set up (once)

Follow the backend's `docs/google-login.md`, step 6. In short, in the same Google Cloud project as the website's web client, make one **Android** OAuth client per signing key, each with package name `com.kulmis.menu_admin` and the key's SHA-1:

| Key | SHA-1 from |
|---|---|
| Debug | `keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android` |
| Upload | `keytool -list -v -keystore <your upload keystore> -alias <alias>` |
| Play App Signing | Play Console → App signing → *App signing key certificate* → SHA-1 |

Nothing goes into the app: no `google-services.json` change and no client ID in the code (it comes from `mobile/config`).

## Problems

- **"Google sign-in is not available on this phone"** — Google said `clientConfigurationError` or similar: usually the SHA-1 of the key that signed this build is missing (a Play build needs the **Play App Signing** SHA-1), or the package name differs.
- The button doesn't show — the Super Admin switch is off, the server has no keys, or the app still has the old config (it refreshes at start and when it comes back to the screen).
