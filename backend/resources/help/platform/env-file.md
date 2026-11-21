---
title: 3. The .env file
topic: setup
summary: The settings file of the server. The important lines.
order: 30
---

Copy `.env.example` to `.env` in `backend/`. Set at least:

```dotenv
APP_NAME="Menu"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://menu.example.com
TRUSTED_PROXIES=          # * behind Cloudflare or a host proxy; empty otherwise

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
LOG_STACK=daily
```

- **APP_ENV=production** and **APP_DEBUG=false** on a live site. Always.
- **APP_URL** exactly as people type it: `https://`, with or without `www`.
- **TRUSTED_PROXIES**: only when Cloudflare or a host proxy is in front. Then rate limits see the real visitor.

Set the file to `600` permissions. **Save a copy of `.env` in your password manager.** The `APP_KEY` in it is needed to read saved passwords and two-factor secrets.

Next: [Database, migrate and cache](help:migrate-and-cache).
