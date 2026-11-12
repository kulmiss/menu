// Scroll lock for the guest page's sheets (backend/resources/public-menu/sheet.js):
// while the dish sheet, My order or the waiter view is open, the page behind
// stays put, and closing puts it back at exactly the same spot (owner, 2026-11-12).
import source from '@public-menu/sheet.js?raw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

type W = Window & { mL?: () => void; mD?: unknown }
const w = window as W
let host: HTMLElement
let dl: HTMLDialogElement

beforeEach(() => {
  host = document.createElement('div')
  host.innerHTML = `<div style="height:5000px">menu</div>
    <dialog id="s" style="max-height:50vh"><div id="sb" style="height:3000px">dish</div></dialog>`
  document.body.append(host)
  dl = document.getElementById('s') as HTMLDialogElement
  // The page data the on-demand file reads; no "My order" here.
  w.mD = { t: { mu: '' } }
  new Function(source)()
})
afterEach(() => {
  if (dl.open) dl.close()
  host.remove()
  document.body.className = ''
  document.body.style.top = ''
  window.scrollTo({ top: 0, behavior: 'instant' })
  delete w.mL
})

const touch = (type: string, y: number) =>
  dl.dispatchEvent(
    new TouchEvent(type, {
      bubbles: true,
      touches:
        type === 'touchend'
          ? []
          : [new Touch({ identifier: 1, target: dl, clientY: y })],
    })
  )

describe('sheet scroll lock', () => {
  it('pins the page while a sheet is open and restores the exact position', async () => {
    window.scrollTo({ top: 1234, behavior: 'instant' })
    dl.showModal()
    expect(document.body.classList.contains('lk')).toBe(true)
    expect(getComputedStyle(document.body).position).toBe('fixed')
    expect(document.body.style.top).toBe('-1234px')

    // Scrolling the sheet moves only the sheet.
    dl.scrollTop = 600
    expect(dl.scrollTop).toBe(600)
    window.scrollBy(0, 500)
    expect(document.body.getBoundingClientRect().top).toBe(-1234)

    const closed = new Promise((r) =>
      dl.addEventListener('close', r, { once: true })
    )
    dl.close()
    await closed // "close" fires as a task, like Escape or the × button
    expect(document.body.classList.contains('lk')).toBe(false)
    expect(document.body.style.top).toBe('')
    expect(window.scrollY).toBe(1234)
  })

  it('keeps scroll chaining inside the sheet', () => {
    expect(getComputedStyle(dl).overscrollBehaviorY).toBe('contain')
  })

  it('stays locked while the waiter view is shown, then lets go', () => {
    window.scrollTo({ top: 800, behavior: 'instant' })
    dl.showModal()
    const wv = document.body.appendChild(document.createElement('div'))
    wv.className = 'wv'
    dl.close() // the order sheet closes under the waiter view
    expect(document.body.classList.contains('lk')).toBe(true)
    wv.remove()
    w.mL!()
    expect(document.body.classList.contains('lk')).toBe(false)
    expect(window.scrollY).toBe(800)
  })

  it('closes on a swipe down from the top of the sheet, not on a short drag', () => {
    dl.showModal()
    touch('touchstart', 100)
    touch('touchmove', 140)
    touch('touchend', 140)
    expect(dl.open).toBe(true)

    touch('touchstart', 100)
    touch('touchmove', 260)
    expect(dl.style.transform).toBe('translateY(160px)')
    touch('touchend', 260)
    expect(dl.open).toBe(false)
    expect(dl.style.transform).toBe('')
  })

  it('does not close when the swipe starts with the sheet scrolled down', () => {
    dl.showModal()
    dl.scrollTop = 300
    touch('touchstart', 100)
    touch('touchmove', 300)
    touch('touchend', 300)
    expect(dl.open).toBe(true)
  })
})
