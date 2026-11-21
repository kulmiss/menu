// "My order" switched off (QUESTIONS #236): the page has no "+" (no .ad, no .ao) and none
// of the My order CSS. In every style A–Q, with and without photos, from small phones to
// desktop, left-to-right and (H, M–Q) right-to-left: nothing sticks out, no space is
// kept free for a "+" that isn't there, and the cards of one row are equally tall.
import base from '@public-menu/base.css?raw'
import { afterEach, describe, expect, it } from 'vitest'
import { page } from 'vitest/browser'
import { GUEST_STYLES } from './guest-styles'

const RTL = [
  'arabic',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
  'burger',
  'injera',
  'juice',
]
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
// The card exactly as menu.blade.php writes it with My order off.
const card = (name: string, price: string, photo: boolean) =>
  `<li data-i="1" data-t=""><button type="button" class="it${photo ? '' : ' np'}"><span class="tx"><span class="nm">${name}</span>
  <span class="sm">Spiced rice, goat meat and a long description that wraps</span><span class="pr">${price}</span>
  <span class="tg"><span class="bd">Halal</span></span></span>
  ${photo ? '<span class="im"><img alt="" width="640" height="480"></span>' : ''}</button></li>`

const px = (v: string) => parseFloat(v) || 0
let style: HTMLStyleElement
let main: HTMLElement
afterEach(async () => {
  style.remove()
  main.remove()
  document.body.className = ''
  document.documentElement.dir = ''
  await page.viewport(414, 896)
})

describe('My order off: every style', () => {
  for (const name of Object.keys(GUEST_STYLES))
    for (const dir of RTL.includes(name) ? ['ltr', 'rtl'] : ['ltr'])
      for (const photos of [true, false])
        for (const width of [320, 360, 390, 414, 768, 1280])
          it(`${name} ${dir} ${photos ? 'with photos' : 'photo-free'} at ${width} px`, async () => {
            await page.viewport(width, 900)
            document.documentElement.dir = dir
            style = document.head.appendChild(document.createElement('style'))
            // base.css and the style's CSS; the server also leaves the style's own
            // .ad / .ao rules out (InlineAssets::withoutSelectors), which match nothing here.
            style.textContent =
              base +
              GUEST_STYLES[name] +
              ':root{--a:#9a3412;--af:#fff;--c1:#e8437f;--t1:#fde4ee;--o1:#111}'
            document.body.className = `S-${name}${photos ? '' : ' NP'}`
            main = document.body.appendChild(document.createElement('main'))
            main.className = 'w'
            const cards = NAMES.flatMap((n, i) => [
              card(n, PRICES[i], photos),
              card(n, PRICES[(i + 1) % PRICES.length], photos && i % 2 === 0),
            ])
            main.innerHTML = `<section class="k1"><h2>Lunch</h2><ul>${cards.join('')}</ul></section>`

            expect(main.querySelector('.ad, .ao, [data-x="A"]')).toBeNull()
            expect(
              document.documentElement.scrollWidth,
              'no sideways scroll'
            ).toBeLessThanOrEqual(width)

            const rows = new Map<number, number[]>()
            for (const li of main.querySelectorAll('li')) {
              const it = li.querySelector<HTMLElement>('.it')!
              const tx = li.querySelector<HTMLElement>('.tx')!
              const box = it.getBoundingClientRect()
              const what = `${li.querySelector('.nm')!.textContent} / ${li.querySelector('.pr')!.textContent}`
              // No room kept for a "+" (its tap area is 44 px).
              for (const el of [it, tx])
                expect(
                  px(getComputedStyle(el).paddingInlineEnd),
                  `${what}: free space at the end`
                ).toBeLessThan(40)
              // Name, price and photo inside the card.
              for (const sel of ['.nm', '.pr', '.im']) {
                const el = li.querySelector<HTMLElement>(sel)
                if (!el) continue
                // Its place in the layout: a tilted photo (style G) may lean out by design.
                el.style.transform = 'none'
                const r = el.getBoundingClientRect()
                el.style.transform = ''
                expect(r.left, `${what}: ${sel} inside`).toBeGreaterThanOrEqual(
                  box.left - 0.5
                )
                expect(r.right, `${what}: ${sel} inside`).toBeLessThanOrEqual(
                  box.right + 0.5
                )
              }
              const top = Math.round(li.getBoundingClientRect().top)
              rows.set(top, [...(rows.get(top) ?? []), Math.round(box.height)])
            }
            // Side by side (grid styles, wide screens): the same height.
            for (const [top, heights] of rows)
              if (heights.length > 1)
                expect(
                  Math.max(...heights) - Math.min(...heights),
                  `row at ${top}: equal heights`
                ).toBeLessThanOrEqual(1)
          })
})
