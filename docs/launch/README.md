# Launch kit

Everything to tell restaurants about the platform. Replace **[Name]** with your platform's name, **[site]** with your address (e.g. `menu.example.com`) and **[WhatsApp]** with your number. Simple English on purpose: owners read these on a phone.

## What is ready in the app

| What | Where | Notes |
|---|---|---|
| One-page sales sheet (A4) | Super Admin → Landing page → **Sales sheet (print or PDF)** | Live plans, prices, demo QR code and your contacts. In the print window choose **Save as PDF**. Update prices first (Super Admin → Plans). |
| Table tents (A6, two per A4), posters A5/A4, window sign, round and square stickers (12 per A4) | Each restaurant: **QR codes → Print materials** | Designs: Classic, Bold, Minimal and **Like my menu** (the colours of the restaurant's menu style). Light or dark. Posters can add 3 mm bleed for a print shop. |
| Social media pictures (square post 1080 × 1080, story 1080 × 1920) | **QR codes → Print materials → For social media** | The restaurant's colour, QR code and name. |
| Demo menus, one per style | `https://[site]/demo`, `/demo-jebena`, `/demo-firewood`, `/demo-green-spoon`, `/demo-dhow`, `/demo-maison`, … | The landing page shows all 17 under **More menu styles**. Only if you load the demo data (never on the live database; see LAUNCH_TODO.md). |
| Screenshots | Landing page: `backend/public/landing/` (hero, demo, 17 styles). Help: `backend/public/help/shots/`. Play Store: the app repository, `store/` | Regenerate after design changes: `node frontend/scripts/landing-screenshots.mjs`, `node frontend/scripts/help-screenshots.mjs`. |

## WhatsApp invite (send one by one, not to groups)

> Hello! 👋 I'm [your name] from [Name].
> We make QR menus for restaurants and cafés. Guests scan a code on the table and see your dishes, photos and prices. You change prices any time from your phone — no reprinting.
> It's free to start: [site]
> See a real example: [site]/demo
> Busy? Send me photos of your paper menu and we type it in for you. 🙂

## Facebook post

> **Your menu on every phone. Free to start.**
> Guests scan the QR code on the table and see your dishes, photos and prices — in English, Amharic, Somali or Arabic.
> ✅ Change a price in seconds, no reprinting
> ✅ "Sold out" with one tap
> ✅ 17 menu styles: coffee house, grill, garden, coastal, fine dining…
> ✅ Table tents and stickers ready to print
> Try the demo: [site]/demo · Start free: [site]
> No time? Send us photos of your paper menu and we add it for you.

Picture: a phone with a demo menu (the landing page's hero or the sales sheet).

## TikTok / Reels caption

> Paper menu ➡️ QR menu in 5 minutes 📱🍽️ Free to start at [site] #restaurant #cafe #addisababa #mogadishu #qrmenu #ethiopianfood #somalifood

## 30-second demo video (script)

| Time | Picture | Words (voice or captions) |
|---|---|---|
| 0–3 s | A torn paper menu, a price crossed out with a pen | "Still reprinting your menu every time a price changes?" |
| 3–8 s | A guest scans the QR table tent; the menu opens on the phone | "Guests scan the code on the table…" |
| 8–13 s | Scrolling the menu: photos, prices, a "Sold out" dish | "…and see your dishes, photos and prices." |
| 13–19 s | Owner's phone: Menu editor, change a price, tap Save; the guest phone refreshes | "Change a price in seconds. Every phone shows it at once." |
| 19–24 s | Quick cuts of 4 styles: Coffee House, Grill, Coastal, Fine Dining | "Pick a style that fits your place." |
| 24–30 s | Printing table tents; the logo and [site] on screen | "Start free today at [site]." |

Film the phones with the demo restaurants (`/demo-jebena`, `/demo-firewood`). Keep faces out of shot unless people agreed in writing.

## Talking points (in person)

- "It's free to start, no card." (Only while a free sign-up plan is offered: Super Admin → Settings → Sign-up.)
- "It works on any phone, no app for guests, and it's fast even on a slow connection."
- "If you're busy, give me photos of your menu; we type it in."
- Paid plans: show the sales sheet — prices come from Super Admin, so the sheet is always right.
