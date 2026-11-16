---
title: Import and export with a spreadsheet
topic: menu
summary: Add or change many dishes with Excel or CSV.
order: 90
features: import_export
pages: /admin/import
roles: owner, manager
keywords: excel, csv, spreadsheet, upload, download, backup, bulk
---

**Export** downloads every menu, category and dish in one file.

1. Open **Import & export**.
2. Under **Export**, tap **Excel** or **CSV**.

**Import** adds or changes many dishes at once. You see a preview before anything is saved.

1. Download the **Empty template** or an export.
2. Fill it in. One row is one dish.
3. Tap **Choose file**. The file can be up to {{import_max_mb}} MB.
4. Check the **Preview**: New, Changed, Unchanged and Errors.
5. Fix rows with errors in the file and upload it again.
6. Tap **Import**.

How the file works:

- Dishes are matched by menu, category and dish name.
- An empty cell keeps the current value.
- Sizes: `Small=60; Large=80`. Prices: `120` or `120.50`.
- Tags and allergens: separated by commas.

![The import preview](shot:import)
