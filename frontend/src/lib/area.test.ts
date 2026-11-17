import { afterEach, describe, expect, it } from 'vitest'
import { rewriteIn, rewriteOut, superPath } from './area'

// QUESTIONS #215: Super Admin screens are /platform/… inside the app and the
// secret address (only in that area's HTML) in the browser.
describe('the two areas', () => {
  afterEach(() => document.querySelector('meta[name="super-path"]')?.remove())

  it('reads the Super Admin address from its page only', () => {
    const meta = document.createElement('meta')
    meta.name = 'super-path'
    meta.content = '/office-7k2q'
    document.head.append(meta)
    expect(superPath()).toBe('/office-7k2q')
    // Never anything that isn't one plain path part.
    meta.content = '//evil.example'
    expect(superPath()).not.toBe('//evil.example')
  })

  it('maps the secret address to the Super Admin screens and back', () => {
    expect(rewriteIn('/office-7k2q', '/office-7k2q')).toBe('/platform')
    expect(rewriteIn('/office-7k2q/restaurants', '/office-7k2q')).toBe(
      '/platform/restaurants'
    )
    expect(rewriteOut('/platform/restaurants', '/office-7k2q')).toBe(
      '/office-7k2q/restaurants'
    )
    expect(rewriteOut('/platform', '/office-7k2q')).toBe('/office-7k2q')
    // Restaurant pages are left alone; a look-alike prefix is not the area.
    expect(rewriteIn('/admin/dishes', '/office-7k2q')).toBe('/admin/dishes')
    expect(rewriteIn('/office-7k2qx', '/office-7k2q')).toBe('/office-7k2qx')
  })

  it('never opens the Super Admin screens at /platform or in the restaurant area', () => {
    expect(rewriteIn('/platform/restaurants', '/office-7k2q')).toBe(
      '/not-found/platform/restaurants'
    )
    expect(rewriteIn('/platform', null)).toBe('/not-found/platform')
    expect(rewriteOut('/platform/plans', null)).toBe('/platform/plans')
  })
})
