// Every text on the guest page is readable (4.5:1, WCAG AA) in every style,
// light and dark, for any brand colour: dish names, prices, tags, headings,
// specials and news, also on a tinted or striped background (owner, 2026-11-18:
// Lighthouse found Night Lounge prices at 4.06:1 under their glow).
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { GUEST_STYLES } from './guest-styles'

type Rgb = [number, number, number]
const hex = (h: string): Rgb => {
  const x =
    h.length === 4 ? '#' + [...h.slice(1)].map((c) => c + c).join('') : h
  return [1, 3, 5].map((i) => parseInt(x.slice(i, i + 2), 16)) as Rgb
}
const toHex = ([r, g, b]: Rgb) =>
  '#' +
  [r, g, b]
    .map((v) => Math.max(0, Math.min(255, v)).toString(16).padStart(2, '0'))
    .join('')
const lum = (c: Rgb) => {
  const [r, g, b] = c
    .map((v) => v / 255)
    .map((s) => (s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4))
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}
const ratio = (a: Rgb, b: Rgb) => {
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p)
  return (x + 0.05) / (y + 0.05)
}
// The same as PHP Contrast::readableOn / textOn (PageTheme's --a and --af).
const readableOn = (brand: string, surface: string) => {
  const towards = lum(hex(surface)) > 0.5 ? 0 : 255
  const c = hex(brand)
  for (let step = 0; step <= 20; step++) {
    const t = step / 20
    const cand = c.map((v) => Math.round(v + (towards - v) * t)) as Rgb
    if (ratio(cand, hex(surface)) >= 4.5) return toHex(cand)
  }
  return towards === 0 ? '#000000' : '#ffffff'
}
const textOn = (a: string) =>
  ratio(hex(a), [255, 255, 255]) >= ratio(hex(a), hex('#141210'))
    ? '#ffffff'
    : '#141210'

/** Every colour in a computed CSS value (rgb(), rgba(), color(srgb …)), as [r, g, b, alpha]. */
const colours = (v: string) => {
  const out: number[][] = []
  for (const m of v.matchAll(/rgba?\(([^)]+)\)/g)) {
    const p = m[1]
      .split(/[\s,/]+/)
      .filter(Boolean)
      .map(Number)
    out.push([p[0], p[1], p[2], p[3] ?? 1])
  }
  for (const m of v.matchAll(/color\(srgb ([^)]+)\)/g)) {
    const p = m[1]
      .split(/[\s/]+/)
      .filter(Boolean)
      .map(Number)
    out.push([p[0] * 255, p[1] * 255, p[2] * 255, p[3] ?? 1])
  }
  return out
}
const over = (c: number[], under: Rgb): Rgb =>
  [0, 1, 2].map((i) => c[i] * c[3] + under[i] * (1 - c[3])) as Rgb

/** The backgrounds a text can sit on: the nearest solid one, and every stop of a gradient on the way. */
function backgrounds(el: Element): Rgb[] {
  const chain: Element[] = []
  for (let p: Element | null = el; p; p = p.parentElement) chain.push(p)
  let solid: Rgb = hex(
    getComputedStyle(document.documentElement)
      .getPropertyValue('--bg')
      .trim() || '#ffffff'
  )
  const layers: number[][][] = []
  for (const p of chain) {
    const cs = getComputedStyle(p)
    const bg = colours(cs.backgroundColor)[0]
    // A thin decorative band (Mesob's woven stripe, a few px high) is never under the text.
    const band = /^\S+\s+([\d.]+)px/.exec(cs.backgroundSize)
    if (cs.backgroundImage !== 'none' && !(band && Number(band[1]) < 12))
      layers.push(colours(cs.backgroundImage))
    if (bg && bg[3] > 0) {
      solid = over(bg, solid)
      break
    }
  }
  const out = [solid]
  for (const stops of layers) for (const s of stops) out.push(over(s, solid))
  return out
}

const BRANDS = [
  '#b4432a',
  '#fde047',
  '#0b1f3a',
  '#5cc9a7',
  '#c58bff',
  '#3d3a6b',
]
const html = `<header><div><h1>Habesha Table</h1><p class="sub">Bole</p></div></header>
<main class="w">
<p class="an"><b>Tonight:</b> live music from 20:00</p>
<div class="spc"><button type="button" class="it sp"><span class="tx"><span class="bd hl">Special</span><span class="nm">Sunset set</span><span class="sm">Two drinks and a plate</span><span class="pr">Br 450</span></span></button></div>
<section><h2>Wot · ወጥ</h2><p class="cd">Served on injera.</p><ul>
<li data-i="1"><button type="button" class="it ao"><span class="tx"><span class="nm">Doro wot · ዶሮ ወጥ</span><span class="sm">Chicken, berbere, egg</span><span class="pr"><s>Br 450</s> Br 384</span><span class="tg"><span class="bd hl">Popular</span><span class="bd">Spicy</span></span></span><span class="ad" data-x="A">+</span><span class="im"><img alt="" width="640" height="480"></span></button></li>
<li data-i="2"><button type="button" class="it np ao"><span class="tx"><span class="nm">Shiro</span><span class="sm">Chickpea stew</span><span class="pr">from Br 220</span><span class="tg"><span class="bd">Vegan</span></span></span><span class="ad" data-x="A">+</span></button></li>
<li data-i="3" class="so"><button type="button" class="it np"><span class="tx"><span class="nm">Kitfo</span><span class="pr">Br 460</span><span class="tg"><span class="bd x0">Sold out</span></span></span></button></li>
</ul></section></main>`
const TEXTS = [
  'h1',
  '.sub',
  '.an',
  'h2',
  '.cd',
  '.sp .nm',
  '.sp .sm',
  '.sp .pr',
  '.sp .bd',
  'li .nm',
  'li .sm',
  'li .pr',
  'li .pr s',
  '.bd',
  '.bd.hl',
  '.bd.x0',
]

let style: HTMLStyleElement
let root: HTMLElement
afterEach(() => {
  style.remove()
  root.remove()
  document.body.className = ''
})

describe('text contrast on the guest page', () => {
  for (const [name, css] of Object.entries(GUEST_STYLES)) {
    const palettes = css
      .match(/--(bg|card):(#[0-9a-f]{6}|#[0-9a-f]{3})\b/g)!
      .map((m) => m.split(':')[1])
    const darkOnly = !css.includes(
      '@media (prefers-color-scheme:dark){:root{--bg'
    )
    for (const dark of darkOnly ? [false] : [false, true])
      it(`${name} ${darkOnly ? '(one dark palette)' : dark ? 'dark' : 'light'}: every text is 4.5:1 or more`, () => {
        const [bg, card] = dark ? palettes.slice(2, 4) : palettes.slice(0, 2)
        // PageTheme: the least contrasting surface (darker in a light palette, lighter in a dark one).
        const lightPalette = lum(hex(bg)) > 0.5
        const surface = [bg, card].sort((x, y) =>
          lightPalette ? lum(hex(x)) - lum(hex(y)) : lum(hex(y)) - lum(hex(x))
        )[0]
        for (const brand of BRANDS) {
          const a = readableOn(brand, surface)
          style = document.head.appendChild(document.createElement('style'))
          style.textContent =
            (dark
              ? (base + css)
                  .split('@media (prefers-color-scheme:dark)')
                  .join('@media all')
              : base + css) + `:root{--a:${a};--af:${textOn(a)}}`
          document.body.className = `S-${name}`
          root = document.body.appendChild(document.createElement('div'))
          root.innerHTML = html
          for (const sel of TEXTS)
            for (const el of root.querySelectorAll(sel)) {
              const c = colours(getComputedStyle(el).color)[0]
              const opacity = Number(
                getComputedStyle(el.closest('.tg,.pr,.nm,.sm') ?? el).opacity
              )
              for (const b of backgrounds(el)) {
                const fg = over([c[0], c[1], c[2], c[3] * opacity], b)
                expect(
                  ratio(fg, b),
                  `${name} brand ${brand}: ${sel} "${el.textContent}"`
                ).toBeGreaterThanOrEqual(4.5)
              }
            }
          style.remove()
          root.remove()
        }
        style = document.head.appendChild(document.createElement('style'))
        root = document.body.appendChild(document.createElement('div'))
      })
  }
})
