// Landing 2's 30-second demo video (owner, 2026-11-22): a real recording of the app, no
// sound, five scenes of about 6 s: sign up → add a dish → pick a style → print the QR code
// → a guest scans it. Run against a LOCAL app only (it signs up a new restaurant and changes
// it in the database to skip the e-mail check and to try a paid style):
//   php artisan serve   (backend, with the demo data)
//   node scripts/landing2-video.mjs [http://127.0.0.1:8000]
// Writes backend/public/landing/video/demo.mp4 (H.264, 1280 × 720, ≤ 2 MB), poster.webp,
// en.vtt and am.vtt. Needs ffmpeg and the mysql client (database "menu" by default: DB env).
import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import { mkdirSync, mkdtempSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const base = process.argv[2] ?? 'http://127.0.0.1:8000'
const db = process.env.DB ?? 'menu'
const out = new URL('../../backend/public/landing/video/', import.meta.url).pathname
const photo = new URL('../../backend/database/seeders/demo-photos/injera-platter.webp', import.meta.url).pathname
const tmp = mkdtempSync(join(tmpdir(), 'l2video-'))
mkdirSync(out, { recursive: true })
const sql = (q) => execFileSync('mysql', [db, '-N', '-e', q]).toString().trim()
const wait = (ms) => new Promise((r) => setTimeout(r, ms))
const email = `video${Date.now()}@example.com`
const SCENES = [
  ['Sign up free', 'በነፃ ይመዝገቡ'],
  ['Add a dish with its photo and price', 'ምግብ በፎቶውና በዋጋው ያክሉ'],
  ['Pick a menu style', 'የሜኑ ስታይል ይምረጡ'],
  ['Print the QR code for your tables', 'ለጠረጴዛዎችዎ QR ኮዱን ያትሙ'],
  ['A guest scans it: your menu opens', 'እንግዳ ይቃኛል፦ ሜኑዎ ይከፈታል'],
]
const SCENE_SECONDS = 6

const browser = await chromium.launch()
const files = []
let state

/** One scene in its own recorded browser window; returns after the page is closed. */
async function scene(name, viewport, fn) {
  const dir = join(tmp, name)
  const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, recordVideo: { dir, size: viewport }, ...(state ? { storageState: state } : {}) })
  const page = await ctx.newPage()
  await fn(page)
  state = await ctx.storageState()
  await page.close()
  await ctx.close()
  files.push(join(dir, readdirSync(dir)[0]))
}
const desk = { width: 1280, height: 720 }
const type = (loc, text) => loc.pressSequentially(text, { delay: 45 })

// 1. Sign up.
await scene('1', desk, async (p) => {
  await p.goto(`${base}/signup`)
  await wait(500)
  await type(p.locator('input[name=name]'), 'Selam Tesfaye')
  await type(p.locator('input[name=restaurant_name]'), 'Selam Café')
  await type(p.locator('input[name=email]'), email)
  await type(p.locator('input[name=password]'), 'Video-demo-2026!')
  await p.check('input[name=terms]')
  await wait(300)
  await p.click('button[type=submit]')
  await p.waitForURL(/verify-email/)
  await wait(600)
})
// Off camera: e-mail confirmed, and the restaurant on Plus to try a paid style.
const tenant = sql(`select t.id from tenants t join memberships m on m.tenant_id=t.id join users u on u.id=m.user_id where u.email='${email}'`)
sql(`update users set email_verified_at=now() where email='${email}'`)
sql(`update tenants set signup_state=null, plan_id=(select id from plans where slug='plus') where id=${tenant}`)
const slug = sql(`select slug from tenants where id=${tenant}`)

// Off camera: the first menu and category (the scene starts at "Add item").
{
  const ctx = await browser.newContext({ viewport: desk, storageState: state })
  const p = await ctx.newPage()
  await p.goto(`${base}/admin`)
  await wait(2000)
  const skip = p.getByRole('button', { name: 'Skip tour' })
  if (await skip.count()) await skip.click()
  await p.goto(`${base}/admin/menu`)
  await p.getByRole('button', { name: 'Create your first menu' }).click()
  await p.getByLabel('Name').fill('Main menu')
  await p.getByRole('button', { name: 'Save' }).click()
  await p.getByRole('button', { name: 'Add category' }).click()
  await p.getByRole('dialog').getByLabel('Name').fill('Ethiopian classics')
  await p.getByRole('button', { name: 'Save' }).click()
  await p.getByRole('button', { name: 'Add item' }).waitFor()
  state = await ctx.storageState()
  await ctx.close()
}

// 2. Add a dish.
await scene('2', desk, async (p) => {
  await p.goto(`${base}/admin/menu`)
  await p.getByRole('button', { name: 'Add item' }).click()
  const d = p.getByRole('dialog')
  await d.locator('input[type=file]:not([capture])').first().setInputFiles(photo)
  await wait(1200)
  await p.getByRole('button', { name: 'Use photo' }).click()
  await wait(900)
  const d2 = p.getByRole('dialog', { name: 'New item' })
  await type(d2.getByLabel('Name', { exact: true }), 'Beyaynetu')
  await type(d2.getByLabel('Short line', { exact: true }), 'Six fasting stews on injera')
  await d2.getByRole('tab', { name: 'Prices & sizes' }).click()
  await type(d2.getByLabel('Price', { exact: true }), '250')
  await wait(300)
  await d2.getByRole('button', { name: 'Save' }).click()
  await p.getByText('Beyaynetu').first().waitFor()
  await wait(1200)
})

// 3. Pick a style (watch the phone preview).
await scene('3', desk, async (p) => {
  await p.goto(`${base}/admin/appearance`)
  await wait(1500)
  for (const name of ['E · Mesob', 'S · Injera & Tibs', 'E · Mesob']) {
    await p.locator('label', { hasText: name }).first().click()
    await wait(1300)
  }
  await p.getByRole('button', { name: 'Save', exact: true }).click()
  await wait(1200)
})

// 4. Print the QR code.
await scene('4', desk, async (p) => {
  await p.goto(`${base}/admin/print-materials`)
  await wait(1200)
  await p.getByRole('button', { name: /Poster A4/ }).click()
  await wait(1000)
  await p.getByRole('button', { name: 'Bold', exact: true }).click()
  await wait(1500)
  await p.getByRole('button', { name: 'Like my menu', exact: true }).click()
  await wait(1800)
})

// 5. A guest scans the code: the menu opens on a phone.
const code = sql(`select code from qr_codes where tenant_id=${tenant} and deleted_at is null order by id limit 1`)
await scene('5', { width: 390, height: 720 }, async (p) => {
  await p.goto(`${base}/q/${code}`)
  await wait(1800)
  await p.mouse.wheel(0, 260)
  await wait(1500)
  await p.getByText('Beyaynetu').first().click()
  await wait(2200)
})
await browser.close()

// Each scene to 6 s (sped up or slowed down), the phone scene centred on a dark background,
// joined, encoded small. Then the first picture and the captions.
const parts = files.map((f, i) => {
  const len = Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f]).toString())
  const speed = (SCENE_SECONDS / len).toFixed(4)
  const pad = i === 4 ? ',pad=1280:720:(ow-iw)/2:0:color=0x0f172a' : ''
  const o = join(tmp, `s${i}.mp4`)
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-i', f, '-vf', `setpts=${speed}*PTS,fps=30${pad},format=yuv420p`, '-t', String(SCENE_SECONDS), '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '18', o])
  return o
})
writeFileSync(join(tmp, 'list.txt'), parts.map((p) => `file '${p}'`).join('\n'))
for (const crf of [30, 32, 34, 36]) {
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', join(tmp, 'list.txt'), '-c:v', 'libx264', '-profile:v', 'high', '-preset', 'slow', '-crf', String(crf), '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', join(out, 'demo.mp4')])
  if (statSync(join(out, 'demo.mp4')).size <= 2 * 1024 * 1024) break
}
const poster = join(tmp, 'poster.png')
execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-ss', String(SCENE_SECONDS + 4.5), '-i', join(out, 'demo.mp4'), '-frames:v', '1', '-vf', 'scale=960:540', poster])
execFileSync('php', ['-r', 'imagewebp(imagecreatefrompng($argv[1]), $argv[2], 70);', poster, join(out, 'poster.webp')])
const t = (s) => `00:${String(Math.floor(s)).padStart(2, '0')}.000`
for (const [lang, col] of [['en', 0], ['am', 1]]) {
  writeFileSync(join(out, `${lang}.vtt`), 'WEBVTT\n\n' + SCENES.map((s, i) => `${t(i * SCENE_SECONDS)} --> ${t((i + 1) * SCENE_SECONDS)}\n${s[col]}\n`).join('\n'))
}
console.log(`demo.mp4 ${(statSync(join(out, 'demo.mp4')).size / 1024).toFixed(0)} KB, restaurant /${slug} (${email}), files in ${out}`)
