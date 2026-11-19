---
title: Sign-up settings
topic: signup
summary: Open or close sign-up, approval, the plan for new restaurants, anti-spam.
order: 10
pages: /platform/settings#signup
---

Open **Settings → Sign-up**.

- **Public sign-up**: off = the sign-up page says "Registration is closed".
- **Plan for new sign-ups**: every new restaurant starts on it (now: **{{signup_plan}}**).
- **Require my approval**: new restaurants wait until you approve them. Their menu is not public until then.
- **E-mail me about every new sign-up**.
- **Require two-factor sign-in for restaurant owners**.
- **Continue with Google**: restaurant people sign up and sign in with Google. It needs the Google keys in `.env` first (the page says when they are missing). Setup: `docs/google-login.md`. Super Admins always use password + two-factor.
- **Anti-spam**: Cloudflare Turnstile (optional) and **Blocked e-mail domains** (one per line).

Unconfirmed sign-ups are deleted after 7 days.

- **Welcome e-mails (day 1, 3 and 7)**: short tips after sign-up — add dishes, print the QR codes, choose a style. A tip that is already done is skipped, and owners can stop them with one click. Off until you switch it on.
- **"Add my menu for me"**: restaurants can send photos of their paper menu (see [Menu requests](help:menu-requests)). On from the start.
