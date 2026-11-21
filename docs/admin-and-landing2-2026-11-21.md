# Admin improvements + Landing 2 (21–22 Nov 2026)

One PR into `develop`: **feat/admin-landing2** (5 items, website only, no Flutter changes).
Decisions are in QUESTIONS #247–#251.

## Summary

| # | Item | Done | Key point |
|---|---|---|---|
| 1 | Phone preview for themes | ✅ | On **Super Admin → Themes** (there is no "default theme for new restaurants" setting, see below) |
| 2 | Collapsible Appearance sections | ✅ | One shared card; summary when closed; remembered per user |
| 3 | Terms closed in Settings | ✅ | All 5 legal texts closed; **bug fixed**: saving General no longer re-saves the texts |
| 4 | Dashboard: more charts and data | ✅ | Demo on/off, compare, 5 groups, 3 new cards, info + CSV on every card |
| 5 | Landing 2 | ✅ | `/landing2` (noindex), switch in Settings, Landing 1 unchanged (golden test) |

## What existed, and what changed

### 1. Phone preview for the default theme
- **Existed:** the owner's phone preview in Appearance (an iframe with the restaurant's preview address); **Super Admin → Themes** with a "New theme" form (name, colour, font, style). **No "default theme for new restaurants" setting exists**, so the preview went on Themes. I did not add a new default setting: it would change what every new restaurant gets, so that's your call.
- **Changed:** the preview parts moved out of Appearance and are now **shared** (`PhonePreview`, `PreviewSwitch`, `usePreviewSrc`). Appearance uses them unchanged, and Themes uses the same parts.
- On a computer the phone sits **beside the form and stays in place while you scroll**. On a phone or tablet, a **Preview** button opens it full screen with Close, and nothing loads until it's opened.
- Buttons: **Light/Dark**, **Left to right / Right to left (Arabic)**, **Photos / No photos**. Right to left works only for the styles that have a menu direction (H and M–T); for the others the button is greyed out with a note. There is no "My order" button, because it isn't part of a theme.
- New `GET /super/themes/preview`: it builds the demo café (or `demo-mandi` for right to left) in memory with the theme. Any style works, whatever the demo's plan. **Nothing is saved**, and the response is `no-store`, `SAMEORIGIN`, noindex. If the demos don't exist, it shows a short help page instead.
- Bugs found and fixed:
  - Styles M–T had no names in the Themes style list; raw keys showed.
  - The unpressed switch text had too little contrast (axe found it).
  - In the real browser, Light/Dark showed as raw keys on the Super Admin side, because the texts weren't loaded there. They are now in the common texts.

### 2. Appearance: collapsible sections
- **Existed:** 5 step cards and step links (from #175), and an unused shadcn `Collapsible`.
- **Changed:** a shared `CollapsibleCard` plus a `useOpenSections` hook.
  - The header is a real button with `aria-expanded` and `aria-controls`. It has the number, title, a hint when open or a summary when closed, a **"Changed"** mark, and a chevron.
  - The animation is short and turns off with reduced motion.
  - Default: section 1 open, the others closed. **Expand all / Collapse all** is at the top.
  - Open/closed is remembered **per user on this device** (localStorage in try/catch; blocked storage works too).
  - `#ap-look` … `#ap-guests` open and scroll to their section, including on page load and when the address changes.
  - **An error opens its section and focuses the field.** Errors without their own field are listed at the top of their section.
  - The live preview keeps working with any sections closed.

### 3. Settings: Terms closed
- **Existed:** 5 large text boxes (Terms, Privacy, About, Contact, Cookies) in the General card.
- **Changed:**
  - All five are closed cards using the same component (my choice; they are the same kind of long box). The header reads "Last updated 12 Nov 2026 · 2,340 words", or "Default text".
  - `#legal-terms` opens Terms, and Expand all is available.
  - Unsaved changes show "Changed". An error opens the card and focuses the text box.
  - The API now sends `legal_updated_at`.
- **Bug fixed:** saving General used to send all five texts every time, so pages you had never edited got the day's default saved as their own text. Now only changed texts are sent, and a backend test proves the Terms row stays exactly the same.

### 4. Dashboard
- **Existed:** a 7/30/90/365/custom range, a plan filter, KPIs with the previous period, growth, funnel, cohorts, health bands, engagement, usage, top 5, team, and a system health card. It runs on the hourly `platform:aggregate` summary tables with a 5-minute cache, and each card loads on its own with its own skeleton and error state. That is shared-hosting safe.
- **Changed:**
  - **Include demo data** (off by default everywhere), with a label when it is on. Demo restaurants are those at a website demo address **and** with only @example.com logins.
  - **Compare with previous period** (on by default); turning it off hides every ↑/↓ %.
  - The overview is now in **5 groups**:
    - Restaurants: total, new, active, first guest view, paused now, deleted.
    - Plans: paying, free, upgrades, downgrades, cancellations.
    - Money: revenue, MRR, per paying restaurant, or **"No payments yet"**.
    - Guests: menu views, QR scans, dish views.
    - Engagement: dishes added, menu changes, added to "My order", "My order" lists shown.
  - Charts: sign-ups vs first guest view, views + scans, revenue per month, dishes added + menu changes.
  - New cards: **Activation** (signed up → added dishes → chose a style → published → 100 views), **Plans** (per plan, plus plan changes per week), **Styles, languages and places**. The **top 10** has a small line per row. Feature use covers photos, My order, QR printed, languages, PDF link, promotions and happy hour.
  - Every card has an **"i" button** in easy words and **Export CSV** (quoted, formula-safe, UTF-8).
  - A plan change recorded together with a payment is now logged, so it counts as an upgrade or downgrade.
  - The charts are small SVGs in the same style as the existing ones. The admin used no chart library: `recharts` is in package.json but unused, so I didn't add one.
- **Bugs fixed:**
  - A restaurant without a plan was counted twice in "all plans".
  - Styles M–T had no names on the dashboard.
  - The growth card had invalid list HTML (axe).
- **Time zone:** days are counted in UTC (the app's clock, as before). Times like "Updated …" show in the Super Admin's browser time.

#### Each chart: what data it uses, and privacy

| Card / chart | Data used | Privacy |
|---|---|---|
| Restaurants (total, new, deleted) | `tenants` (created, deleted) | restaurant data |
| Active per day | menu-view counters + team changes in the activity log | counts only |
| First guest view | first day with a menu-view counter | anonymous daily counter |
| Paused now | subscription state now | restaurant data |
| Plans, upgrades, downgrades, cancellations | current plan; plan moves in the activity log (`plan_id` from → to) | restaurant data |
| Revenue, MRR, per paying restaurant | `payments` | money data, no guest data |
| Menu views, QR scans, dish views | `stats_daily` daily counters (already collected) | anonymous totals, no IPs, no cookies |
| "My order" adds / lists shown | `stats_daily` (`order_add`, `order_shown`), already counted on the guest's phone | anonymous totals |
| Dishes added, menu changes | activity log of the restaurant teams (not Super Admin, not "log in as") | team actions, counted |
| Activation | restaurants' dishes, saved Appearance, first view, all-time views | restaurant data |
| Styles, languages | each restaurant's Appearance style and menu language | restaurant settings |
| Places | each restaurant's **time zone** city (addresses are free text) | restaurant setting |
| Top 10 + small line | menu-view counters of those 10 restaurants | anonymous totals |
| Unique visitors, guest devices, guests' light/dark | **not collected**; the cards say so | no new tracking |
| System (errors, failed jobs, queue) | existing System health card (log file count, `failed_jobs`, `jobs`) | — |

#### Speed
5,000 restaurants and a year of data (about 3.65 million counter rows), in the CI test:
- All 12 cards together: **137–177 ms uncached** (limit: each under 200 ms, all under 1.5 s).
- The hourly update takes **19–25 s**. The very first full build of 400 days takes **77–98 s**. Both run from the command line, which is fine on shared hosting.
- Cached card answers come straight from the 5-minute cache.

### 5. Landing 2
- **Backup:**
  - Landing 1 is **not changed**.
  - A copy of its templates is in `backend/resources/views/landing-v1-backup/` (with a README).
  - `LandingGoldenTest` compares the home page byte for byte with the page saved *before* my change (only the nonce and the site address are masked).
  - ⚠️ The git tag `landing-v1-2026-11-22` could not be pushed from here: the proxy blocks tags. Run: `git tag landing-v1-2026-11-22 9b0a73e && git push origin landing-v1-2026-11-22`.
- **3 directions:** A · Menu card, B · Bold blocks, C · Calm grid (`docs/landing2/concepts.webp`). **Picked B** because it has the strongest sign-up button, reads well on cheap phones, needs few pictures (fast), and the brand colour carries it in light and dark.
- **Content (live data):**
  - Hero with the live demo phone and "See a live menu".
  - 3 steps.
  - **All 20 styles A–T** with phone screenshots and demo links.
  - QR band with the demo's QR code.
  - Features: My order, languages, speed, insights.
  - **Plans and prices read live from the plans** (with no public plans, there is no prices section).
  - FAQ (the same answers the Landing editor controls).
  - Final band, a sticky button on phones, and the site footer (terms, privacy, contact, …).
- **Technical:** inline CSS, two tiny inline scripts, no libraries, lazy and sized images, motion that respects reduced motion, logical (start/end) CSS, and `dir="rtl"` for an Arabic site language. The current landing is English only, so Landing 2 is too.
- **SEO:** `/landing2` is noindex with the canonical set to `/` and has no structured data. Once picked, the home page has a title, description, Open Graph image, JSON-LD and canonical. The sitemap stays `/` and `/landing2` is never in it. `landing2` is a reserved restaurant address.
- **The switch:** Super Admin → Settings → General → **Home page design** (Landing 1 / Landing 2).
  - It saves at once and is logged (`platform.home_design_changed`).
  - It shows at once because saving empties the settings cache, and the home page has no saved copy, so there is no other page cache to clear.
  - **Switching back** gives exactly the old page (tested).

## Screenshots
Sent in the chat with this report, and saved in `docs/landing2/`:
- Landing 2: phone and desktop, light and dark, plus the 3 concepts.
- Super Admin Themes with the phone (desktop, and full screen on a phone).
- Settings with Terms closed.
- The dashboard on desktop and phone, light and dark.
- Appearance with sections closed and open, checked by tests and in the browser.

## Tests and checks

| Check | Result |
|---|---|
| Backend tests | **1,253 / 1,253 pass** (full run after the last change; 2 backup tests failed once because this sandbox's disk was full, and they pass on rerun) |
| Frontend tests | **1,661 / 1,665 pass**: all new and changed tests pass; 4 public-menu layout tests fail **in this sandbox only**, and they fail the same way on `develop` here (pixel positions; CI is green for them) |
| New backend tests | ThemePreviewTest 5, InsightsMoreTest 8, Landing2Test 6, LandingGoldenTest 1, LegalTextTest +2, speed test now 5,000 restaurants |
| New frontend tests | Appearance sections 12, Settings legal 7, Themes preview 5, Insights 9, dashboard switches 1, CSV 2 |
| axe | 0 violations: Appearance (closed/open), Settings, Themes, Insights; Landing 2 at 360/390/820/1280 px, light and dark |
| PHPStan, Pint, ESLint, Prettier, tsc, build | 0 errors; 0 new warnings (3 old ESLint warnings in files I didn't touch) |
| Lighthouse, local, mobile simulated | `/landing2` **100/100/100/100**, LCP 1.03 s, CLS 0 · home with Landing 2: **100 ×4**, LCP 1.03 s · Landing 1 home: 100 ×4, LCP 1.65 s (local `php artisan serve`; CI uses Nginx) |
| Lighthouse CI | `/landing2` added to the landing job with the same targets; the noindex/canonical checks are left out there on purpose |
| Guest menu speed | no guest menu code changed (`MenuPageData::build` has a new optional argument that only the theme preview uses) |

## What I could not test
- Pushing the backup **git tag** (blocked by the proxy).
- **Lighthouse CI on GitHub** for `/landing2`: it runs on the PR. I measured locally (above).
- The 4 public-menu layout tests in this sandbox (they also fail on develop here).
- Real Arabic texts on Landing 2 (the site has only English).

## Merge and run
1. Merge the PR **feat/admin-landing2** into `develop` (the owner merges).
2. Laragon (port 8000), in `C:\laragon\www\menu`:
   ```
   git checkout develop
   git pull
   cd backend
   php artisan migrate
   php artisan platform:aggregate
   cd ..\frontend
   pnpm install
   pnpm build
   ```
   Open http://localhost:8000/landing2 and http://localhost:8000/super. Then go to **Themes**, **Settings → General** and **Dashboard**.
3. On the live server, after a release and deploy: run `php artisan platform:aggregate` once to fill the new dashboard numbers (otherwise the hourly job does it within an hour).
