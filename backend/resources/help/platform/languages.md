---
title: Languages
topic: content
summary: What language the platform and the menus use.
order: 20
pages: /platform/settings#general
---

The admin, the app and the guest menus are in **English** today. Restaurants type their dishes in their own words.

**Default language** and **Default currency** for new restaurants are in **Settings → General**.

Adding a language for the screens needs a developer: the texts are in `backend/lang/`, `frontend/src/locales/` and the app's `lib/l10n/`. Every screen uses these files, so a new language is a translation job, not a code change.
