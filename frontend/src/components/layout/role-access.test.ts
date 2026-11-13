import { describe, expect, it } from 'vitest'
import { canOpen } from './role-access'

describe('which screens a role may open', () => {
  it('keeps manager screens from Staff, billing for Owners', () => {
    for (const path of [
      '/admin/appearance',
      '/admin/info',
      '/admin/qr-codes',
      '/admin/print-materials',
      '/admin/insights',
      '/admin/team/',
      '/admin/branches',
    ]) {
      expect(canOpen(path, 'staff'), path).toBe(false)
      expect(canOpen(path, 'manager'), path).toBe(true)
    }
    expect(canOpen('/admin/settings/billing', 'manager')).toBe(false)
    expect(canOpen('/admin/settings/billing', 'owner')).toBe(true)
  })

  it('lets everyone open the shared screens', () => {
    for (const path of [
      '/admin',
      '/admin/menu',
      '/admin/dishes',
      '/admin/settings',
      '/admin/settings/security',
    ])
      expect(canOpen(path, 'staff'), path).toBe(true)
  })
})
