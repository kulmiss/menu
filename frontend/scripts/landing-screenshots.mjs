// Phone screenshots of the demo menus for the public landing page (/).
// Needs the demo data (php artisan db:seed --class=DemoSeeder) and the app
// running (php artisan serve). Writes WebP files (300, 450 and 600 px wide) to
// backend/public/landing/. Usage:
//   node scripts/landing-screenshots.mjs [http://127.0.0.1:8000]
import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import { mkdirSync, mkdtempSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const base = process.argv[2] ?? 'http://127.0.0.1:8000'
const out = new URL('../../backend/public/landing/', import.meta.url).pathname
const tmp = mkdtempSync(join(tmpdir(), 'landing-'))
mkdirSync(out, { recursive: true })

// Style key → demo restaurant (DemoSeeder).
const shots = {
  hero: 'demo', // B · Grid, the café
  editorial: 'demo-fine',
  grid: 'demo-pizza',
  bistro: 'demo-bakery',
  minimal: 'demo-nine-wells-coffee',
}

const browser = await chromium.launch()
const ctx = await browser.newContext({
  viewport: { width: 375, height: 760 },
  deviceScaleFactor: 2,
  colorScheme: 'light',
})
for (const [name, slug] of Object.entries(shots)) {
  const page = await ctx.newPage()
  await page.goto(`${base}/${slug}`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(800)
  const png = join(tmp, `${name}.png`)
  await page.screenshot({ path: png })
  await page.close()
  // Resize and encode with PHP's GD (already needed by the app).
  for (const width of [300, 450, 600]) {
    execFileSync('php', [
      '-r',
      `$s=imagecreatefrompng($argv[1]);$w=(int)$argv[3];$h=(int)round(imagesy($s)*$w/imagesx($s));$d=imagecreatetruecolor($w,$h);imagecopyresampled($d,$s,0,0,0,0,$w,$h,imagesx($s),imagesy($s));imagewebp($d,$argv[2],72);`,
      png,
      join(out, `${name}-${width}.webp`),
      String(width),
    ])
  }
  console.log(`${name}: /${slug}`)
}
// Share picture (1200 × 630) for the landing page: three phones on a soft
// gradient. No text, so it stays right when the platform is renamed.
const og = await browser.newPage({ viewport: { width: 1200, height: 630 } })
const img = (name) => `data:image/webp;base64,${readFileSync(join(out, `${name}-600.webp`)).toString('base64')}`
await og.setContent(`<!doctype html><body style="margin:0;width:1200px;height:630px;display:flex;align-items:center;justify-content:center;gap:40px;background:radial-gradient(60% 80% at 20% 10%,#fde3d4,transparent 70%),radial-gradient(50% 70% at 90% 90%,#fbd0b9,transparent 70%),#fff7f2;overflow:hidden">
${['editorial', 'hero', 'minimal'].map((n, i) => `<div style="width:${i === 1 ? 300 : 250}px;padding:${i === 1 ? 12 : 10}px;border-radius:${i === 1 ? 48 : 40}px;background:#0b0f19;box-shadow:0 30px 60px -20px rgba(15,23,42,.45);transform:translateY(${i === 1 ? 60 : 110}px)"><img src="${img(n)}" style="display:block;width:100%;border-radius:${i === 1 ? 38 : 32}px"></div>`).join('')}
</body>`)
const ogPng = join(tmp, 'og.png')
await og.screenshot({ path: ogPng })
execFileSync('php', ['-r', 'imagejpeg(imagecreatefrompng($argv[1]), $argv[2], 82);', ogPng, join(out, 'og.jpg')])
console.log('og.jpg')
await browser.close()
