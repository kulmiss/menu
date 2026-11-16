---
title: 6. Cron: the one scheduled line
topic: setup
summary: One line every minute runs e-mails, backups and menu updates.
order: 60
---

In cPanel → **Cron Jobs**, add this line, **every minute**:

```cron
* * * * * cd /home/USER/menu/backend && php artisan schedule:run >> /dev/null 2>&1
```

It runs everything: e-mails, happy-hour menu updates, the nightly backup at 02:30, monthly reports, push notifications and clean-up.

Some hosts need the full PHP path, for example `/opt/cpanel/ea-php83/root/usr/bin/php`.

**Check it:** after two minutes, run `php artisan schedule:list`. The Super Admin dashboard → **System health** shows "cron last run".

Next: [E-mail (SMTP)](help:smtp).
