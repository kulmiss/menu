import { describe, expect, it } from 'vitest'
import { formatMoney, moneyInputValue, parseMoney } from './money'

describe('money', () => {
  it('formats birr with decimals only when needed', () => {
    expect(formatMoney(12000, 'ETB')).toBe('Br 120')
    expect(formatMoney(12050, 'ETB')).toBe('Br 120.50')
    expect(formatMoney(150000000, 'ETB')).toBe('Br 1,500,000')
    expect(formatMoney(null, 'ETB')).toBe('')
  })

  it('keeps Western digits in Arabic', () => {
    expect(formatMoney(12000, 'ETB', 'ar')).toMatch(/120/)
  })

  it('parses what people type', () => {
    expect(parseMoney('120')).toBe(12000)
    expect(parseMoney('120,5')).toBe(12050)
    expect(parseMoney(' 0.99 ')).toBe(99)
    expect(parseMoney('')).toBeNull()
    expect(parseMoney('12a')).toBeNaN()
    expect(parseMoney('1.234')).toBeNaN()
  })

  it('shows stored prices in inputs', () => {
    expect(moneyInputValue(12000)).toBe('120')
    expect(moneyInputValue(12050)).toBe('120.50')
    expect(moneyInputValue(null)).toBe('')
  })
})
