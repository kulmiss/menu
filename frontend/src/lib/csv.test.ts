import { describe, expect, it } from 'vitest'
import { toCsv } from './csv'

describe('toCsv', () => {
  it('quotes cells with commas, quotes and line breaks', () => {
    expect(
      toCsv([
        ['Name', 'Views'],
        ['Café, Bole', 12],
        ['Say "hi"', 0],
        ['two\nlines', null],
      ])
    ).toBe(
      'Name,Views\r\n"Café, Bole",12\r\n"Say ""hi""",0\r\n"two\nlines",\r\n'
    )
  })

  it('never lets a text cell run as a spreadsheet formula, but keeps negative numbers', () => {
    expect(toCsv([['=SUM(A1)', '+1', '-2', '@x', 'ok', -3]])).toBe(
      "'=SUM(A1),'+1,'-2,'@x,ok,-3\r\n"
    )
  })
})
