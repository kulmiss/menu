import { tenantFixture } from '@/test-utils/fixtures'
import { describe, expect, it } from 'vitest'
import { isManagerOrOwner, manageableRoles, pickTenant } from './use-me'

describe('pickTenant', () => {
  const a = tenantFixture({ id: 1 })
  const b = tenantFixture({ id: 2 })

  it('uses the restaurant chosen in the switcher', () => {
    expect(pickTenant([a, b], 2)).toBe(b)
  })

  it('falls back to the first one when the choice is gone', () => {
    expect(pickTenant([a, b], 99)).toBe(a)
    expect(pickTenant([a, b], null)).toBe(a)
    expect(pickTenant([], 1)).toBeNull()
  })
})

describe('roles', () => {
  it('mirrors the server: owners manage managers and staff, managers staff', () => {
    expect(manageableRoles('owner')).toEqual(['manager', 'staff'])
    expect(manageableRoles('manager')).toEqual(['staff'])
    expect(manageableRoles('staff')).toEqual([])
    expect(isManagerOrOwner('staff')).toBe(false)
  })
})
