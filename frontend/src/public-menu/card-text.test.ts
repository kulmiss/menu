// Dish cards on the guest page (backend/resources/public-menu/*.css): the name,
// price, description and tags never sit on top of each other, in every style,
// on small phones too. Bug (2026-11-17): in style A (grid) a dish without a
// photo had its name and price side by side; on phones a name like "Chechebsa"
// or "Berbere chicken" ran under the price ("ChechebBr 160").
import base from '@public-menu/base.css?raw'
import order from '@public-menu/order.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const styles = GUEST_STYLES
const card = (name: string, price: string, photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it ao${photo ? '' : ' np'}"><span class="tx"><span class="nm">${name}</span>
  <span class="sm">Flatbread with niter kibbeh, berbere and honey</span><span class="pr">${price}</span>
  <span class="tg"><span class="bd">Vegetarian</span><span class="bd">Spicy</span></span></span>
  <span class="ad" data-x="A" aria-hidden="true">+</span>${photo ? '<span class="im"><img alt="" width="640" height="480" style="background:#888"></span>' : ''}</button></li>`

// Where the text really is (not the element box, which a long word overflows).
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
afterEach(() => {
  style.remove()
  main.remove()
  document.body.className = ''
})

describe('dish card text', () => {
  for (const [key, css] of Object.entries(styles))
    for (const width of [320, 360, 390])
      it(`${key} at ${width} px: name, price and add button never overlap (no photo)`, async () => {
        await page.viewport(width, 800)
        style = document.head.appendChild(document.createElement('style'))
        style.textContent = base + order + css + ':root{--a:#9a3412;--af:#fff}'
        document.body.className = `S-${key}`
        main = document.body.appendChild(document.createElement('main'))
        main.className = 'w'
        main.innerHTML = `<section><h2>Breakfast</h2><ul>${[
          card('Chechebsa', 'Br 160', true),
          card('Chechebsa', 'Br 160', false),
          card('Berbere chicken', 'from Br 550', false),
          card('Gomen', 'Br 170', false),
          card('Crispy chicken burger', 'from Br 1,250.50', false),
        ].join('')}</ul></section>`
        for (const li of main.querySelectorAll('li:has(.np)')) {
          const nm = textBoxes(li.querySelector('.nm')!)
          const pr = textBoxes(li.querySelector('.pr')!)
          const ad = li.querySelector('.ad')!.getBoundingClientRect()
          const what = `${li.querySelector('.nm')!.textContent}`
          for (const a of nm) {
            for (const b of pr)
              expect(overlap(a, b), `${what}: name/price`).toBe(false)
            expect(overlap(a, ad), `${what}: name/+`).toBe(false)
            expect(
              a.right,
              `${what}: name inside the card`
            ).toBeLessThanOrEqual(li.getBoundingClientRect().right)
          }
          for (const b of pr)
            expect(overlap(b, ad), `${what}: price/+`).toBe(false)
        }
      })
})
