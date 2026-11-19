// The website's demo menus get a Phone / Desktop bar on tablets and computers
// (owner, 2026-11-19): never on phones (and so never in the phone-sized speed
// checks), the same look in every menu style, 44 px buttons, centred.
import base from '@public-menu/base.css?raw'
import demo from '@public-menu/demo.css?raw'
import order from '@public-menu/order.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const bar = `<nav class="dvb" aria-label="How to show this demo"><b>Demo menu</b><span class="dvs"><a href="/phone/demo"><svg aria-hidden="true" width="18" height="18"></svg>Phone</a><a aria-current="page"><svg aria-hidden="true" width="18" height="18"></svg>Desktop</a></span></nav><div class="top"><header><h1>Menu</h1></header></div>`

let style: HTMLStyleElement
let host: HTMLElement
afterEach(async () => {
  style.remove()
  host.remove()
  document.body.className = ''
  await page.viewport(414, 896)
})

describe('demo menu Phone / Desktop bar', () => {
  for (const name of Object.keys(GUEST_STYLES))
    for (const width of [360, 414, 768, 1024, 1440])
      it(`${name} at ${width} px`, async () => {
        await page.viewport(width, 900)
        style = document.head.appendChild(document.createElement('style'))
        style.textContent = base + order + GUEST_STYLES[name] + demo
        document.body.className = `S-${name}`
        host = document.body.appendChild(document.createElement('div'))
        host.innerHTML = bar
        const nav = host.querySelector<HTMLElement>('.dvb')!
        if (width < 768) {
          expect(getComputedStyle(nav).display).toBe('none')
          return
        }
        const r = nav.getBoundingClientRect()
        // At the very top (style E keeps its thin woven stripe above it).
        expect(r.top).toBeLessThanOrEqual(name === 'mesob' ? 8.5 : 0.5)
        expect(r.width).toBeCloseTo(width, 0)
        expect(r.height).toBeLessThanOrEqual(64)
        const seg = host.querySelector('.dvs')!.getBoundingClientRect()
        const label = host.querySelector('.dvb b')!.getBoundingClientRect()
        // Label and buttons together are centred.
        expect(
          Math.abs((label.left + seg.right) / 2 - width / 2)
        ).toBeLessThanOrEqual(1.5)
        for (const a of host.querySelectorAll('.dvs a')) {
          const b = a.getBoundingClientRect()
          expect(b.height).toBeGreaterThanOrEqual(44)
          expect(b.top).toBeGreaterThanOrEqual(r.top)
          expect(b.bottom).toBeLessThanOrEqual(r.bottom)
        }
        // The chosen view is the light pill; the same colours whatever the style.
        const cur = getComputedStyle(
          host.querySelector('.dvs a[aria-current]')!
        )
        expect(cur.backgroundColor).toBe('rgb(248, 250, 252)')
        expect(cur.color).toBe('rgb(15, 23, 42)')
        expect(getComputedStyle(nav).backgroundColor).toBe('rgb(15, 23, 42)')
        expect(document.documentElement.scrollWidth).toBeLessThanOrEqual(width)
      })
})
