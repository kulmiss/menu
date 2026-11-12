// The opening-hours pill and closure notice on the guest page
// (backend/resources/public-menu/base.css + each style): readable in light
// and dark (contrast ≥ 4.5:1), with room above and below (owner, 2026-11-12).
import base from '@public-menu/base.css?raw'
import bistro from '@public-menu/style-bistro.css?raw'
import editorial from '@public-menu/style-editorial.css?raw'
import grid from '@public-menu/style-grid.css?raw'
import minimal from '@public-menu/style-minimal.css?raw'
import { afterEach, describe, expect, it } from 'vitest'

type Rgba = [number, number, number, number]
const rgba = (c: string): Rgba => {
  const m = c.match(/[\d.]+/g)!.map(Number)
  return [m[0], m[1], m[2], m[3] ?? 1]
}
// Paint a (possibly see-through) colour over an opaque one.
const over = (top: Rgba, under: Rgba): Rgba => [
  ...([0, 1, 2].map((i) => top[i] * top[3] + under[i] * (1 - top[3])) as [
    number,
    number,
    number,
  ]),
  1,
]
const lum = (c: Rgba) => {
  const [r, g, b] = c
    .slice(0, 3)
    .map((v) => v / 255)
    .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4))
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}
const contrast = (a: Rgba, b: Rgba) => {
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p)
  return (x + 0.05) / (y + 0.05)
}

let style: HTMLStyleElement | null = null
let root: HTMLElement | null = null
function page(css: string, dark: boolean) {
  style = document.createElement('style')
  // Dark mode: apply the prefers-color-scheme:dark rules unconditionally.
  style.textContent = dark
    ? css.split('@media (prefers-color-scheme:dark)').join('@media all')
    : css
  document.head.append(style)
  root = document.createElement('div')
  root.innerHTML = `<header><div><h1>Buna &amp; Bread</h1><p class="sub">Bole</p>
    <p class="st op" id="open">Open now · until 22:00</p>
    <p class="st" id="shut">Closed · opens Thu 08:00</p></div></header>
    <div class="w"><p class="nt" id="nt">Closed Thu 7 Jan · Genna</p></div>
    <div class="bar" id="bar"></div>`
  document.body.append(root)
}
afterEach(() => {
  style?.remove()
  root?.remove()
})

const STYLES = { editorial, grid, bistro, minimal }

describe('opening-hours pill', () => {
  for (const [name, css] of Object.entries(STYLES))
    for (const dark of [false, true])
      it(`is a readable, spaced pill with a dot in ${name} ${dark ? 'dark' : 'light'}`, () => {
        page(css + base, dark)
        const bg = rgba(getComputedStyle(document.body).backgroundColor)
        for (const sel of ['#open', '#shut']) {
          const el = document.querySelector<HTMLElement>(sel)!
          const cs = getComputedStyle(el)
          expect(cs.display).toBe('inline-flex')
          expect(parseFloat(cs.marginTop)).toBeGreaterThanOrEqual(6)
          expect(parseFloat(cs.marginBottom)).toBeGreaterThan(0)
          expect(parseFloat(cs.borderTopLeftRadius)).toBeGreaterThan(20)
          const dot = getComputedStyle(el, '::before')
          expect(dot.width).toBe(dot.height)
          expect(parseFloat(dot.width)).toBeGreaterThanOrEqual(6)
          const fill = over(rgba(cs.backgroundColor), bg)
          expect(contrast(rgba(cs.color), fill)).toBeGreaterThanOrEqual(4.5)
        }
        // Open is green, closed is red: the dot tells them apart at a glance.
        const dotOf = (s: string) =>
          rgba(
            getComputedStyle(document.querySelector(s)!, '::before')
              .backgroundColor
          )
        const [g, r] = [dotOf('#open'), dotOf('#shut')]
        expect(g[1]).toBeGreaterThan(g[0])
        expect(r[0]).toBeGreaterThan(r[1])

        // The closure notice is readable and keeps a gap to the search bar.
        const nt = document.getElementById('nt')!
        const ns = getComputedStyle(nt)
        expect(parseFloat(ns.marginBottom)).toBeGreaterThanOrEqual(12)
        expect(
          contrast(rgba(ns.color), over(rgba(ns.backgroundColor), bg))
        ).toBeGreaterThanOrEqual(4.5)
      })

  it('stays hidden when the restaurant has no opening hours', () => {
    page(grid + base, false)
    const el = document.getElementById('open')!
    el.hidden = true
    expect(getComputedStyle(el).display).toBe('none')
  })
})
