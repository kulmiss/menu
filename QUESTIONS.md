# QUESTIONS

Format: question → **decision** (who decided). Owner = repository owner. Rec = Claude's recommendation, accepted by default.
Open items are at the bottom.

## Answered at Step 1 (2026-11-10)

| # | Question | Decision |
|---|---|---|
| 1 | Product name | **Platform setting; working name "Menu".** (Rec, accepted) |
| 2 | URL structure | **One domain: `/{slug}`, `/{slug}/{branch}`, `?table=`, `/q/{code}`, `/admin`, `/platform`, `/auth`, `/api`; reserved slug list.** (Rec) |
| 3 | Custom domains per restaurant | **No.** (Rec) |
| 4 | User in several restaurants | **Yes, with a switcher.** (Rec) |
| 5 | Sign-up | **Self-service + email verification + trial; Super Admin can also create.** (Rec) |
| 6 | Router | **TanStack Router.** (Rec) |
| 7 | Admin + Super Admin apps | **One SPA, two lazy route trees.** (Rec) |
| 8 | Sanctum mode | **Cookie SPA auth, same domain.** (Rec) |
| 9 | Public menu tech | **Blade + ~5 KB vanilla JS + inlined CSS, HTML cache.** (Rec) |
| 10 | Roles | **Custom: owner/manager/staff + `can_edit_prices`, policies.** (Rec) |
| 11 | Activity log | **Custom table.** (Rec) |
| 12 | Auth flows | **Laravel Fortify (headless).** (Rec) |
| 13 | Image storage | **Local `public/media` disk, swappable to S3; 320/640/1280 WebP + JPEG; 8 MB max.** (Rec) |
| 14 | Menu languages | **Any number (plan-limited), RTL, no machine translation.** (Rec) |
| 15 | Admin UI languages | **English, Somali, Amharic + Arabic (RTL proof). Non-English marked "machine draft" until reviewed.** (Owner) |
| 16 | Currency | **Platform default ETB; each restaurant picks its own; no conversion.** (Owner) |
| 17 | Allergens | **Fixed EU-14 + custom tags.** (Rec) |
| 18 | Map location | **Maps link or lat/lng; "Open in Maps" link; no embedded map.** (Rec) |
| 19 | Import format | **CSV + XLSX, preview before commit.** (Rec) |
| 20 | Default plans | **Trial 14 d (Pro limits); Starter 1 br/100 items/2 lang/2 members; Pro 3/500/5/5; Business unlimited.** (Rec) |
| 21 | Over limit after downgrade | **Keep data, block new.** (Rec) |
| 22 | Plan expired | **7 days grace (full admin + banner) → admin read-only, public menu still shown for 14 more days with an owner-only "subscription expired" note → then "Menu unavailable".** (Owner) |
| 23 | "Powered by" footer | **On Trial/Starter; removal is a plan feature.** (Rec) |
| 24 | Switchable features | **Multiple menus, branches, translations, promotions, insights, import/export, custom slug, team, custom fonts, remove branding.** (Rec) |
| 25 | Email | **SMTP via `.env`.** (Rec) |
| 26 | Delete restaurant | **Soft delete, purge after 30 days, double confirm.** (Rec) |
| 27 | Activity log retention | **12 months.** (Rec) |
| 28 | 2FA | **Required for Super Admin, optional for owners.** (Rec) |
| 29 | Minimum versions | **PHP 8.3+, MySQL 8.0+ / MariaDB 10.6+.** (Rec) |
| 30 | Repo bootstrap | **One first commit on `main`, then `develop`, protect `main`.** (Owner) |
| 31 | Package installs | **Owner enables them; Step 3 starts in a new session.** (Owner) |
| 32 | Legal pages | **Simple Terms and Privacy pages; owner supplies text; clear placeholders until then.** (Owner) |

New rules added by the owner (now in PLAN.md §2): Docker is for local development only; shared hosting is the main target (no Node.js, Redis, queue workers or Docker on the server).

## Smaller decisions taken at Step 2 (Claude, changeable)

| # | Question | Decision |
|---|---|---|
| 33 | How to store translations | **JSON columns per field, custom cast, no package.** Fast public render, simple import/export. |
| 34 | Cross-tenant references | **Composite FKs `(tenant_id, parent_id)`** so the DB itself blocks cross-tenant links. |
| 35 | Add-on groups | **Reusable across items** (pivot), since "Extra toppings" repeats. |
| 36 | Item text | **`summary` (short, list) + `description` (full, sheet).** |
| 37 | Price when variants exist | Item price empty; list shows **"from" lowest variant**. |
| 38 | Restaurant-level QR with several branches | Opens the **default branch** with a branch switch. |
| 39 | Fonts for Amharic / Arabic | **Noto Sans Ethiopic / Noto Sans Arabic via `unicode-range`**, downloaded only when needed. |
| 40 | Calendar & digits | **Gregorian calendar, Western digits** in all locales (Ethiopian calendar not in scope). |
| 41 | Default time zone | **Africa/Addis_Ababa**, per restaurant. |
| 42 | Who can invite | Owner: managers + staff; Manager: staff only. One owner per restaurant. |
| 43 | Impersonation limits | 60 min; no password/email/2FA, ownership, slug or delete actions; all logged. |
| 44 | Expired subscription write response | HTTP **402** with an i18n message; reads still work. |
| 45 | Image upload memory | Browser crops and downscales to ≤ 2048 px before upload; server limit 8 MB. |
| 46 | HEIC uploads | Rejected with a clear message (GD can't read HEIC); browser-side conversion where the browser supports it. |
| 47 | Admin font list for menus | Inter, Manrope, Lora, Nunito, System. |
| 48 | Repo layout | Monorepo: `backend/` (Laravel), `frontend/` (SPA), `public-menu/` assets built into `backend/public`. |
| 49 | Demo photos | Generated or royalty-free food images only, licences listed; no people. |

## Decisions taken at Step 3 · Partition 1 (Claude, changeable)

| # | Question | Decision |
|---|---|---|
| 50 | Package versions when Claude can't reach the registries | The setup script runs `composer require` / `pnpm add` without guessed versions; the **lockfiles pin the exact versions** and are committed. |
| 51 | Test database | **MySQL `menu_test`** locally and in CI (composite FKs and JSON need MySQL, not SQLite). |
| 52 | Laravel skeleton extras | Removed the skeleton's own Vite/npm setup, CLAUDE.md/AGENTS.md, StyleCI and GitHub files; public-menu CSS/JS tooling arrives in Partition 4. |
| 53 | Social login buttons in template auth forms | Not in scope; removed in Partition 2. |
| 54 | Local PHP version | Owner's Laragon has **PHP 8.5**; CI runs **8.3** (the minimum we support) so we never depend on newer features. |

## Open

- Owner action: protect `main` in GitHub settings (require PR, no force push, no deletion). Claude's access can't change branch settings.
- PR #1 is still open: merge it into `develop`.
- MySQL must be running in Laragon for `setup-local.bat`.
