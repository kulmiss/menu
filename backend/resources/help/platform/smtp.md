---
title: 7. E-mail (SMTP)
topic: setup
summary: So invitations, invoices and password e-mails arrive.
order: 70
pages: /platform/settings#email
---

1. Open **Settings → Email (SMTP)**.
2. Fill in the server, port (587), encryption, username, password, from address and name.
3. Tap **Save**, then **Send test email** to your own address. You see the result, or the server's error, at once.

The password is stored encrypted and never shown again.

**So e-mails don't land in spam**, add three DNS records for the domain of the from address: **SPF**, **DKIM** and **DMARC**. cPanel → **Email Deliverability** can add SPF and DKIM for you. Details: `docs/deploy.md` section 7.

Next: [The first Super Admin](help:first-super-admin).
