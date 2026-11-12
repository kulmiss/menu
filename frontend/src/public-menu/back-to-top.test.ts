// "Back to top" on the guest page (backend/resources/public-menu/sheet.js):
// shows after ~1.5 screens, sits above the My order bar, hides while a sheet
// is open, and respects reduced motion (owner, 2026-11-12).
import source from '@public-menu/sheet.js?raw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const w = window as Window & { mD?: unknown }
let host: HTMLElement
const btn = () => document.querySelector<HTMLButtonElement>('.tt')!
const at = async (y: number) => {
  window.scrollTo({ top: y, behavior: 'instant' })
  window.dispatchEvent(new Event('scroll'))
  await new Promise((r) => setTimeout(r, 250)) // let the fade finish
}

beforeEach(() => {
  host = document.createElement('div')
  host.innerHTML = `<div style="height:${innerHeight * 6}px">menu</div><dialog id="s"></dialog>`
  document.body.append(host)
  w.mD = { t: { mu: '', tp: 'Back to top' } }
  new Function(source)()
})
afterEach(() => {
  document.querySelectorAll('.tt').forEach((b) => b.remove())
  host.remove()
  document.body.className = ''
  window.scrollTo({ top: 0, behavior: 'instant' })
  vi.restoreAllMocks()
})

describe('back to top', () => {
  it('appears only after about 1.5 screens and is a 44 px round brand button', async () => {
    await at(innerHeight)
    expect(getComputedStyle(btn()).visibility).toBe('hidden')
    await at(innerHeight * 2)
    const cs = getComputedStyle(btn())
    expect(cs.visibility).toBe('visible')
    expect(btn().getAttribute('aria-label')).toBe('Back to top')
    const r = btn().getBoundingClientRect()
    expect(r.width).toBeGreaterThanOrEqual(44)
    expect(r.height).toBeGreaterThanOrEqual(44)
    expect(parseFloat(cs.borderTopLeftRadius)).toBeGreaterThanOrEqual(22)
  })

  it('moves up above the My order bar and hides while a sheet is open', async () => {
    await at(innerHeight * 2)
    const low = btn().getBoundingClientRect().bottom
    document.body.classList.add('hob')
    await new Promise((r) => setTimeout(r, 250))
    // The bar is 3.25 rem tall, 0.75 rem from the bottom: the button clears it.
    expect(
      innerHeight - btn().getBoundingClientRect().bottom
    ).toBeGreaterThanOrEqual(4 * 16)
    expect(btn().getBoundingClientRect().bottom).toBeLessThan(low)
    document.body.classList.add('lk')
    expect(getComputedStyle(btn()).display).toBe('none')
  })

  it('scrolls smoothly to the top, or instantly with reduced motion', async () => {
    await at(innerHeight * 3)
    const spy = vi.spyOn(window, 'scrollTo')
    btn().click()
    expect(spy).toHaveBeenLastCalledWith({ top: 0, behavior: 'smooth' })
    vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: true,
    } as MediaQueryList)
    btn().click()
    expect(spy).toHaveBeenLastCalledWith({ top: 0, behavior: 'instant' })
  })
})
