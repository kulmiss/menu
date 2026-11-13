// Unit tests for the guest page's opening-hours logic
// (backend/resources/public-menu/hours.js, inlined into every menu page).
import source from '@public-menu/hours.js?raw'
import { describe, expect, it } from 'vitest'

type Now = { w: number; t: string; d: string }
type Window = [number, string, string]
const lib = new Function(`${source}; return { mhNow, mhIn, mhState }`)() as {
  mhNow: (z: string, date?: Date) => Now
  mhIn: (list: Window[], n: Now) => Window | null
  mhState: (
    h: Window[],
    x: [string, string][],
    n: Now
  ) => (string | number)[] | null
}

const at = (w: number, t: string, d = '2026-11-10'): Now => ({ w, t, d })
// Mon–Fri 07:00–11:30 and 13:00–22:00 (a break), Sat 18:00–02:00 (overnight).
const HOURS: Window[] = [
  ...[1, 2, 3, 4, 5].flatMap((w): Window[] => [
    [w, '07:00', '11:30'],
    [w, '13:00', '22:00'],
  ]),
  [6, '18:00', '02:00'],
]

describe('mhNow', () => {
  it('reads the time in the restaurant time zone', () => {
    // 2026-11-10 21:30 UTC = Wednesday 00:30 in Addis Ababa (UTC+3).
    expect(
      lib.mhNow('Africa/Addis_Ababa', new Date('2026-11-10T21:30:00Z'))
    ).toEqual({
      w: 3,
      t: '00:30',
      d: '2026-11-11',
    })
  })
})

describe('mhState', () => {
  it('is open inside a window and says until when', () => {
    expect(lib.mhState(HOURS, [], at(2, '09:15'))).toEqual([1, '11:30'])
  })

  it('is closed during the break and opens again the same day', () => {
    expect(lib.mhState(HOURS, [], at(2, '12:00'))).toEqual([0, '13:00', 0])
  })

  it('treats the closing minute as closed', () => {
    expect(lib.mhState(HOURS, [], at(2, '22:00'))).toEqual([0, '07:00', 1])
  })

  it('keeps an overnight window open after midnight', () => {
    expect(lib.mhState(HOURS, [], at(0, '01:30'))).toEqual([1, '02:00'])
    expect(lib.mhState(HOURS, [], at(0, '02:00'))).toEqual([0, '07:00', 1])
  })

  it('finds the next opening several days ahead', () => {
    const fridays: Window[] = [[5, '10:00', '14:00']]
    expect(lib.mhState(fridays, [], at(3, '09:00'))).toEqual([0, '10:00', 2])
    // Open one day a week: after closing, the next opening is a week later.
    expect(lib.mhState(fridays, [], at(5, '15:00'))).toEqual([0, '10:00', 7])
  })

  it('says "closed today" on a holiday, even inside opening hours', () => {
    expect(
      lib.mhState(HOURS, [['2026-11-09', '2026-11-10']], at(2, '09:00'))
    ).toEqual([2])
  })

  it('has nothing to say without opening hours', () => {
    expect(lib.mhState([], [], at(2, '09:00'))).toBeNull()
  })
})

describe('mhIn (menus served at set times)', () => {
  it('matches the right day and time', () => {
    expect(lib.mhIn([[1, '06:30', '11:30']], at(1, '06:30'))).toEqual([
      1,
      '06:30',
      '11:30',
    ])
    expect(lib.mhIn([[1, '06:30', '11:30']], at(2, '07:00'))).toBeNull()
  })
})
