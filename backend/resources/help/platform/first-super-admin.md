---
title: 8. The first Super Admin and two-factor
topic: setup
summary: Make your own account on the server, then turn on 2FA.
order: 80
---

Super Admins are made on the server, not in the app. In `backend/`:

```bash
php artisan tinker --execute="\$u = App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => 'a-long-password']); \$u->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();"
```

1. Sign in at `{{site_url}}/auth/sign-in`.
2. Open `{{site_url}}/platform`. You see only **Set up two-factor**.
3. Scan the QR code with an authenticator app. Type the code.
4. **Save the recovery codes** in your password manager.

Now the whole panel opens, and the **setup wizard** starts. It walks you through the platform name, plans, e-mail and switches. You can open it again from **Settings → Setup wizard**.

Next: [Firebase for the app](help:firebase).
