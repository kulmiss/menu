import { describe, expect, it } from 'vitest'
import { isValidSlug, slugify } from './slug'

describe('slugify', () => {
  it('turns a name into a menu address', () => {
    expect(slugify('Buna & Bread Café')).toBe('buna-bread-cafe')
    expect(slugify('  Sahan   Grill!! ')).toBe('sahan-grill')
  })

  it('gives an empty slug for names without Latin letters', () => {
    expect(slugify('ቡና')).toBe('')
  })

  it('keeps slugs at most 50 characters without a trailing hyphen', () => {
    const slug = slugify('a'.repeat(49) + ' b')
    expect(slug.length).toBeLessThanOrEqual(50)
    expect(slug.endsWith('-')).toBe(false)
  })
})

describe('isValidSlug', () => {
  it('matches the server rules', () => {
    expect(isValidSlug('buna-bread')).toBe(true)
    expect(isValidSlug('ab')).toBe(false)
    expect(isValidSlug('-buna')).toBe(false)
    expect(isValidSlug('buna--bread')).toBe(false)
    expect(isValidSlug('Buna')).toBe(false)
  })
})
