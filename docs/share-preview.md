# Link previews (WhatsApp, Telegram, Facebook, X, iMessage)

When a menu link is shared, the app reads the `og:` and `twitter:` tags in the page's `<head>`:

| Tag | Value |
|---|---|
| `og:title` / `twitter:title` | Restaurant name, plus " · Branch" when there are several branches |
| `og:description` | The restaurant's "About" text (first 155 characters), else "The menu of …: dishes, prices and opening hours." |
| `og:image` / `twitter:image` | The banner's 1200 × 630 JPEG; without a banner, the default from Platform → Settings → Link preview, else the built-in `public/images/share-default.jpg` |
| `og:image:width` / `height` / `type` / `alt` | 1200 / 630 / image/jpeg / "Name — menu" |
| `og:url`, canonical | The branch's own address, absolute, on `APP_URL` |
| `twitter:card` | `summary_large_image` |

Every address starts with `APP_URL`, so set `APP_URL=https://your-domain` in `.env`. Image addresses carry `?v=`, which changes when the picture changes, so apps fetch the new one. The menu page itself never loads the picture.

Older banners get their preview picture with `php artisan media:regenerate`.

## Checking after go-live

1. Open the menu address in a browser and use "View page source". The tags above should be in the `<head>`, with `https://` addresses.
2. **Facebook Sharing Debugger**: go to https://developers.facebook.com/tools/debug/ (sign in with any Facebook account). Paste the menu address, for example `https://your-domain/buna-bread/piassa`, then press **Debug**.
   - "Link preview" should show the banner (or the default picture), the name with the branch, and the description.
   - Warnings about `fb:app_id` can be ignored.
   - After you change the banner, press **Scrape Again** so Facebook drops its old copy. WhatsApp and Telegram keep their own copies for a while; the new `?v=` address makes them fetch the new picture on the next share.
3. **Telegram**: send the link to yourself in "Saved Messages". To refresh an old preview, send it to @WebpageBot.
4. **X**: post the link in a draft. The card preview appears in the composer.
5. **WhatsApp**: send the link to yourself. The preview appears before you press send.

The picture must be reachable from the internet over https, without a login. A server that blocks unknown bots also blocks these previews.
