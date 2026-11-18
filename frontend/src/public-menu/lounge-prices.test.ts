// Style F · Night Lounge: a price never breaks onto two lines (owner, 2026-11-18:
// "ETB 384" broke in the mockup), stays inside its card and clear of the name
// and the "+", with or without a photo, from small phones to desktop.
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const NAMES = [
  'Tibs',
  'Sunset Spritz with a very long name for a drink',
  'ዶሮ ወጥ በእንጀራ እና በቅቤ',
  'Supercalifragilisticexpialidocious-cocktail',
]
const PRICES = [
  'ETB 384',
  'ETB 1,234.50',
  'from ETB 12,345.00',
  '<s>ETB 1,500</s> ETB 1,234',
]
const card = (name: string, price: string, photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it ao${photo ? '' : ' np'}"><span class="tx"><span class="nm">${name}</span>
  <span class="sm">Orange, soda, mint and a long description that wraps</span><span class="pr">${price}</span>
  <span class="tg"><span class="bd">Vegan</span></span></span>
  <span class="ad" data-x="A" aria-hidden="true">+</span>${photo ? '<span class="im"><img alt="" width="640" height="480"></span>' : ''}</button></li>`

/** The lines a piece of text takes (one rectangle per line). */
const lines = (n: Node) => {
  const r = document.createRange()
  r.selectNodeContents(n)
  return new Set(
    [...r.getClientRects()]
      .filter((b) => b.width > 0)
      .map((b) => Math.round(b.top))
  ).size
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
  await page.viewport(414, 896)
})

describe('Night Lounge prices', () => {
  for (const width of [320, 360, 390, 414, 768, 1280])
    it(`stay on one line at ${width} px, inside the card, clear of the name and +`, async () => {
      await page.viewport(width, 900)
      style = document.head.appendChild(document.createElement('style'))
      style.textContent =
        base + GUEST_STYLES.lounge + ':root{--a:#c58bff;--af:#000}'
      document.body.className = 'S-lounge'
      main = document.body.appendChild(document.createElement('main'))
      main.className = 'w'
      const cards = NAMES.flatMap((n) =>
        PRICES.flatMap((p) => [card(n, p, true), card(n, p, false)])
      )
      main.innerHTML = `<section><h2>Cocktails</h2><ul>${cards.join('')}</ul></section>`
      for (const li of main.querySelectorAll('li')) {
        const pr = li.querySelector('.pr')!
        const what = `${pr.textContent} in "${li.querySelector('.nm')!.textContent}"`
        // Every part of the price (the old price too) is on one line.
        for (const part of pr.childNodes)
          if ((part.textContent ?? '').trim() !== '')
            expect(lines(part), what).toBe(1)
        // No glow behind a price: it lowered the contrast to 4.06:1 (Lighthouse, 2026-11-18).
        expect(getComputedStyle(pr).textShadow, what).toBe('none')
        const box = li.getBoundingClientRect()
        const p = pr.getBoundingClientRect()
        expect(p.right, `${what}: inside the card`).toBeLessThanOrEqual(
          box.right + 0.5
        )
        expect(p.left, `${what}: inside the card`).toBeGreaterThanOrEqual(
          box.left - 0.5
        )
        expect(
          overlap(p, li.querySelector('.ad')!.getBoundingClientRect()),
          `${what}: price/+`
        ).toBe(false)
        const r = document.createRange()
        r.selectNodeContents(li.querySelector('.nm')!)
        for (const n of r.getClientRects())
          if (n.width > 0)
            expect(overlap(n, p), `${what}: name/price`).toBe(false)
      }
    })
})
