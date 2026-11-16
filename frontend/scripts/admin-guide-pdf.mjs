// Prints docs/admin-guide/admin-guide.html (written by `php artisan
// docs:admin-guide`) to docs/admin-guide.pdf: A4, page numbers, screenshots.
// Usage: node scripts/admin-guide-pdf.mjs
import { chromium } from 'playwright'
import { pathToFileURL } from 'node:url'

const html = new URL('../../docs/admin-guide/admin-guide.html', import.meta.url)
const pdf = new URL('../../docs/admin-guide.pdf', import.meta.url).pathname

const browser = await chromium.launch()
const page = await browser.newPage()
await page.goto(pathToFileURL(html.pathname).href, { waitUntil: 'networkidle' })
await page.pdf({
  path: pdf,
  format: 'A4',
  margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' },
  displayHeaderFooter: true,
  headerTemplate: '<span></span>',
  footerTemplate:
    '<div style="font:9px system-ui;width:100%;text-align:center;color:#64748b">Super Admin guide · <span class="pageNumber"></span> / <span class="totalPages"></span></div>',
  printBackground: true,
})
await browser.close()
console.log('docs/admin-guide.pdf')
