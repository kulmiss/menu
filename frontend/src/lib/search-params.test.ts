import { describe, expect, it } from 'vitest'
import { optionalId, optionalString } from './search-params'

describe('search params', () => {
  it('keeps real values only', () => {
    expect(optionalString('/admin')).toBe('/admin')
    expect(optionalString('')).toBeUndefined()
    expect(optionalString(5)).toBeUndefined()
    expect(optionalId('12')).toBe(12)
    expect(optionalId(3)).toBe(3)
    expect(optionalId('-1')).toBeUndefined()
    expect(optionalId('abc')).toBeUndefined()
    expect(optionalId('1.5')).toBeUndefined()
  })
})
