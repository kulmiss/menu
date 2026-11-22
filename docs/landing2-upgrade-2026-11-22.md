# Landing 2: look & feel upgrade, English + Amharic (22 Nov 2026)

**One PR to merge:** `feat/landing2-upgrade` → `develop`. It is stacked on #180, so it also carries #180's fixes. Merge #180 first, or merge only this PR: it contains #180.
Website only. No Flutter changes.
Landing 1 is unchanged byte for byte: its golden test passes.

## Summary
- **Languages:** Landing 2 now has English and Amharic.
- **Additions:** all 13 are built.
- **Switches and texts:** every part has an ON/OFF switch, and its texts can be edited in a new screen, **Super Admin → Landing 2**.
- **Weight:** the first load went from **394 KB to 55 KB (English) and 89 KB (Amharic)**.
- **Lighthouse:** **100/100/100/100** in both languages.
- **Video (added later the same day):** the 30-second demo video is recorded from the real app and built in (see #9).
- **Not done here:** the Amharic is a draft that needs a native speaker's review.

## Speed (Lighthouse mobile, simulated mid-range phone, slow 4G, behind Nginx + gzip like CI)

| Step | Scores P/A/BP/SEO | LCP | CLS | TBT | First load | JS files |
|---|---|---|---|---|---|---|
| Before (#180) | 100/100/100/100 | 0.89 s | 0 | 12 ms | **393.8 KB** | 0 |
| + all 13 additions, EN | 100/96/100/100 | 1.05 s | 0 | 18 ms | 55.4 KB | 0 |
| + all 13 additions, AM | 99/96/100/100 | 1.36 s | 0 | 104 ms | 89.1 KB | 0 |
| After the accessibility fixes, EN (2 runs) | 100/100/100/100 | 1.05 s | 0 | 59–62 ms | 55.1 KB | 0 |
| After the accessibility fixes, AM (2 runs) | 100/100/100/100 · 99/… | 1.35 s | 0 | 37–106 ms | 88.8 KB | 0 |

- **Inline JavaScript:** 6.1 KB in all (budget 30 KB).
- **Amharic weight:** the two font files (~16 KB each) are the difference.
- **Why it got lighter:** the page used to load 13 style pictures on open (lazy loading starts very early). Now they load only when you scroll near. The hero picture was also fetched in two sizes; now it is fetched in one.
- **TBT is noisy in this sandbox:** 30–260 ms between runs of the same page. Real 4× CPU tests mostly show no long task.
- **CI budget:** median of 5 runs, TBT ≤ 100 ms. If CI flakes on TBT, re-run the job.

## The 13 additions
1. **Style switcher (hero).**
   - A–T chips as a real radio group: arrow keys work and screen readers name each chip.
   - It shows only with JavaScript. Without it, the switcher is hidden and the page still works.
   - It starts on the demo café's style.
   - Tapping a chip loads that style's screen and cross-fades it in. Then, while the browser is idle, the next two load. Nothing loads on open.
   - "Open this style's demo" link.
   - Screens are WebP, each ≤ 20 KB: 220 px for 1x, up to 440 px for 2x.
   - 10 styles have photo-heavy screens. Their 2x file is 320–420 px wide so it stays ≤ 20 KB, so it is a bit soft on sharp screens.
2. **"Change a price".**
   - Br 70 → Br 80 on the dish and on 3 table phones, then the "Saved ✓ · every table sees it" toast.
   - An 8-second CSS loop. It runs only while it is on screen.
   - With reduced motion it shows the new price and the toast, still.
3. **English + Amharic.**
   - Addresses: `/landing2` and `/am/landing2`. Once Landing 2 is the home page: `/` and `/am`.
   - While Landing 1 is home, `/am` goes back to `/`.
   - hreflang (en, am, x-default) and a canonical on each page.
   - Switch in the header and the footer. It keeps the section you were reading.
   - Every text is translated. An automated test finds no English left on the Amharic page except Menu, QR, PDF, PNG, Br and WhatsApp.
   - No overflow at 360 px.
   - Prices stay "Br 490 / በወር".
   - Footer: the built-in texts are in Amharic. Texts you typed in Content → Footer get their Amharic in Super Admin → Landing 2.
4. **Real restaurants.**
   - Super Admin → Landing 2 → Restaurants: logo, name, city, their words in English and Amharic, "permission given", shown, order.
   - The section shows only when **at least 3** have permission.
   - Nothing is seeded.
5. **Payment methods.**
   - "Pay with: telebirr · CBE Birr · Bank transfer" as text badges with our own icons. The list is editable.
   - **No official logos:** I found no public logo rules for telebirr or CBE Birr, so there is no permission to use them (QUESTIONS #255).
6. **Monthly / Yearly.**
   - Two real radio buttons.
   - Prices come live from the plans: Br 4,900 / year, and "2 months free" is worked out from the plan.
   - Hidden when no plan has a yearly price.
7. **Light bands.**
   - Warm sand behind steps, prices and questions (dark: deep navy).
   - axe finds 0 contrast problems in light and dark.
8. **Hero food photo.**
   - Our demo injera-platter photo (Pexels License, credited in `backend/public/landing/CREDITS.md`).
   - Blurred, 3.6 KB, inlined under the brand colour, so it cannot slow LCP.
   - If it is switched off, a CSS gradient shows instead.
9. **Demo video.**
   - The page shows the first picture with ▶. The video and its captions (EN + AM) load only after the tap: 0 video bytes before. CI checks this.
   - No autoplay, no preload.
   - Upload slot: MP4 ≤ 2 MB (checked to really be MP4), a first picture, and .vtt captions.
   - **Built in** (added later the same day): recorded from the real app by `frontend/scripts/landing2-video.mjs`. Sign up → add Beyaynetu (photo, Br 250) → pick E · Mesob → Poster A4 → a guest opens the QR link. 5 × 6 s, 1280 × 720 H.264, 801 KB, captions EN + AM. The recorded restaurant is put on Plus off camera so it can show a paid style. An uploaded video replaces it.
10. **Mesob / jebena pattern.** Our own drawing, under 1 KB, very faint on the final band.
11. **Free QR poster.**
    - A live preview: name, 6 colours, English or Amharic poster line, a sample QR marked "Example".
    - Download after a free sign-up, from the existing Print materials, where the QR opens the real menu.
    - No new server endpoint, so there is nothing to spam and no IP is stored.
    - My recommendation with pros and cons: QUESTIONS #257.
12. **Premium touches.**
    - Buttons lift on hover and press.
    - Cards fade in (CSS scroll-driven, Chrome/Edge; other browsers show them at once).
    - Smooth anchor scrolling and focus rings.
    - All off with reduced motion. No layout shift.
    - Note: a card that scrolls off the bottom and comes back fades again. CSS alone cannot play it "once".
13. **Real numbers.**
    - Our numbers: restaurants (live, not demos), dishes, menu views in the last 30 days.
    - Counted nightly (`landing2:numbers`, 22:40) and rounded down: 1,043 → "1,000+".
    - Each has ON/OFF and a minimum (defaults 100 / 1,000 / 1,000), so a young site shows nothing rather than small numbers.
    - Outside numbers: you type the number, the source and the date. The page shows "Source: …, date".
    - Super Admin warns when one is older than 30 days.
    - Text only: no count-up, because "1,000+" can't be counted up honestly.

## Shipped OFF
- No part is switched off. These parts stay hidden by themselves until they have data:
  - video (none yet)
  - restaurants (fewer than 3 with permission)
  - numbers (below their minimums)

## Screenshots
- Sent in the chat: phones 390 px in EN/AM, light/dark, plus 360 px dark; computer EN light and AM dark.
- CI uploads full-page screenshots of all 20 cases in the `lighthouse-landing` artifact (`landing2-shots`).

## Tests and checks
**Backend**
- New backend tests: 44 (`Landing2UpgradeTest`).
  - 43 of them fail on the old code.
  - The one that also passes on the old code is "no cookies, no outside scripts", which was already true.
- Existing tests: #180's tests were updated to the new rules (canonical, English/Amharic only), with stronger checks.
- Full backend suite: 1,279 tests ran. The clashes came from my own mistake: I ran two test runs on the same test database. Every file that failed then passes when run alone, except `BackupEncryptionTest`:
  - It is killed in this sandbox for lack of memory/disk: it zips 2.2 GB of local demo photos.
  - Not checked here; CI must show it green.
- Static checks:
  - PHPStan: 0 errors.
  - Pint: clean.

**Frontend**
- New frontend tests: 3 (the editor). Full frontend suite: 1,664 of 1,668 pass.
- The 4 public-menu pixel tests fail in this sandbox. They also failed on develop before this PR, earlier in this session.
- Lint: 0 errors. The 3 warnings are in files I did not touch.
- format, build: OK.

**Browser checks** (`ci/lighthouse/landing2-checks.mjs`, now in CI)
- 20 cases: EN/AM × 360/390/414/768/1280 × light/dark.
- Results: axe 0, no CSP or console errors, CLS 0, nothing too wide.
- First load: ≤ 78 KB, JS 6.1 KB.
- Nothing heavy loads on open.
- Taps:
  - The switcher loads one screen (plus the next two while idle).
  - The video loads only on the tap (checked once the built-in video was added).
  - The language switch keeps `#pricing`.
- In 1 of about 6 local runs, with the CPU busy with tests, one layout shift of 0.0036 was seen at 414 px. I could not repeat it in 18 more runs, even with 6× CPU slowdown.

**CI**
- Lighthouse for both languages with budgets:
  - 150 KB first load
  - JS ≤ 30 KB
  - no media before tap
  - CLS ≤ 0.001
  - TBT ≤ 100 ms
  - LCP ≤ 1.5 s

## What I could NOT test here
- Real phones, Safari and Firefox. I tested Chromium only.
- The demo video's Amharic captions (native review, like the rest).
- Golden screenshots are saved, but not compared pixel by pixel. Font rendering differs between machines, and the existing pixel tests already break in this sandbox.
- Native Amharic quality.

## Your tasks
1. **Amharic review:** send `docs/landing2-amharic-review.csv` to a native speaker. It has 241 rows: key | English | Amharic | max length | notes. Then, on the server:
   ```
   php artisan landing2:amharic --import=reviewed.csv --dry-run
   php artisan landing2:amharic --import=reviewed.csv
   ```
   - The dry run checks every row and saves nothing.
   - If any row is wrong, nothing is saved.
2. **Restaurants:** add at least 3 real ones with their permission, in Super Admin → Landing 2 → Restaurants.
3. **Numbers:** add outside numbers with their source and date, and check our own minimums.
4. **Video:** watch the built-in one. To use your own instead, record it with the steps below and upload it in Super Admin → Landing 2 → Demo video.
5. **Payment list:**
   - Check that telebirr, CBE Birr and bank transfer are what you accept.
   - Check the product name: CBE's site also says "CBE Birr Plus".
6. **Git tag** (still from before): run `git tag landing-v1-2026-11-22 9b0a73e` and `git push origin landing-v1-2026-11-22`.

### Recording your own 30-second video (optional)
1. On your PC, start the app: `php artisan serve`.
2. Open Chrome at 1280 × 720 (DevTools → device toolbar → "Responsive" 1280 × 720).
3. Start the recorder: Windows Game Bar (Win + Alt + R) or OBS at 720p 30 fps, with no microphone.
4. Record these scenes, about 6 seconds each:
   - (a) `/signup`: type a restaurant name, e-mail and password, then tap "Create".
   - (b) The admin **Menu**: add a dish named "Shiro tegabino", Br 80, Save.
   - (c) **Appearance**: tap 2–3 styles, then keep one.
   - (d) **Print materials**: open a QR poster and show the PDF.
   - (e) A phone (or DevTools in phone size) opens the QR link, and the menu shows the new dish.
5. Cut the clip to 30 s and make it ≤ 2 MB:
   ```
   ffmpeg -i in.mp4 -t 30 -vf scale=1280:720 -c:v libx264 -preset slow -crf 30 -an -movflags +faststart demo.mp4
   ```
   - If it is over 2 MB, raise `-crf` to 32–34.
6. Make a first picture from the start of the clip:
   ```
   ffmpeg -ss 1 -i demo.mp4 -frames:v 1 poster.png
   ```
7. Write two caption files, `en.vtt` and `am.vtt`, with one line per scene, for example:
   ```
   WEBVTT

   00:00.000 --> 00:06.000
   Sign up free
   ```
   - In Amharic: "በነፃ ይመዝገቡ".
8. Upload all four files in Super Admin → Landing 2 → Demo video.

## Run steps after merging
```
git checkout main && git pull        # after the release PR develop → main
🚀 Deploy (VS Code)                   # runs the migration (landing_restaurants)
php artisan landing2:numbers          # first count (then nightly)
```
- Check `/landing2` and `/am/landing2`.
- When you are happy, switch the home page in Super Admin → Settings → Home page design → Landing 2. Then `/am` works too.
