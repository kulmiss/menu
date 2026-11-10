import { type Tag, type Translations } from './types'

/**
 * Menu text in the restaurant's main language, falling back to any filled
 * language (so nothing shows up blank in the editor).
 */
export function tr(value: Translations | undefined, locale: string): string {
  if (!value) return ''
  return value[locale] ?? Object.values(value).find((v) => v !== '') ?? ''
}

/** Sets one language and keeps the others. Blank removes that language. */
export function withText(
  value: Translations | undefined,
  locale: string,
  text: string
): Translations {
  const next = { ...(value ?? {}) }
  if (text.trim() === '') delete next[locale]
  else next[locale] = text
  return next
}

/** Weekday names in the UI language, Sunday = 0 (same as the server). */
export function weekdayNames(locale: string): string[] {
  const fmt = new Intl.DateTimeFormat(locale, { weekday: 'long' })
  // 2026-11-01 is a Sunday.
  return Array.from({ length: 7 }, (_, d) =>
    fmt.format(new Date(Date.UTC(2026, 10, 1 + d, 12)))
  )
}

/** Tag label: built-in tags are translated by the app. */
export function tagLabel(tag: Tag, locale: string, t: (k: string) => string) {
  return tag.key ? t(`tags.${tag.key}`) : tr(tag.name, locale)
}
