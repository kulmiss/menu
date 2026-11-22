// CI only: Landing 2's browser checks (owner, 2026-11-22), in English and Amharic, on
// phones (360, 390, 414), a tablet (768) and a computer (1280), light and dark:
//   - no console errors or warnings, no failed requests, no CSP violations, no layout shift
//   - nothing wider than the screen (also the Amharic text at 360 px)
//   - axe: no accessibility violations (WCAG 2.1 A and AA)
//   - first load: at most 150 KB in all, at most 30 KB of JavaScript (inline), no script files
//   - nothing heavy on open: no style switcher screen, no style picture, no video bytes
//   - the switcher loads one screen when a chip is tapped; the video only when ▶ is tapped
//   - the language switch keeps the section
// Full-page screenshots of every case go to $SHOTS (default /tmp/landing2-shots).
// Usage: node landing2-checks.mjs [http://localhost:8125]
import { mkdirSync, readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import puppeteer from 'puppeteer-core'

const require = createRequire(import.meta.url)
const base = process.argv[2] ?? 'http://localhost:8125'
const shots = process.env.SHOTS ?? '/tmp/landing2-shots'
const chrome = process.env.CHROME_PATH ?? require('chrome-launcher').Launcher.getInstallations()[0]
const axe = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8')
const KB = 1024
const pages = { en: '/landing2', am: '/am/landing2' }
const widths = [360, 390, 414, 768, 1280]
const failures = []
const rows = []
mkdirSync(shots, { recursive: true })

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox'] })

async function open(lang, width, scheme) {
  const page = await browser.newPage()
  await page.setCacheEnabled(false) // every case is a first visit
  await page.setViewport({ width, height: width < 768 ? 800 : 900, deviceScaleFactor: 1 })
  await page.emulateMediaFeatures([
    { name: 'prefers-color-scheme', value: scheme },
    { name: 'prefers-reduced-motion', value: 'reduce' },
  ])
  const log = { console: [], failed: [], requests: [], bytes: 0 }
  page.on('console', (m) => ['error', 'warn', 'warning'].includes(m.type()) && log.console.push(`${m.type()}: ${m.text()}`))
  page.on('pageerror', (e) => log.console.push(`pageerror: ${e.message}`))
  page.on('requestfailed', (r) => log.failed.push(`${r.url()} ${r.failure()?.errorText}`))
  page.on('response', (r) => {
    if (r.status() >= 400) log.failed.push(`${r.status()} ${r.url()}`)
    if (!r.url().startsWith('data:')) log.requests.push(r.url())
  })
  // Bytes as sent over the network (compressed), like Lighthouse counts them.
  const cdp = await page.createCDPSession()
  await cdp.send('Network.enable')
  cdp.on('Network.loadingFinished', (e) => { log.bytes += e.encodedDataLength })
  await page.evaluateOnNewDocument(() => {
    window.__csp = []
    document.addEventListener('securitypolicyviolation', (e) => window.__csp.push(`${e.violatedDirective} ${e.blockedURI}`))
    window.__cls = 0
    new PerformanceObserver((l) => l.getEntries().forEach((e) => { if (!e.hadRecentInput) window.__cls += e.value })).observe({ type: 'layout-shift', buffered: true })
  })
  await page.goto(base + pages[lang], { waitUntil: 'networkidle0' })
  return { page, log }
}

const fail = (where, what) => failures.push(`${where}: ${what}`)

for (const lang of Object.keys(pages)) {
  for (const width of widths) {
    for (const scheme of ['light', 'dark']) {
      const where = `${lang} ${width}px ${scheme}`
      const { page, log } = await open(lang, width, scheme)
      // First load (before any scroll or tap).
      const first = { bytes: log.bytes, requests: [...log.requests] }
      const inlineJs = await page.evaluate(() => [...document.scripts].filter((s) => !s.src && s.type !== 'application/ld+json').reduce((n, s) => n + s.text.length, 0))
      if (first.bytes > 150 * KB) fail(where, `first load ${(first.bytes / KB).toFixed(1)} KB > 150 KB`)
      if (inlineJs > 30 * KB) fail(where, `JavaScript ${(inlineJs / KB).toFixed(1)} KB > 30 KB`)
      for (const [what, re] of [['script file', /\.m?js(\?|$)/], ['switcher screen', /\/landing\/switch\//], ['video', /\.mp4(\?|$)/], ['style picture', /\/landing\/styles\//]]) {
        const hit = first.requests.filter((u) => re.test(u))
        if (hit.length) fail(where, `${what} loaded on open: ${hit.join(', ')}`)
      }
      // Scroll the whole page (pictures load near the screen), then check layout and accessibility.
      await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { scrollTo(0, y); await new Promise((r) => setTimeout(r, 80)) } scrollTo(0, 0) })
      await new Promise((r) => setTimeout(r, 400))
      const wide = await page.evaluate(() => {
        const w = document.documentElement.clientWidth
        const out = []
        if (document.documentElement.scrollWidth > w) out.push(`page ${document.documentElement.scrollWidth}px`)
        document.querySelectorAll('main *, footer *').forEach((el) => {
          if (el.closest('.row,.chips,svg,.sr,.bi,.skip')) return
          const r = el.getBoundingClientRect()
          if (r.width && r.right > w + 1 && getComputedStyle(el).position !== 'fixed') out.push(`${el.tagName.toLowerCase()}.${el.className} ${Math.round(r.right)}px`)
        })
        return out.slice(0, 5)
      })
      if (wide.length) fail(where, `wider than the screen: ${wide.join('; ')}`)
      await page.evaluate(axe) // through DevTools, so the page's CSP stays as it is
      const violations = await page.evaluate(async () => (await window.axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] })).violations.map((v) => `${v.id} (${v.nodes.length}): ${v.nodes[0]?.target}`))
      violations.forEach((v) => fail(where, `axe ${v}`))
      const { csp, cls } = await page.evaluate(() => ({ csp: window.__csp, cls: window.__cls }))
      csp.forEach((c) => fail(where, `CSP ${c}`))
      if (cls > 0.001) fail(where, `layout shift ${cls.toFixed(4)}`)
      log.console.forEach((c) => fail(where, c))
      log.failed.forEach((f) => fail(where, `request ${f}`))
      await page.screenshot({ path: `${shots}/${lang}-${width}-${scheme}.png`, fullPage: true })
      rows.push(`| ${where} | ${(first.bytes / KB).toFixed(1)} KB | ${(inlineJs / KB).toFixed(1)} KB | ${violations.length} | ${cls.toFixed(3)} | ${wide.length ? '❌' : '✅'} |`)
      await page.close()
    }
  }

  // Taps: the switcher and the video load only then; the language switch keeps the section.
  const where = `${lang} taps`
  const { page, log } = await open(lang, 390, 'light')
  const chips = await page.$$('#switcher input')
  if (chips.length < 2) fail(where, 'no style switcher')
  else {
    const before = log.requests.filter((u) => u.includes('/landing/switch/')).length
    // The first chip with its own screen (the demo café's chip shows the hero picture). After it, at
    // most the next two styles' screens load while the browser is idle.
    const style = await page.$$eval('#switcher input', (rs) => rs.find((r) => r.dataset.src)?.value)
    await page.click(`#switcher input[value="${style}"]`)
    await page.waitForResponse((r) => r.url().includes('/landing/switch/'), { timeout: 5000 }).catch(() => fail(where, 'tapping a chip loaded no screen'))
    await new Promise((r) => setTimeout(r, 600))
    const shown = await page.$eval('.shot .swi.on', (i) => i.currentSrc).catch(() => null)
    const loaded = log.requests.filter((u) => u.includes('/landing/switch/'))
    if (before !== 0 || !shown || !shown.includes(`/landing/switch/${style}-`) || new Set(loaded.map((u) => u.split('/').pop().split('-')[0])).size > 3) fail(where, `switcher: before ${before}, shown ${shown}, loaded ${loaded.join(', ')}`)
  }
  if (await page.$('#vp')) {
    if (log.requests.some((u) => u.endsWith('.mp4'))) fail(where, 'video bytes before the tap')
    // Centred, so the phone's sticky button bar at the bottom does not cover it.
    await page.$eval('#vp', (s) => s.scrollIntoView({ block: 'center' }))
    await new Promise((r) => setTimeout(r, 300))
    await page.click('#vp')
    await page.waitForResponse((r) => r.url().includes('.mp4'), { timeout: 5000 }).catch(() => fail(where, 'tapping ▶ loaded no video'))
  }
  await page.$eval('#pricing', (s) => s.scrollIntoView())
  await new Promise((r) => setTimeout(r, 200))
  await Promise.all([page.waitForNavigation(), page.click('header .lang')])
  if (!page.url().endsWith('#pricing')) fail(where, `language switch went to ${page.url()} (expected #pricing)`)
  await page.close()
}

await browser.close()
console.log('| Case | First load | JavaScript | axe | CLS | Fits |\n|---|---|---|---|---|---|\n' + rows.join('\n'))
if (failures.length) {
  console.error(`\n${failures.length} problem(s):\n- ${failures.join('\n- ')}`)
  process.exit(1)
}
console.log(`\nAll Landing 2 checks passed (${rows.length} cases). Screenshots: ${shots}`)
