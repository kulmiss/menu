// Phone screenshots of the demo menus for the public landing page (/), in
// light and dark mode (owner, 2026-11-17). Needs the demo data (php artisan
// db:seed --class=DemoSeeder) and the app running (php artisan serve). Writes
// WebP files (300, 450 and 600 px wide) to backend/public/landing/, and AVIF
// copies when PHP's GD can write AVIF (else old AVIF copies are deleted, and
// the page then uses WebP only). Usage:
//   node scripts/landing-screenshots.mjs [http://127.0.0.1:8000] [styles]
// ("styles": only the "More menu styles" pictures, nothing else is touched.)
import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import { mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const base = process.argv[2] ?? 'http://127.0.0.1:8000'
const onlyStyles = process.argv[3] === 'styles'
const out = new URL('../../backend/public/landing/', import.meta.url).pathname
const tmp = mkdtempSync(join(tmpdir(), 'landing-'))
mkdirSync(out, { recursive: true })

// File name → demo restaurant (DemoSeeder): the hero phone and the phone in
// "See it on your phone"; each also as "<name>-dark".
const shots = {
  hero: 'demo', // the café
  demo: 'demo-pizza', // B · Grid
}
const avif =
  execFileSync('php', ['-r', "echo function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false) ? 1 : 0;"]).toString() === '1'

const browser = await chromium.launch()
if (!onlyStyles) {
for (const scheme of ['light', 'dark']) {
  // 375 × 760 CSS px at 1.6× = 600 × 1216, the phone frame's shape.
  const ctx = await browser.newContext({
    viewport: { width: 375, height: 760 },
    deviceScaleFactor: 1.6,
    colorScheme: scheme,
  })
  for (const [shot, slug] of Object.entries(shots)) {
    const name = scheme === 'dark' ? `${shot}-dark` : shot
    const page = await ctx.newPage()
    await page.goto(`${base}/${slug}`, { waitUntil: 'networkidle' })
    await page.waitForTimeout(800)
    const png = join(tmp, `${name}.png`)
    await page.screenshot({ path: png })
    await page.close()
    // Resize and encode with PHP's GD (already needed by the app).
    for (const width of [300, 450, 600]) {
      for (const format of avif ? ['webp', 'avif'] : ['webp']) {
        execFileSync('php', [
          '-r',
          `$s=imagecreatefrompng($argv[1]);$w=(int)$argv[3];$h=(int)round(imagesy($s)*$w/imagesx($s));$d=imagecreatetruecolor($w,$h);imagecopyresampled($d,$s,0,0,0,0,$w,$h,imagesx($s),imagesy($s));$argv[4]==='avif'?imageavif($d,$argv[2],55,6):imagewebp($d,$argv[2],78);`,
          png,
          join(out, `${name}-${width}.${format}`),
          String(width),
          format,
        ])
      }
      if (!avif) rmSync(join(out, `${name}-${width}.avif`), { force: true })
    }
    console.log(`${name}: /${slug}`)
  }
  await ctx.close()
}
// Share picture (1200 × 630) for the landing page: three phones on a soft
// gradient. No text, so it stays right when the platform is renamed.
const og = await browser.newPage({ viewport: { width: 1200, height: 630 } })
const img = (name) => `data:image/webp;base64,${readFileSync(join(out, `${name}-600.webp`)).toString('base64')}`
await og.setContent(`<!doctype html><body style="margin:0;width:1200px;height:630px;display:flex;align-items:center;justify-content:center;gap:40px;background:radial-gradient(60% 80% at 20% 10%,#fde3d4,transparent 70%),radial-gradient(50% 70% at 90% 90%,#fbd0b9,transparent 70%),#fff7f2;overflow:hidden">
${['demo', 'hero', 'demo-dark'].map((n, i) => `<div style="width:${i === 1 ? 300 : 250}px;padding:${i === 1 ? 12 : 10}px;border-radius:${i === 1 ? 48 : 40}px;background:#0b0f19;box-shadow:0 30px 60px -20px rgba(15,23,42,.45);transform:translateY(${i === 1 ? 60 : 110}px)"><img src="${img(n)}" style="display:block;width:100%;border-radius:${i === 1 ? 38 : 32}px"></div>`).join('')}
</body>`)
const ogPng = join(tmp, 'og.png')
await og.screenshot({ path: ogPng })
execFileSync('php', ['-r', 'imagejpeg(imagecreatefrompng($argv[1]), $argv[2], 82);', ogPng, join(out, 'og.jpg')])
console.log('og.jpg')
}
// The "Menu styles" strip under the demo (owner, 2026-11-19): the top of each
// style's demo restaurant (SiteController::STYLE_DEMOS), light mode, 180 and 360 px.
const styles = {
  editorial: 'demo-fine', grid: 'demo-pizza', bistro: 'demo-bakery', minimal: 'demo-nine-wells-coffee',
  mesob: 'demo-mesob', lounge: 'demo-lounge', board: 'demo-street', arabic: 'demo-mandi',
  islamic: 'demo-halal', somali: 'demo-xamar', mono: 'demo-atelier', colour: 'demo-juicy',
  coffee: 'demo-jebena', grill: 'demo-firewood', garden: 'demo-green-spoon', coastal: 'demo-dhow',
  luxe: 'demo-maison', burger: 'demo-bigbite', injera: 'demo-shekla', juice: 'demo-spris',
}
mkdirSync(join(out, 'styles'), { recursive: true })
const thumbs = await browser.newContext({ viewport: { width: 375, height: 760 }, deviceScaleFactor: 2, colorScheme: 'light' })
for (const [style, slug] of Object.entries(styles)) {
  const page = await thumbs.newPage()
  const res = await page.goto(`${base}/${slug}`, { waitUntil: 'networkidle' })
  if (!res?.ok()) {
    console.log(`styles/${style}: /${slug} not found, skipped`)
    await page.close()
    continue
  }
  await page.waitForTimeout(800)
  const png = join(tmp, `style-${style}.png`)
  await page.screenshot({ path: png })
  await page.close()
  for (const width of [180, 360]) {
    for (const format of avif ? ['webp', 'avif'] : ['webp']) {
      execFileSync('php', [
        '-r',
        `$s=imagecreatefrompng($argv[1]);$w=(int)$argv[3];$h=(int)round(imagesy($s)*$w/imagesx($s));$d=imagecreatetruecolor($w,$h);imagecopyresampled($d,$s,0,0,0,0,$w,$h,imagesx($s),imagesy($s));$argv[4]==='avif'?imageavif($d,$argv[2],55,6):imagewebp($d,$argv[2],78);`,
        png,
        join(out, 'styles', `${style}-${width}.${format}`),
        String(width),
        format,
      ])
    }
    if (!avif) rmSync(join(out, 'styles', `${style}-${width}.avif`), { force: true })
  }
  console.log(`styles/${style}: /${slug}`)
}
await thumbs.close()
if (!avif) console.log('PHP GD cannot write AVIF here: WebP only.')
await browser.close()
