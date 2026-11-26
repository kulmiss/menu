# Redesign for restaurant admins (2026-11)

The app now looks like the web admin (same shadcn tokens) and puts the jobs restaurant admins do every day on Home. Super Admin work moved to the web; the app keeps a small emergency screen.

## Top 5 tasks

| # | Task | Before (taps) | After (taps) |
|---|---|---|---|
| 1 | Change a price | Menu → dish → scroll to price → edit → Save (5) | Home → Change a price → dish → type → Save (4, no full editor) · Menu → tap the price → type → Save (3) |
| 2 | Hide / show a dish | Menu → dish → scroll → switch → Save (5) | Home → Hide or show → switch (2, saved at once) · Menu → long press → Hide (2) |
| 3 | Add a dish with a photo | Menu → + Dish → Add photo (3 before the camera) | Home → Add dish with photo (1, camera/gallery opens) |
| 4 | Share the menu link / QR | More → Open my menu (2) | Home → Share (1) |
| 5 | Check today's numbers | Home, scroll to the 7-day cards | Home, first card: views and QR scans today vs yesterday, sold out now |

## Navigation
- **Restaurant**: Home · Menu · Orders · More (Orders only with the plan's "My order" lists, for Owners/Managers). Sold out and Insights are on Home and in More. Tablets get the same items as a rail.
- **Home**: today card → quick actions (change a price, hide/show, add dish with photo, share link/QR, mark sold out, promotions; each only when the role and plan allow it) → setup checklist → 7-day chart → missing photos → plan → news → Manage (Menu, My order, Branches, QR codes, Settings).
- **Super Admin**: one screen — overview, four emergency switches (app maintenance, self sign-up, push notifications, Super Admin app sign-in), "Open Super Admin on the web".

## Design tokens
`lib/app/theme.dart` → `AppTokens` (a `ThemeExtension`, read with `context.tokens`). Values are the web's `frontend/src/styles/theme.css`, converted from OKLCH to sRGB.

| Token | Light | Dark |
|---|---|---|
| background | `#FFFFFF` | `#020618` |
| foreground | `#020618` | `#F8FAFC` |
| card | `#FFFFFF` | `#020919` |
| primary / primary-foreground | `#0F172B` / `#F8FAFC` | `#E2E8F0` / `#0F172B` |
| secondary · muted · accent | `#F1F5F9` | `#1D293D` |
| muted-foreground | `#62748E` | `#90A1B9` |
| destructive | `#E7000B` | `#FF6467` |
| border · input | `#E2E8F0` | white 10 % · 15 % |
| ring | `#90A1B9` | `#6A7282` |

Radius 10 (sm 6 · md 8 · lg 10 · xl 14), 16 px screen padding, 48 px touch targets, Inter, Lucide icons. The theme follows the phone; Settings › Theme switches Phone / Light / Dark (saved on the device). Differences from the web are in QUESTIONS M38.

## Components (`lib/core/widgets/ui.dart`)
Button (primary, secondary, outline, ghost, destructive; loading), Badge, Card, Sheet (`showAppSheet`, bottom sheet for quick edits), Toast (`showToast`, Sonner-like; `showSnack`/`runAction` use it), SkeletonBox, PageTitle (large title). Input, Switch, Tabs, Dialog, segmented buttons and the bottom bar are styled in the theme. Empty states: icon + one line + action.

Icons: `AppIcons.*` from `lib/app/icons.dart`, generated with `python3 tool/icons/gen.py <lucide.ttf> <codepoints.json>` from `tool/icons/names.txt`.

## Accessibility
48 px touch targets (tested with the Android tap-target guideline), contrast ≥ 4.5:1 for text in both themes (`test/core/theme_tokens_test.dart`), text scale follows the phone up to 130 %, semantics on icon buttons and the price button.

## Screenshots
Before: `docs/screens/before/`, after: `docs/screens/after/` — Sign-in, Home, Menu list, Dish edit, Settings, each `_light` and `_dark`. Regenerate the after set on Linux:

```
COMPARE_DIR=after flutter test --run-skipped -t compare --update-goldens test/compare
```

Goldens (CI): `test/goldens/images/` — `screen_{home,menu,orders,more,settings,super_admin}_{light,dark}`, `ui_kit_{light,dark}`, sign-in and sold-out (phone + tablet).

## Size and start-up
Release APK, arm64, same machine and flags as CI (`--release --split-per-abi --target-platform android-arm64 --obfuscate`):

| | Before (develop) | After (#21) | Change |
|---|---|---|---|
| APK file | 25,378,414 B (24.2 MB) | 25,322,621 B (24.1 MB) | −55.8 KB |
| Download (gzip −9, the budget in M34) | 12,144,480 B (11.6 MB) | 12,119,441 B (11.6 MB) | −25.0 KB |

The Lucide font adds a few KB after icon tree-shaking; removing the Super Admin screens and 203 unused strings takes more out.

Start-up: nothing new waits before the first frame except the saved theme choice, read from the device together with the token, lock and analytics settings (one `Future.wait`, no network). Firebase still starts after the first frame. To measure on a phone:

```
flutter run --profile --trace-startup --dart-define=API_BASE_URL=https://<your domain>
# then open build/start_up_info.json: timeToFirstFrameMicros (budget: usable < 1.5 s)
```

Run it on `develop` before merging and on the last redesign branch after, same phone, cold start (swipe the app away first), three times each.
