<!-- Written by `php artisan docs:admin-guide` from backend/resources/help/platform. Edit those files, not this one. -->

# Sign-up settings

*Open or close sign-up, approval, the plan for new restaurants, anti-spam.*

Open **Settings → Sign-up**.

- **Public sign-up**: off = the sign-up page says "Registration is closed".
- **Plan for new sign-ups**: every new restaurant starts on it (now: **(the plan for new sign-ups)**).
- **Require my approval**: new restaurants wait until you approve them. Their menu is not public until then.
- **E-mail me about every new sign-up**.
- **Require two-factor sign-in for restaurant owners**.
- **Continue with Google**: restaurant people sign up and sign in with Google. It needs the Google keys in `.env` first (the page says when they are missing). Setup: `docs/google-login.md`. Super Admins always use password + two-factor.
- **Anti-spam**: Cloudflare Turnstile (optional) and **Blocked e-mail domains** (one per line).

Unconfirmed sign-ups are deleted after 7 days.
