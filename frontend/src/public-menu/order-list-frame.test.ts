// A style's dish-card frame stays on the menu's dish lists: the "My order" list
// and the waiter view (lists outside a category) get no card border or
// background. Bug (2026-11-18): styles E and F framed every list item.
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { GUEST_STYLES } from './guest-styles'

let style: HTMLStyleElement
let root: HTMLElement
afterEach(() => {
  style.remove()
  root.remove()
})

describe('lists outside the menu', () => {
  for (const [name, css] of Object.entries(GUEST_STYLES))
    it(`${name}: My order's list items have no side borders`, () => {
      style = document.head.appendChild(document.createElement('style'))
      style.textContent = base + css
      root = document.body.appendChild(document.createElement('div'))
      root.innerHTML =
        '<dialog id="s" open><div id="sb"><ul class="ol"><li><div class="r1"><span>Tea</span><span>Br 60</span></div></li></ul></div></dialog>'
      const li = root.querySelector('.ol li')!
      const cs = getComputedStyle(li)
      expect(parseFloat(cs.borderLeftWidth), 'left border').toBe(0)
      expect(parseFloat(cs.borderRightWidth), 'right border').toBe(0)
    })
})
