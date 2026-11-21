# Help articles

The defaults for the Help centre (backend QUESTIONS #194). `restaurant/` is the Help page in the web admin and the app. `platform/` is the Super Admin guide (it is also written to `docs/admin-guide/` and `docs/admin-guide.pdf`). The Super Admin can change any article in **Super Admin → Help articles**. A changed article wins over the file with the same name.

## Writing rules

- Very easy English. Short sentences. One idea per sentence.
- Steps are a numbered list. Start each step with a verb: "Tap", "Open", "Choose".
- Use the exact button and menu names from the screens, in **bold**.
- Never type a price, a limit or a number of days. Use a placeholder (below), so the text is always right.
- When you change a feature, change its article in the same pull request (see CONTRIBUTING.md).

## Header

```
---
title: Add a dish
topic: menu                      # a key from lang/en/help.php "topics"
summary: One line for the list and the search.
order: 20                        # place in the topic (small first)
features: sold_out               # hide the article when one of these is off (`!key` = only when off)
roles: owner, manager            # who sees it; empty = everyone
pages: /admin/menu, /admin/dishes   # web pages whose "?" button opens it (first match wins)
app: /r/:tid/menu                # app screens whose "?" button opens it
keywords: price, photo           # extra words for the search
public: true                     # also shown signed out (sign-in problems) and in the Help centre at /support
---
```

## In the text

| Write | You get |
|---|---|
| `{{plans}}` | the list of plans with prices, limits and features, live |
| `{{plan.pro.price}}`, `{{plan.free.photos}}` | one plan's price or limit (`items`, `photos`, `branches`, `members`, `menus`, `tables`, `languages`) |
| `{{signup_plan}}`, `{{signup_plan.photos}}` | the plan new restaurants get |
| `{{trial_days}}`, `{{grace_days}}`, `{{read_only_days}}`, `{{invitation_days}}` | days, from the settings |
| `{{photos_per_dish}}`, `{{photo_max_mb}}`, `{{import_max_mb}}` | upload limits |
| `{{site_name}}`, `{{site_url}}`, `{{admin_url}}`, `{{menu_url}}`, `{{restaurant_name}}` | names and addresses |
| `{{support}}`, `{{support_whatsapp}}`, `{{support_email}}` | how to contact support |
| `<!-- if sold_out -->…<!-- endif -->` | only when the feature is on (`!sold_out` = only when off; `role:owner,manager`) |
| `![Alt](shot:dashboard)` | the phone and desktop screenshot `public/help/shots/dashboard-{phone,desktop}.webp` |
| `![Alt](appshot:app-home)` | an app screenshot `public/help/shots/app-home.webp` |
| `[text](help:add-a-dish)` | a link to another article |

Screenshots are made from the demo data by `scripts/help-screenshots.mjs` (see docs/help-screenshots.md). A test fails when an article uses a screenshot, a link or a placeholder that doesn't exist.
