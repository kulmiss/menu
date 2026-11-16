// Screenshots for the Help centre (backend QUESTIONS #194), from the demo data.
// Needs: php artisan migrate:fresh --seed --seeder=DemoSeeder, the admin built
// (pnpm build) and the app running (php artisan serve). Writes WebP files to
// backend/public/help/shots/: `<name>-phone.webp` (390 px) and
// `<name>-desktop.webp` (1000 px). Usage:
//   node scripts/help-screenshots.mjs [http://127.0.0.1:8000] [only-these,names]
// See docs/help-screenshots.md. Re-take them after a screen changes.
import { chromium } from 'playwright'
import { createHmac } from 'node:crypto'
import { execFileSync } from 'node:child_process'
import { mkdirSync, mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const base = process.argv[2] ?? 'http://127.0.0.1:8000'
const only = process.argv[3]?.split(',')
const out = new URL('../../backend/public/help/shots/', import.meta.url).pathname
const tmp = mkdtempSync(join(tmpdir(), 'help-'))
mkdirSync(out, { recursive: true })

/** Six-digit code for the demo Super Admin (DemoSeeder::ADMIN_2FA_SECRET). */
export function totp(secret, at = Date.now()) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  let bits = ''
  for (const c of secret.replace(/=+$/, '')) bits += alphabet.indexOf(c).toString(2).padStart(5, '0')
  const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)))
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(Math.floor(at / 30000)))
  const h = createHmac('sha1', key).update(counter).digest()
  const o = h[h.length - 1] & 15
  return String((h.readUInt32BE(o) & 0x7fffffff) % 1_000_000).padStart(6, '0')
}

const sizes = {
  phone: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, width: 390 },
  desktop: { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 1, width: 1000 },
}

/** A request as the signed-in page (cookie session, XSRF header, idempotency key). */
const call = (page, method, url, body) =>
  page.evaluate(
    async ([method, url, body]) => {
      const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')
      const r = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf, 'Idempotency-Key': crypto.randomUUID() },
        body: body ? JSON.stringify(body) : undefined,
      })
      return r.status
    },
    [method, url, body]
  )

const settle = async (page) => {
  await page.waitForLoadState('networkidle').catch(() => {})
  await page.waitForTimeout(700)
}

/** Each shot: where to go, and what to do before the picture. */
const shots = {
  signup: { path: '/auth/sign-up', guest: true },
  dashboard: { path: '/admin' },
  'menu-editor': { path: '/admin/menu' },
  'dish-editor': {
    path: '/admin/menu',
    run: async (page) => {
      await page.locator('button.text-start:not([disabled])').first().click()
    },
  },
  dishes: {
    path: '/admin/dishes',
    run: async (page) => {
      const boxes = page.getByRole('checkbox')
      for (const i of [1, 2, 3]) await boxes.nth(i).check().catch(() => {})
    },
  },
  import: { path: '/admin/import' },
  appearance: { path: '/admin/appearance' },
  branches: { path: '/admin/branches' },
  'qr-codes': { path: '/admin/qr-codes' },
  'print-materials': { path: '/admin/print-materials' },
  promotions: { path: '/admin/promotions' },
  'happy-hour': {
    path: '/admin/promotions',
    run: async (page) => {
      await page.getByText('Price periods').first().scrollIntoViewIfNeeded().catch(() => {})
    },
  },
  insights: { path: '/admin/insights' },
  team: { path: '/admin/team' },
  security: { path: '/admin/settings/security' },
  billing: { path: '/admin/settings/billing' },
  'help-centre': { path: '/admin/help' },
  'help-button': {
    path: '/admin/menu',
    run: async (page) => {
      await page.getByRole('button', { name: 'Help for this page' }).click()
    },
  },
  // The welcome tour (first sign-in): shown again by resetting "seen".
  tour: {
    path: '/admin/help',
    run: async (page) => {
      await page.getByRole('button', { name: 'Take the tour' }).click()
      await page.getByRole('button', { name: 'Next' }).click()
    },
  },
  // The Super Admin setup wizard (signed in as the demo Super Admin).
  'wizard-welcome': { path: '/platform', admin: true, wizard: 0 },
  'wizard-plans': { path: '/platform', admin: true, wizard: 2 },
  'wizard-switches': { path: '/platform', admin: true, wizard: 4 },
  'guest-menu': { path: '/demo', guest: true },
  'guest-my-order': {
    path: '/demo',
    guest: true,
    run: async (page) => {
      // Tap + on two dishes without choices, then open the list.
      for (const id of ['7', '13']) {
        await page.locator(`li[data-i="${id}"] .ad`).click()
        await page.waitForTimeout(300)
      }
      await page.locator('button.ob').click()
    },
  },
}

let lastCode = ''

async function signIn(page, email, password, secret) {
  await page.goto(`${base}/auth/sign-in`)
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  if (secret) {
    // A code works once: wait for the next one when it was just used.
    while (totp(secret) === lastCode) await page.waitForTimeout(1000)
    lastCode = totp(secret)
    await page.getByLabel('Authentication code').fill(lastCode)
    await page.getByRole('button', { name: 'Verify' }).click()
  }
  await page.waitForURL(/\/(admin|platform)/)
  // No welcome tour or setup wizard in the pictures.
  await page.evaluate(() => {
    try {
      localStorage.setItem('help-shots', '1')
    } catch {
      /* ignore */
    }
  })
}

const browser = await chromium.launch()
for (const [kind, size] of Object.entries(sizes)) {
  const { width, ...options } = size
  const signedIn = await browser.newContext({ ...options, colorScheme: 'light' })
  const guest = await browser.newContext({ ...options, colorScheme: 'light' })
  const admin = await browser.newContext({ ...options, colorScheme: 'light' })
  await signIn(await signedIn.newPage(), 'demo@example.com', 'password')
  const adminPage = await admin.newPage()
  await signIn(adminPage, 'admin@example.com', 'password', 'JBSWY3DPEHPK3PXP')
  for (const [name, shot] of Object.entries(shots)) {
    if (only && !only.includes(name)) continue
    const page = await (shot.guest ? guest : shot.admin ? admin : signedIn).newPage()
    if (shot.wizard !== undefined) await call(adminPage, 'PUT', '/api/v1/platform/setup', { done: false })
    await page.goto(`${base}${shot.path}`)
    await settle(page)
    if (shot.wizard !== undefined) {
      for (let n = 0; n < shot.wizard; n++) await page.getByRole('button', { name: 'Next' }).click()
      await settle(page)
    }
    // Close one-time notices (announcement, weak demo password, welcome tour).
    for (const label of shot.wizard !== undefined || name === 'tour' ? [] : ['Got it', 'Close', 'Skip tour']) {
      const b = page.getByRole('button', { name: label, exact: true }).first()
      if (await b.isVisible().catch(() => false)) await b.click().catch(() => {})
    }
    if (shot.run) {
      await shot.run(page)
      await settle(page)
    }
    const png = join(tmp, `${name}-${kind}.png`)
    await page.screenshot({ path: png })
    await page.close()
    // Resize and encode with PHP's GD (already needed by the app).
    execFileSync('php', [
      '-r',
      `$s=imagecreatefrompng($argv[1]);$w=(int)$argv[3];$h=(int)round(imagesy($s)*$w/imagesx($s));$d=imagecreatetruecolor($w,$h);imagecopyresampled($d,$s,0,0,0,0,$w,$h,imagesx($s),imagesy($s));imagewebp($d,$argv[2],74);`,
      png,
      join(out, `${name}-${kind}.webp`),
      String(width),
    ])
    console.log(`${name}-${kind}.webp`)
  }
  await call(adminPage, 'PUT', '/api/v1/platform/setup', { done: true })
  await signedIn.close()
  await guest.close()
  await admin.close()
}
await browser.close()
