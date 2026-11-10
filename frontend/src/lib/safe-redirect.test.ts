import { describe, expect, it } from 'vitest'
import { safeRedirect } from './safe-redirect'

describe('safeRedirect', () => {
  it('keeps same-site paths', () => {
    expect(safeRedirect('/admin/team?tab=1')).toBe('/admin/team?tab=1')
    expect(safeRedirect(`${window.location.origin}/admin/settings`)).toBe(
      '/admin/settings'
    )
    expect(safeRedirect('/auth/invite/abc')).toBe('/auth/invite/abc')
  })

  it('refuses other sites and sign-in pages', () => {
    expect(safeRedirect('https://evil.example/admin')).toBe('/admin')
    expect(safeRedirect('//evil.example')).toBe('/admin')
    expect(safeRedirect('/auth/sign-in')).toBe('/admin')
    expect(safeRedirect(undefined)).toBe('/admin')
  })
})
