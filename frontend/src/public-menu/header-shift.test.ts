// Nothing in the header moves when the script fills in "Open now · until 23:30"
// (CLS 0, owner 2026-11-11), in every style, with or without a logo, the Halal
// badge and a long or Arabic name.
// Bugs (2026-11-18): in H the centred name block widened with the pill, so it slid
// sideways; in I the Halal badge shared the pill's line and slid with it.
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

let style: HTMLStyleElement
let top: HTMLElement
afterEach(async () => {
  style.remove()
  top.remove()
  document.body.className = ''
  document.documentElement.dir = ''
  await page.viewport(414, 896)
})

const header = (name: string, logo: boolean) =>
  `<header>${logo ? '<img class="lg" alt="" width="76" height="76">' : ''}<div><h1>${name}</h1>
  <p class="sub">Bole branch</p>
  <p class="hb"><svg aria-hidden="true" width="16" height="16"></svg>Halal</p>
  <p class="st ld" id="st">&nbsp;</p></div></header>`

const rects = () =>
  ['h1', '.sub', '.hb', 'header img'].flatMap((s) => {
    const e = document.querySelector(s)
    if (!e) return []
    const r = e.getBoundingClientRect()
    return [`${s} ${Math.round(r.left)},${Math.round(r.top)}`]
  })

describe('guest header: filling in the opening hours moves nothing', () => {
  for (const name of Object.keys(GUEST_STYLES))
    for (const dir of name === 'arabic' ? ['ltr', 'rtl'] : ['ltr'])
      for (const logo of [true, false])
        for (const width of [360, 1280])
          it(`${name} ${dir} ${logo ? 'logo' : 'no logo'} at ${width} px`, async () => {
            await page.viewport(width, 900)
            document.documentElement.dir = dir
            style = document.head.appendChild(document.createElement('style'))
            style.textContent =
              base + GUEST_STYLES[name] + ':root{--a:#9a3412;--af:#fff}'
            document.body.className = `S-${name}`
            top = document.body.appendChild(document.createElement('div'))
            top.className = 'top'
            for (const n of [
              'Dar',
              'دار المندي',
              'Bariis iskukaris and friends',
            ]) {
              top.innerHTML = header(n, logo)
              const before = rects()
              const st = document.getElementById('st')!
              st.className = 'st op'
              st.textContent = 'Open now · until 23:30'
              expect(rects(), `${n}`).toEqual(before)
            }
          })
})
