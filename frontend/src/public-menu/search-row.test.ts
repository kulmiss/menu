// The search row keeps its buttons in place while the page is still arriving:
// the share button must not move when the info button below it in the HTML is
// parsed a moment later (CI, 2026-11-18: a 0.0007 layout shift in style E when
// the first network packet ended between the two buttons).
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const share =
  '<button type="button" class="ib" data-x="H" aria-label="Share menu"></button>'
const info = '<a class="ib" href="#info" aria-label="Info"></a>'

let style: HTMLStyleElement
let root: HTMLElement
afterEach(() => {
  style.remove()
  root.remove()
})

describe('search row', () => {
  for (const [name, css] of Object.entries(GUEST_STYLES))
    it(`${name}: the share button does not move when the info button arrives`, async () => {
      await page.viewport(412, 800)
      style = document.head.appendChild(document.createElement('style'))
      style.textContent = base + css
      root = document.body.appendChild(document.createElement('div'))
      root.className = 'bar'
      root.innerHTML = `<div class="w"><div class="sr n2"><input id="q" type="search">${share}</div></div>`
      const row = root.querySelector('.sr')!
      const before = row.querySelector('button')!.getBoundingClientRect()
      const input = row.querySelector('input')!.getBoundingClientRect()
      row.insertAdjacentHTML('beforeend', info)
      const after = row.querySelector('button')!.getBoundingClientRect()
      expect(after.left).toBe(before.left)
      expect(row.querySelector('input')!.getBoundingClientRect().width).toBe(
        input.width
      )
      // Both buttons 44 px, inside the row.
      for (const b of row.querySelectorAll('.ib')) {
        const r = b.getBoundingClientRect()
        expect(r.width).toBeGreaterThanOrEqual(44)
        expect(r.right).toBeLessThanOrEqual(
          row.getBoundingClientRect().right + 0.5
        )
      }
    })
})
