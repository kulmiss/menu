# Contributing

## How we work

- Small pull requests into `develop`. CI green. Tests for every change. The owner merges.
- Decisions go into [QUESTIONS.md](QUESTIONS.md) with a number.
- English texts through `t()` / `__()`. Tap targets at least 44 px. Light and dark.
- Speed budgets: admin first load ≤ 240 KB gzip (`node frontend/scripts/bundle-size.mjs --budget-kb=240`), the guest menu checks in CI, app start-up unchanged.

## Docs rule: change the feature, change its docs in the same PR

Every pull request that adds or changes a feature updates the matching docs **in the same pull request**:

| You changed… | Update |
|---|---|
| A restaurant screen, button name or rule | its article in `backend/resources/help/restaurant/` (format: `backend/resources/help/README.md`), and the screenshot if the screen looks different (`docs/help-screenshots.md`) |
| A Super Admin screen or a server step | its page in `backend/resources/help/platform/`, then `php artisan docs:admin-guide` and `node frontend/scripts/admin-guide-pdf.mjs` |
| An API route | `php artisan docs:api-reference`; an app endpoint also gets a section with examples in `docs/mobile-api.md` |
| A new web admin page | a help article with `pages:` for its **?** button |
| A new app screen | a help article with `app:` for its **?** button |

Never type a price, a plan limit or a number of days in an article: use a placeholder (`{{plans}}`, `{{signup_plan.photos}}`, `{{trial_days}}` …).

The tests hold you to it: they fail when a route has no API doc, when `api-reference.md` or `docs/admin-guide/` is out of date, when an article links to a missing article or picture, uses an unknown placeholder or types a limit, and when a sidebar or Super Admin page has no help article.

## Checks (same as CI)

- Backend (`backend/`): `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test`
- Frontend (`frontend/`): `pnpm lint`, `pnpm format:check`, `pnpm build`, `pnpm test`
