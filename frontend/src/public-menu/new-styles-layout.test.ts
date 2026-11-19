// Styles H–Q (owner, 2026-11-18 and -19): a price never breaks onto two lines and stays
// inside its card, the "+" is never cut off or outside its card, and the name is
// clear of the price and the "+", with or without photos, from small phones to
// desktop, left-to-right and (H, M–Q) right-to-left.
// Bug (2026-11-18): in style I a dish without a photo had its "+" half above its card.
import base from '@public-menu/base.css?raw'
import order from '@public-menu/order.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_ART, GUEST_STYLES } from './guest-styles'

const NEW = [
  'arabic',
  'islamic',
  'somali',
  'mono',
  'colour',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
]
const RTL = ['arabic', 'coffee', 'grill', 'garden', 'coastal', 'luxe']
const NAMES = [
  'Shaah',
  'Bariis iskukaris with hilib ari and banana on the side',
  'مندي دجاج مع الأرز المدخن',
  'Supercalifragilisticexpialidocious-mandi',
]
const PRICES = [
  'ETB 384',
  'ETB 1,234.50',
  'from ETB 12,345.00',
  '<s>ETB 1,500</s> ETB 1,234',
]
const card = (name: string, price: string, photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it ao${photo ? '' : ' np'}"><span class="tx"><span class="nm">${name}</span>
  <span class="sm">Spiced rice, goat meat and a long description that wraps</span><span class="pr">${price}</span>
  <span class="tg"><span class="bd">Halal</span></span></span>
  <span class="ad" data-x="A" aria-hidden="true">+</span>${photo ? '<span class="im"><img alt="" width="640" height="480"></span>' : ''}</button></li>`

/** The lines a text node takes (one rectangle per line). */
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

describe('styles H–Q: prices, "+" and names', () => {
  for (const name of NEW)
    for (const dir of RTL.includes(name) ? ['ltr', 'rtl'] : ['ltr'])
      for (const photos of [true, false])
        for (const width of [320, 360, 390, 768, 1280])
          it(`${name} ${dir} ${photos ? 'with photos' : 'photo-free'} at ${width} px`, async () => {
            await page.viewport(width, 900)
            document.documentElement.dir = dir
            style = document.head.appendChild(document.createElement('style'))
            style.textContent =
              base +
              order +
              GUEST_STYLES[name] +
              ':root{--a:#9a3412;--af:#fff;--c1:#e8437f;--t1:#fde4ee;--o1:#111}'
            document.body.className = `S-${name}${photos ? '' : ' NP'}`
            main = document.body.appendChild(document.createElement('main'))
            main.className = 'w'
            // A menu with photos also has dishes without one; a photo-free menu has none.
            const cards = NAMES.flatMap((n, i) => [
              card(n, PRICES[i], photos),
              card(n, PRICES[(i + 1) % PRICES.length], false),
            ])
            main.innerHTML = `<section class="k1"><h2>Lunch</h2><ul>${cards.join('')}</ul></section>`
            // Nothing wider than the screen (bug 2026-11-19: style K at 320 px with a long price).
            expect(
              document.documentElement.scrollWidth,
              'no sideways scroll'
            ).toBeLessThanOrEqual(width)
            for (const li of main.querySelectorAll('li')) {
              const box = li.getBoundingClientRect()
              const pr = li.querySelector('.pr')!
              const what = `${li.querySelector('.nm')!.textContent} / ${pr.textContent}`
              // Each price (the old one and the new one) on one line, inside the card.
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
              // The "+" (its visible circle) is wholly inside the card.
              const ad = li.querySelector('.ad')!.getBoundingClientRect()
              const inset = 7 // .4375rem: the circle inside the 44 px tap area
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
              expect(ad.width, `${what}: + tap area`).toBeGreaterThanOrEqual(44)
              for (const b of boxes(pr))
                expect(overlap(b, ad), `${what}: price/+`).toBe(false)
              // The name: inside the card, never under the price or the "+".
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

// Styles M–Q: the header drawing never covers the name, the branch, the hours pill
// or the logo and stays inside the header; a category icon or photo sits beside its
// name, never over it, and a long name wraps instead of running out.
const inside = (a: DOMRect, b: DOMRect) =>
  a.left >= b.left - 0.5 &&
  a.right <= b.right + 0.5 &&
  a.top >= b.top - 0.5 &&
  a.bottom <= b.bottom + 0.5

describe('styles M–Q: header drawing and category icons', () => {
  for (const name of Object.keys(GUEST_ART))
    for (const dir of ['ltr', 'rtl'])
      for (const width of [320, 360, 390, 414, 768, 1280])
        it(`${name} ${dir} at ${width} px`, async () => {
          await page.viewport(width, 900)
          document.documentElement.dir = dir
          style = document.head.appendChild(document.createElement('style'))
          style.textContent =
            base + order + GUEST_STYLES[name] + ':root{--a:#9a3412;--af:#fff}'
          document.body.className = `S-${name} dc`
          main = document.body.appendChild(document.createElement('div'))
          for (const title of [
            'Dar',
            'Maison Sheba · ሜዞን ሳባ',
            'مطعم بيت المندي والمشويات',
          ])
            for (const logo of [true, false]) {
              main.innerHTML = `<div class="top"><header>${GUEST_ART[name]}${logo ? '<img class="lg" alt="" width="76" height="76">' : ''}<div><h1>${title}</h1><p class="sub">Bole branch · Addis Ababa</p><p class="st op">Open now · until 23:30</p></div></header></div>
<main class="w"><section><h2><svg class="ci" aria-hidden="true" width="24" height="24"></svg>Hot drinks and a very long category name that wraps</h2></section>
<section><h2><span class="ci"><picture><img alt="" width="640" height="640"></picture></span>Grill</h2></section></main>`
              const what = `${title} ${logo ? 'logo' : 'no logo'}`
              const head = main.querySelector('header')!.getBoundingClientRect()
              const ha = main.querySelector('.ha')!.getBoundingClientRect()
              expect(ha.width, `${what}: drawing shown`).toBeGreaterThan(40)
              expect(inside(ha, head), `${what}: drawing inside`).toBe(true)
              // Q · Luxe: a faint marble texture behind the whole header, not a drawing beside
              // it; the text over it keeps 4.5:1 (text-contrast.test.ts).
              const texture = ha.width >= head.width - 1
              for (const sel of ['h1', '.sub', '.st', '.lg']) {
                const el = main.querySelector(sel)
                if (!el) continue
                const n = sel === '.lg' || sel === '.st' ? [el] : boxes(el)
                for (const b of n.map((x) =>
                  x instanceof Element ? x.getBoundingClientRect() : x
                )) {
                  if (!texture)
                    expect(overlap(b, ha), `${what}: ${sel} clear`).toBe(false)
                  expect(inside(b, head), `${what}: ${sel} inside`).toBe(true)
                }
              }
              expect(document.documentElement.scrollWidth).toBeLessThanOrEqual(
                width
              )
              for (const h2 of main.querySelectorAll('h2')) {
                const ci = h2.querySelector('.ci')!.getBoundingClientRect()
                expect(ci.width, `${name}: icon size`).toBeGreaterThanOrEqual(
                  24
                )
                for (const b of boxes(h2.lastChild!))
                  expect(overlap(b, ci), `${h2.textContent}: icon/name`).toBe(
                    false
                  )
                const h = h2.getBoundingClientRect()
                expect(inside(ci, h), `${name}: icon inside`).toBe(true)
                expect(h.right).toBeLessThanOrEqual(width + 0.5)
              }
            }
        })
})
