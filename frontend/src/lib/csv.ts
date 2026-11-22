export type CsvCell = string | number | null | undefined

/**
 * Rows as CSV text (RFC 4180): quotes around cells with a comma, quote or
 * line break, doubled quotes inside. A text cell starting with = + - @ (or a
 * tab/return) gets a leading ' so a spreadsheet never runs it as a formula.
 * Numbers stay numbers (a negative number is not text).
 */
export function toCsv(rows: CsvCell[][]): string {
  const cell = (v: CsvCell) => {
    if (v === null || v === undefined) return ''
    if (typeof v === 'number') return Number.isFinite(v) ? String(v) : ''
    let s = v
    if (/^[=+\-@\t\r]/.test(s)) s = `'${s}`
    return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
  }
  return rows.map((r) => r.map(cell).join(',')).join('\r\n') + '\r\n'
}

/** Downloads rows as a .csv file (with a byte-order mark so Excel reads UTF-8). */
export function downloadCsv(name: string, rows: CsvCell[][]) {
  const blob = new Blob(['﻿' + toCsv(rows)], {
    type: 'text/csv;charset=utf-8',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name.endsWith('.csv') ? name : `${name}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 0)
}
