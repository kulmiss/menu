---
title: Demo data
topic: data
summary: The sales demo for testing, never on the live server.
order: 10
---

The demo has a café with 67 dishes and real photos, three showcase restaurants (styles A, B, C), and about 30 small restaurants with a year of payments.

**On a test computer only:**

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
php artisan demo:photos
```

The password for all demo accounts is `password`. The demo Super Admin is `admin@example.com`.

**Never on the live server.** It refuses when `APP_ENV=production`. The setup wizard shows if demo restaurants exist and can delete them.
