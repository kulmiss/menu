// A dish or category name with one very long word (no spaces) wraps inside
// its card on the guest page, in every style, on small phones.
// Bug (2026-11-17): in style B (editorial) a 70-letter name ran under the
// price and out of the card.
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const LONG =
  'Supercalifragilisticexpialidocious-extra-long-dish-name-without-spaces'
const styles = GUEST_STYLES
const card = (photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it ao${photo ? '' : ' np'}"><span class="tx"><span class="nm">${LONG}</span>
  <span class="sm">Short description</span><span class="pr">Br 1,234.50</span><span class="tg"></span></span>
  <span class="ad" data-x="A" aria-hidden="true">+</span>${photo ? '<span class="im"><img alt="" width="640" height="480"></span>' : ''}</button></li>`

const textBoxes = (e: Element) => {
  const r = document.createRange()
  r.selectNodeContents(e)
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
  await page.viewport(414, 896)
})

describe('very long names on the guest page', () => {
  for (const [key, css] of Object.entries(styles))
    it(`${key} at 360 px: the name wraps inside the card, clear of the price and +`, async () => {
      await page.viewport(360, 800)
      style = document.head.appendChild(document.createElement('style'))
      style.textContent = base + css + ':root{--a:#9a3412;--af:#fff}'
      document.body.className = `S-${key}`
      main = document.body.appendChild(document.createElement('main'))
      main.className = 'w'
      const cards = [card(true), card(false)]
      main.innerHTML = `<section><h2>${LONG}</h2><ul>${cards.join('')}</ul></section>`
      const h2 = main.querySelector('h2')!
      for (const b of textBoxes(h2))
        expect(b.right, 'category name').toBeLessThanOrEqual(innerWidth)
      for (const li of main.querySelectorAll('li')) {
        const box = li.getBoundingClientRect()
        const ad = li.querySelector('.ad')!.getBoundingClientRect()
        for (const a of textBoxes(li.querySelector('.nm')!)) {
          expect(a.right, 'name inside the card').toBeLessThanOrEqual(
            box.right + 0.5
          )
          for (const p of textBoxes(li.querySelector('.pr')!))
            expect(overlap(a, p), 'name/price').toBe(false)
          expect(overlap(a, ad), 'name/+').toBe(false)
        }
      }
    })
})
