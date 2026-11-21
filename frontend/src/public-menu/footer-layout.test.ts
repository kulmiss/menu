// The guest page's Info section (footer): Address, Contact, then the social links,
// the PDF link and "Menu by". Bug (owner, 2026-11-19): the social icons sat right
// under the Contact card with no space, at the start of the line. They are a
// centred row now, with clear space above, in every style, both directions.
import base from '@public-menu/base.css?raw'
import order from '@public-menu/order.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const RTL = [
  'arabic',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
  'burger',
  'injera',
  'juice',
]
const icon =
  '<svg aria-hidden="true" viewBox="0 0 24 24" width="24" height="24"></svg>'
// As menu.blade.php writes it.
const footer = `<footer class="ft" id="info"><div class="w">
<div class="fh"><div><p class="fn">Firewood Kitchen</p></div></div>
<p class="ab">Grill house in Kazanchis.</p>
<div class="fg">
<div><h2>${icon}Hours</h2><dl class="hs"><div><dt>Mon–Fri</dt><dd>11:00–22:00</dd></div></dl></div>
<div><h2>${icon}Address</h2><p>Kazanchis, Addis Ababa</p> <a class="fbt" href="#">Open in Maps</a></div>
<div><h2>Contact</h2><ul class="fc"><li><a href="#">${icon}<span><small>Call</small><bdi class="v">+251 11 000 1402</bdi></span></a></li><li><a href="#">${icon}<span><small>Email</small><bdi class="v">demo-firewood@example.com</bdi></span></a></li></ul></div>
</div>
<div class="fsw"><h2>Follow us</h2><ul class="fso"><li><a href="#" aria-label="Instagram">${icon}</a></li><li><a href="#" aria-label="Website">${icon}</a></li></ul></div>
<p class="pdl"><a href="#">${icon}Download the menu (PDF)</a></p>
<p class="pb">Menu by Menu</p>
</div></footer>`

let style: HTMLStyleElement
let host: HTMLElement
afterEach(async () => {
  style.remove()
  host.remove()
  document.body.className = ''
  document.documentElement.dir = ''
  await page.viewport(414, 896)
})

const mid = (r: DOMRect) => (r.left + r.right) / 2

describe('guest menu Info section: social links', () => {
  for (const name of Object.keys(GUEST_STYLES))
    for (const dir of RTL.includes(name) ? ['ltr', 'rtl'] : ['ltr'])
      for (const width of [320, 390, 1280])
        it(`${name} ${dir} at ${width} px`, async () => {
          await page.viewport(width, 900)
          document.documentElement.dir = dir
          style = document.head.appendChild(document.createElement('style'))
          style.textContent =
            base +
            order +
            GUEST_STYLES[name] +
            ':root{--a:#9a3412;--af:#fff;--c1:#e8437f;--t1:#fde4ee;--t3:#fef3c7;--o1:#111}'
          document.body.className = `S-${name}`
          host = document.body.appendChild(document.createElement('div'))
          host.innerHTML = footer
          const w = host.querySelector('.ft .w')!
          const inner = w.getBoundingClientRect()
          const cs = getComputedStyle(w)
          const content = {
            left: inner.left + parseFloat(cs.paddingLeft),
            right: inner.right - parseFloat(cs.paddingRight),
          }
          const centre = (content.left + content.right) / 2
          const grid = host.querySelector('.fg')!.getBoundingClientRect()
          const block = host.querySelector('.fsw')!.getBoundingClientRect()
          const row = host.querySelector('.fso')!
          const links = [...row.querySelectorAll('a')].map((a) =>
            a.getBoundingClientRect()
          )

          // Clear space above the social links (at least 24 px from the last card).
          expect(block.top - grid.bottom, 'space above').toBeGreaterThanOrEqual(
            24
          )
          // Its title and the icons are centred.
          const title = host.querySelector('.fsw h2')!
          const range = document.createRange()
          range.selectNodeContents(title)
          expect(
            Math.abs(mid(range.getBoundingClientRect()) - centre),
            'title centred'
          ).toBeLessThanOrEqual(1.5)
          const iconsMid =
            (Math.min(...links.map((r) => r.left)) +
              Math.max(...links.map((r) => r.right))) /
            2
          expect(
            Math.abs(iconsMid - centre),
            'icons centred'
          ).toBeLessThanOrEqual(1.5)
          // 44 px tap targets, on one line, apart from each other.
          for (const r of links) {
            expect(r.width).toBeGreaterThanOrEqual(44)
            expect(r.height).toBeGreaterThanOrEqual(44)
          }
          expect(new Set(links.map((r) => Math.round(r.top))).size).toBe(1)
          expect(
            Math.abs(links[1].left - links[0].right) - 0,
            'gap'
          ).toBeGreaterThanOrEqual(8)
          // The PDF link is centred too, below with space.
          const pdf = host.querySelector('.pdl a')!.getBoundingClientRect()
          expect(
            Math.abs(mid(pdf) - centre),
            'PDF link centred'
          ).toBeLessThanOrEqual(1.5)
          expect(
            pdf.top - block.bottom,
            'space above PDF link'
          ).toBeGreaterThanOrEqual(16)
          expect(document.documentElement.scrollWidth).toBeLessThanOrEqual(
            width
          )
          // Numbers and times read left to right in both directions: "+" first, "11:00" before "22:00",
          // and the number sits on the label's side (the start of the line).
          const at = (el: Element, i: number) => {
            const r = document.createRange()
            r.setStart(el.firstChild!, i)
            r.setEnd(el.firstChild!, i + 1)
            return r.getBoundingClientRect()
          }
          const phone = host.querySelector('.fc .v')!
          expect(at(phone, 0).left, 'phone: "+" first').toBeLessThan(
            at(phone, 15).left
          )
          const dd = host.querySelector('.hs dd')!
          expect(at(dd, 0).left, 'hours: opening time first').toBeLessThan(
            at(dd, 10).left
          )
          const label = host.querySelector('.fc small')!.getBoundingClientRect()
          const value = phone.getBoundingClientRect()
          if (dir === 'rtl')
            expect(
              Math.abs(value.right - label.right),
              'number on the label side'
            ).toBeLessThanOrEqual(1)
          else
            expect(
              Math.abs(value.left - label.left),
              'number on the label side'
            ).toBeLessThanOrEqual(1)
        })
})
