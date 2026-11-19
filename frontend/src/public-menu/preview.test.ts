// The admin's live preview of the guest page (Appearance; D.p = 1) keeps no
// "My order" list. Bug (2026-11-17): there, tapping a dish threw
// "Cannot read properties of undefined (reading 'fmt')" and the dish never
// opened; tapping a "+" did nothing and loaded the on-demand file again.
import base from '@public-menu/base.css?raw'
import order from '@public-menu/order.css?raw'
import orderSource from '@public-menu/order.js?raw'
import source from '@public-menu/sheet.js?raw'
import grid from '@public-menu/style-grid.css?raw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

type W = Window & {
  mD?: unknown
  mS?: (li: Element, d: unknown) => void
  mA?: (a: Element) => void
  mC?: unknown
}
const w = window as W
const D = {
  id: 1,
  b: 1,
  p: 1, // the preview
  cu: 'Br',
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
      d: 'Espresso with steamed milk.',
      v: [
        ['Regular', 'Br 100'],
        ['Large', 'Br 125'],
      ],
    },
    8: { d: 'Hot water.' },
  },
  o: {
    7: [
      null,
      [
        [1, 10000],
        [2, 12500],
      ],
      [],
      0,
    ],
    8: [3000, [], [], 0],
  },
}

let host: HTMLElement
let css: HTMLStyleElement
let dl: HTMLDialogElement
const errors: string[] = []
const onError = (e: ErrorEvent) => errors.push(e.message)

beforeEach(() => {
  errors.length = 0
  window.addEventListener('error', onError)
  css = document.head.appendChild(document.createElement('style'))
  css.textContent = grid + base + order
  host = document.body.appendChild(document.createElement('div'))
  host.innerHTML = `<ul><li data-i="7"><button class="it ao"><span class="nm">Cappuccino</span>
    <span class="pr">from Br 100</span><span class="ad" data-x="A">+</span></button></li>
    <li data-i="8"><button class="it ao"><span class="nm">Water</span>
    <span class="pr">Br 30</span><span class="ad" data-x="A">+</span></button></li></ul>
    <dialog id="s"><div class="sh" id="sb"></div><button class="x" id="sx">×</button></dialog>`
  dl = document.getElementById('s') as HTMLDialogElement
  delete w.mA
  delete w.mC
  w.mD = D
  // With My order on the page loads one file holding both (InlineAssets::sheetUrl).
  new Function(source)()
  new Function(orderSource)()
})
afterEach(() => {
  window.removeEventListener('error', onError)
  if (dl.open) dl.close()
  host.remove()
  css.remove()
  document.querySelectorAll('#sst,.tt,.obr').forEach((e) => e.remove())
  delete w.mA
})

describe('guest page in the admin preview', () => {
  it('opens a dish with sizes as plain rows and no Add button', () => {
    expect(() => w.mS!(host.querySelector('li')!, D)).not.toThrow()
    expect(dl.open).toBe(true)
    expect(dl.querySelector('.oa')).toBeNull()
    expect(dl.querySelector('input')).toBeNull()
    expect(dl.textContent).toContain('Large')
  })

  it('a "+" opens the dish instead of adding to a list', () => {
    expect(typeof w.mA).toBe('function')
    w.mA!(host.querySelector('li[data-i="8"] .ad')!)
    expect(dl.open).toBe(true)
    expect(dl.querySelector('h3')!.textContent).toBe('Water')
    expect(document.querySelector('.obr')).toBeNull()
    expect(errors).toEqual([])
  })
})
