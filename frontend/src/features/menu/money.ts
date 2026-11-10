/**
 * Prices are stored in minor units (santim for ETB). Restaurants' own
 * symbol/decimals settings arrive with Appearance; until then: a short
 * symbol for known currencies and decimals only when needed ("Br 120",
 * "Br 120.50").
 */
const SYMBOLS: Record<string, string> = {
  ETB: 'Br',
  USD: '$',
  EUR: '€',
  SOS: 'Sh',
  KES: 'KSh',
}

export function formatMoney(
  minor: number | null | undefined,
  currency: string,
  locale = 'en'
): string {
  if (minor === null || minor === undefined) return ''
  const major = minor / 100
  const whole = Number.isInteger(major)
  const number = new Intl.NumberFormat(locale, {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: 2,
    numberingSystem: 'latn',
  }).format(major)
  return `${SYMBOLS[currency] ?? currency} ${number}`
}

/** "120.5" / "120,50" → 12050; blank → null; nonsense → NaN. */
export function parseMoney(text: string): number | null {
  const clean = text.trim().replace(/\s/g, '').replace(',', '.')
  if (clean === '') return null
  if (!/^\d+(\.\d{0,2})?$/.test(clean)) return Number.NaN
  return Math.round(Number(clean) * 100)
}

/** 12050 → "120.50", 12000 → "120". */
export function moneyInputValue(minor: number | null | undefined): string {
  if (minor === null || minor === undefined) return ''
  return minor % 100 === 0 ? String(minor / 100) : (minor / 100).toFixed(2)
}
