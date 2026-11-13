/**
 * Tiny checks for URL query values. Routes use these instead of a schema
 * library so zod stays out of the first download (PLAN.md §15).
 */
export function optionalString(value: unknown): string | undefined {
  return typeof value === 'string' && value !== '' ? value : undefined
}

export function optionalId(value: unknown): number | undefined {
  const n =
    typeof value === 'number'
      ? value
      : typeof value === 'string'
        ? Number(value)
        : NaN
  return Number.isInteger(n) && n > 0 ? n : undefined
}
