// Layouts (owner, 2026-11-21, QUESTIONS #242): every style also as photo cards
// in two columns (L-grid) or a list with small photos (L-list), whichever is not
// its own. In each: nothing wider than the screen, a price on one line inside
// its card, the "+" inside the card and never over the name or the price, with
// and without photos, from small phones to desktop, left-to-right and (styles
// with "Menu direction") right-to-left.
import base from '@public-menu/base.css?raw'
import order from '@public-menu/order.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

// Each style's own layout (backend PageTheme::STYLES 'layout'); D's own is text.
const GRID_OWN = ['editorial', 'grid', 'islamic', 'burger', 'juice']
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
const others = (name: string) =>
  name === 'minimal'
    ? ['L-grid', 'L-list']
    : [GRID_OWN.includes(name) ? 'L-list' : 'L-grid']

const NAMES = [
  'Shaah',
  'Bariis iskukaris with hilib ari and banana on the side',
  'مندي دجاج مع الأرز المدخن',
]
const PRICES = ['ETB 384', 'from ETB 12,345.00', '<s>ETB 1,500</s> ETB 1,234']
const card = (name: string, price: string, photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it ao${photo ? '' : ' np'}"><span class="tx"><span class="nm">${name}</span>
  <span class="sm">Spiced rice, goat meat and a long description that wraps</span><span class="pr">${price}</span>
  <span class="tg"><span class="bd">Halal</span></span></span>
  <span class="ad" data-x="A" aria-hidden="true">+</span>${photo ? '<span class="im"><img alt="" width="640" height="480"></span>' : ''}</button></li>`

const lines = (n: Node) => {
  const r = document.createRange()
  r.selectNodeContents(n)
  return new Set(
    [...r.getClientRects()]
      .filter((b) => b.width > 0)
      .map((b) => Math.round(b.top))
  ).size
}
const boxes = (n: Node) => {
  const r = document.createRange()
  r.selectNodeContents(n)
  return [...r.getClientRects()].filter((b) => b.width > 0)
}
const overlap = (a: DOMRect, b: DOMRect) =>
  a.left < b.right - 0.5 &&
  b.left < a.right - 0.5 &&
  a.top < b.bottom - 0.5 &&
  b.top < a.bottom - 0.5

let style: HTMLStyleElement
let main: HTMLElement
afterEach(async () => {
  style.remove()
  main.remove()
  document.body.className = ''
  document.documentElement.dir = ''
  await page.viewport(414, 896)
})

describe('layouts: the other layout of every style', () => {
  for (const name of Object.keys(GUEST_STYLES))
    for (const layout of others(name))
      for (const dir of RTL.includes(name) ? ['ltr', 'rtl'] : ['ltr'])
        for (const width of [320, 360, 390, 768])
          it(`${name} ${layout} ${dir} at ${width} px`, async () => {
            await page.viewport(width, 900)
            document.documentElement.dir = dir
            style = document.head.appendChild(document.createElement('style'))
            style.textContent =
              base +
              order +
              GUEST_STYLES[name] +
              ':root{--a:#9a3412;--af:#fff;--c1:#e8437f;--t1:#fde4ee;--o1:#111}'
            document.body.className = `S-${name} ${layout}`
            main = document.body.appendChild(document.createElement('main'))
            main.className = 'w'
            // Dishes with and without a photo side by side, as on a real menu.
            const cards = NAMES.flatMap((n, i) => [
              card(n, PRICES[i], true),
              card(n, PRICES[(i + 1) % PRICES.length], false),
            ])
            main.innerHTML = `<section class="k1"><h2>Lunch</h2><ul>${cards.join('')}</ul></section>`
            expect(
              document.documentElement.scrollWidth,
              'no sideways scroll'
            ).toBeLessThanOrEqual(width)
            if (layout === 'L-grid' && width < 768) {
              const tops = new Set(
                [...main.querySelectorAll('li')].map((li) =>
                  Math.round(li.getBoundingClientRect().top)
                )
              )
              expect(tops.size, 'two cards per row').toBeLessThan(
                main.querySelectorAll('li').length
              )
            }
            for (const li of main.querySelectorAll('li')) {
              const box = li.getBoundingClientRect()
              const pr = li.querySelector('.pr')!
              const what = `${li.querySelector('.nm')!.textContent} / ${pr.textContent}`
              for (const n of [...pr.childNodes].filter((c) =>
                c.textContent!.trim()
              ))
                expect(lines(n), `${what}: price on one line`).toBe(1)
              const p = pr.getBoundingClientRect()
              expect(p.left, `${what}: price inside`).toBeGreaterThanOrEqual(
                box.left - 0.5
              )
              expect(p.right, `${what}: price inside`).toBeLessThanOrEqual(
                box.right + 0.5
              )
              const ad = li.querySelector('.ad')!.getBoundingClientRect()
              const inset = 7 // the visible circle inside the 44 px tap area
              expect(ad.top + inset, `${what}: + top`).toBeGreaterThanOrEqual(
                box.top - 0.5
              )
              expect(
                ad.bottom - inset,
                `${what}: + bottom`
              ).toBeLessThanOrEqual(box.bottom + 0.5)
              expect(
                ad.left + inset,
                `${what}: + start`
              ).toBeGreaterThanOrEqual(box.left - 0.5)
              expect(ad.right - inset, `${what}: + end`).toBeLessThanOrEqual(
                box.right + 0.5
              )
              for (const b of boxes(pr))
                expect(overlap(b, ad), `${what}: price/+`).toBe(false)
              for (const a of boxes(li.querySelector('.nm')!)) {
                expect(a.right, `${what}: name inside`).toBeLessThanOrEqual(
                  box.right + 0.5
                )
                expect(a.left, `${what}: name inside`).toBeGreaterThanOrEqual(
                  box.left - 0.5
                )
                for (const b of boxes(pr))
                  expect(overlap(a, b), `${what}: name/price`).toBe(false)
                expect(overlap(a, ad), `${what}: name/+`).toBe(false)
              }
            }
          })
})
