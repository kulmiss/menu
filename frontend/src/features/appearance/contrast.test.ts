import { describe, expect, it } from 'vitest'
import { contrast, isHex, tooLightOnWhite } from './contrast'

describe('contrast', () => {
  it('matches the WCAG reference values', () => {
    expect(contrast('#000000', '#ffffff')).toBeCloseTo(21, 1)
    expect(contrast('#767676', '#ffffff')).toBeCloseTo(4.54, 2)
  })

  it('flags colours too light for a white page', () => {
    expect(tooLightOnWhite('#fde047')).toBe(true)
    expect(tooLightOnWhite('#b4432a')).toBe(false)
    expect(tooLightOnWhite('nope')).toBe(false)
  })

  it('accepts only #rrggbb', () => {
    expect(isHex('#A1b2C3')).toBe(true)
    expect(isHex('#abc')).toBe(false)
  })
})
