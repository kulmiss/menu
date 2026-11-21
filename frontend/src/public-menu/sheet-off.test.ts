// "My order" switched off (QUESTIONS #236): the dish sheet still shows sizes and add-ons,
// as information only, with no Add button and none of its CSS; a list saved on this phone
// while it was on is cleared (only this restaurant's), never shown.
import base from '@public-menu/base.css?raw'
import source from '@public-menu/sheet.js?raw'
import grid from '@public-menu/style-grid.css?raw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

type W = Window & {
  mD?: unknown
  mS?: (li: Element, d: unknown) => void
  mC?: unknown
  mA?: unknown
}
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
    o: null,
  },
  c: {
    7: {
      d: 'Espresso with steamed milk.',
      v: [
        ['Regular', 'Br 100'],
        ['Large', 'Br 125'],
      ],
      a: [
        [
          'Milk',
          0,
          1,
          [
            ['Oat milk', '+Br 15', 0],
            ['Soy milk', '+Br 10', 1],
          ],
        ],
      ],
    },
  },
  o: null,
}

let host: HTMLElement
let css: HTMLStyleElement
let dl: HTMLDialogElement

beforeEach(() => {
  css = document.head.appendChild(document.createElement('style'))
  css.textContent = base + grid
  host = document.body.appendChild(document.createElement('div'))
  // The card as the page writes it with My order off: no "ao", no "+".
  host.innerHTML = `<ul><li data-i="7"><button class="it np"><span class="nm">Cappuccino</span>
    <span class="pr">from Br 100</span></button></li></ul>
    <dialog id="s"><div class="sh" id="sb"></div><button class="x" id="sx">×</button></dialog>`
  dl = document.getElementById('s') as HTMLDialogElement
  delete w.mC
  delete w.mA
})
afterEach(() => {
  if (dl.open) dl.close()
  host.remove()
  css.remove()
  document.querySelectorAll('#sst,.tt,.obr').forEach((e) => e.remove())
  document.body.className = ''
  document.body.style.top = ''
  localStorage.clear()
})

describe('dish sheet with My order off', () => {
  it('shows sizes and add-ons as plain information, with no Add button and no My order CSS', () => {
    w.mD = D
    new Function(source)()
    w.mS!(host.querySelector('li')!, D)
    expect(dl.open).toBe(true)
    const text = dl.textContent!
    for (const s of [
      'Sizes',
      'Regular',
      'Br 100',
      'Large',
      'Br 125',
      'Milk',
      'Choose up to 1',
      'Oat milk',
      '+Br 15',
      'Soy milk',
      'Sold out',
    ])
      expect(text).toContain(s)
    expect(dl.querySelector('input, .oa, .sf, .oc')).toBeNull()
    expect(text).not.toMatch(/my order|add to/i)
    expect(document.getElementById('sst')).toBeNull()
    expect(w.mC).toBeUndefined()
    expect(w.mA).toBeUndefined()
  })

  it('clears this restaurant’s saved list (every branch), and keeps other restaurants’ lists', () => {
    localStorage.setItem(
      'mo:1:1',
      JSON.stringify({ i: [{ i: 7, v: 0, a: [], q: 2 }] })
    )
    localStorage.setItem('mo:1:4', JSON.stringify({ i: [] }))
    localStorage.setItem(
      'mo:12:1',
      JSON.stringify({ i: [{ i: 3, v: 0, a: [], q: 1 }] })
    )
    localStorage.setItem('other', 'x')
    w.mD = D
    new Function(source)()
    expect(localStorage.getItem('mo:1:1')).toBeNull()
    expect(localStorage.getItem('mo:1:4')).toBeNull()
    expect(localStorage.getItem('mo:12:1')).not.toBeNull()
    expect(localStorage.getItem('other')).toBe('x')
  })

  it('keeps the list when My order is on, and in the admin preview', () => {
    localStorage.setItem('mo:1:1', '{"i":[]}')
    w.mD = { ...D, o: {} }
    new Function(source)()
    expect(localStorage.getItem('mo:1:1')).toBe('{"i":[]}')
    w.mD = { ...D, p: 1 }
    new Function(source)()
    expect(localStorage.getItem('mo:1:1')).toBe('{"i":[]}')
  })
})
