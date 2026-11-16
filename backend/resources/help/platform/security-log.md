---
title: Security log and activity log
topic: security
summary: Failed sign-ins, password resets and who changed what.
order: 10
pages: /platform/activity
---

**Activity log** (in the panel) shows changes on the platform and in restaurants: who, what and when. Filter by area, action or person.

**Security log** (on the server) has one line for each security event: failed sign-ins, locked accounts, wrong two-factor codes, password resets, blocked sign-ups, refused uploads. It is in `storage/logs/security-YYYY-MM-DD.log`, kept 30 days.

Each line holds only short codes for the e-mail and the address, never passwords or codes.

Quick check: `grep login.locked storage/logs/security-*.log`.

**Good habits:** two-factor for every Super Admin, a password manager, and fewer Super Admins.
