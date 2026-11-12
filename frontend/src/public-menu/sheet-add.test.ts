// The dish sheet's "Add to my order" button (backend/resources/public-menu/sheet.js):
// full width, sticky at the bottom of the sheet, price with the chosen size and
// add-ons, and never on top of the sheet's content (owner, 2026-11-12).
import base from '@public-menu/base.css?raw'
import source from '@public-menu/sheet.js?raw'
import grid from '@public-menu/style-grid.css?raw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

type W = Window & { mD?: unknown; mS?: (li: Element, d: unknown) => void }
const w = window as W
const D = {
  id: 1,
  b: 1,
  cu: 'Br',
  p: 0,
  t: {
    sz: 'Sizes',
    so: 'Sold out',
    up: 'Choose up to :max',
    ex: 'Choose :count',
    ch: 'Choose :min–:max',
    al: 'Allergens',
    kc: ':count kcal',
    pm: ':count min',
    ph: ':count photos',
    mu: '',
    tp: 'Back to top',
    o: {
      add: 'Add to my order',
      added: 'Added',
      bar: 'My order (:count) · :price',
      pick: 'Choose :count',
    },
  },
  c: {
    7: {
      d: 'Espresso with steamed milk. '.repeat(40),
      v: [
        ['Regular', 'Br 100'],
        ['Large', 'Br 125'],
      ],
      a: [['Milk', 0, 1, [['Oat milk', '+Br 15', 0]]]],
    },
  },
  o: {
    7: [
      null,
      [
        [1, 10000],
        [2, 12500],
      ],
      [[0, 1, [[5, 1500, 0]]]],
      0,
    ],
  },
}

let host: HTMLElement
let css: HTMLStyleElement
let dl: HTMLDialogElement
const btn = () => document.querySelector<HTMLButtonElement>('.oa')!

beforeEach(() => {
  css = document.head.appendChild(document.createElement('style'))
  css.textContent = grid + base + ':root{--a:#9a3412;--af:#fff}' // set per restaurant by the page
  host = document.body.appendChild(document.createElement('div'))
  host.innerHTML = `<ul><li data-i="7"><button class="it"><span class="nm">Cappuccino</span>
    <span class="pr">from Br 100</span><span class="ad">+</span></button></li></ul>
    <dialog id="s"><div class="sh" id="sb"></div><button class="x" id="sx">×</button></dialog>`
  dl = document.getElementById('s') as HTMLDialogElement
  localStorage.clear()
  w.mD = D
  new Function(source)()
  w.mS!(host.querySelector('li')!, D)
})
afterEach(() => {
  if (dl.open) dl.close()
  host.remove()
  css.remove()
  document.querySelectorAll('#sst,.tt').forEach((e) => e.remove())
  document.body.className = ''
  document.body.style.top = ''
})

describe('Add to my order in the dish sheet', () => {
  it('is a full-width brand button, at least 44 px tall, stuck to the bottom of the sheet', () => {
    const bar = btn().parentElement!
    expect(getComputedStyle(bar).position).toBe('sticky')
    const s = dl.getBoundingClientRect()
    const b = btn().getBoundingClientRect()
    expect(b.height).toBeGreaterThanOrEqual(44)
    expect(b.width).toBeGreaterThanOrEqual(s.width - 2 * 16 - 1)
    // Long description: the bar is at the sheet's bottom edge while the content scrolls under it.
    expect(
      Math.abs(bar.getBoundingClientRect().bottom - s.bottom)
    ).toBeLessThanOrEqual(1)
    expect(
      parseFloat(getComputedStyle(bar).paddingBottom)
    ).toBeGreaterThanOrEqual(12)
    expect(getComputedStyle(btn()).backgroundColor).not.toBe('rgba(0, 0, 0, 0)')
  })

  it('shows the price with the chosen size and add-ons', () => {
    expect(btn().textContent).toBe('Add to my order · Br 100')
    const [, large] = [
      ...dl.querySelectorAll<HTMLInputElement>('input[type=radio]'),
    ]
    large.click()
    expect(btn().textContent).toBe('Add to my order · Br 125')
    dl.querySelector<HTMLInputElement>('input[type=checkbox]')!.click()
    expect(btn().textContent).toBe('Add to my order · Br 140')
  })

  it('never covers the content: scrolled to the end, everything sits above the bar', () => {
    dl.scrollTop = 1e6
    const bar = btn().parentElement!
    const last = bar.previousElementSibling!
    expect(last.getBoundingClientRect().bottom).toBeLessThanOrEqual(
      bar.getBoundingClientRect().top + 0.5
    )
  })

  it('lines up the choices: control, name on the left, price on the right', () => {
    const row = dl.querySelector('.oc')!
    const [input, name, price] = [...row.children].map((e) =>
      e.getBoundingClientRect()
    )
    expect(name.left - input.right).toBeLessThanOrEqual(16)
    expect(getComputedStyle(row.children[1]).textAlign).not.toBe('center')
    expect(price.right).toBeCloseTo(row.getBoundingClientRect().right, 0)
    expect(row.getBoundingClientRect().height).toBeGreaterThanOrEqual(44)
  })
})
