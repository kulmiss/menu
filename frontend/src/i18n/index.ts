import en from '@/locales/en/common.json'
import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'

/**
 * Interface languages. English only for now (owner, 2026-11-10; QUESTIONS
 * #15). Every text already goes through t(): adding a language is a new
 * src/locales/<code>/common.json plus a line here — the language switch
 * appears by itself once there are two.
 */
export const LANGUAGES: readonly { code: string; dir: 'ltr' | 'rtl' }[] = [
  { code: 'en', dir: 'ltr' },
]

export type LanguageCode = string

const STORAGE_KEY = 'ui_language'

function storedLanguage(): LanguageCode {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    if (LANGUAGES.some((l) => l.code === value)) return value as LanguageCode
  } catch {
    // storage unavailable: fall back to English
  }
  return 'en'
}

export function languageDir(code: string): 'ltr' | 'rtl' {
  return LANGUAGES.find((l) => l.code === code)?.dir ?? 'ltr'
}

export function persistLanguage(code: LanguageCode) {
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    // ignore: the choice just won't be remembered
  }
}

// English ships with the app; other languages download when chosen, so
// each visit loads only one translation file (PLAN.md §15).
const loaders = import.meta.glob<{ default: Record<string, unknown> }>(
  '../locales/*/common.json'
)
/** True while a locale file is still an unreviewed machine draft. */
export function isDraftLanguage(code: string): boolean {
  // Read from the files at build time (vite.config.ts), so the "(draft)"
  // label needs no translation download.
  return __LOCALE_STATUS__[code] === 'machine_draft'
}

/** Makes sure a language's texts are loaded. */
export async function loadLanguage(code: string): Promise<void> {
  if (code === 'en' || i18n.hasResourceBundle(code, 'common')) return
  const loader = loaders[`../locales/${code}/common.json`]
  if (!loader) return
  const bundle = (await loader()).default
  i18n.addResourceBundle(code, 'common', bundle, true, true)
}

/** Loads a language, then switches the interface to it. */
export async function setLanguage(code: LanguageCode): Promise<void> {
  await loadLanguage(code)
  await i18n.changeLanguage(code)
}

void i18n.use(initReactI18next).init({
  resources: { en: { common: en } },
  partialBundledLanguages: true,
  lng: 'en',
  fallbackLng: 'en',
  defaultNS: 'common',
  ns: ['common'],
  interpolation: { escapeValue: false },
  returnNull: false,
})

i18n.on('languageChanged', (lng) => {
  document.documentElement.setAttribute('lang', lng)
})
document.documentElement.setAttribute('lang', i18n.language)

/** Resolves once the remembered language is loaded (render after this). */
export const i18nReady: Promise<void> = (() => {
  const stored = storedLanguage()
  return stored === 'en'
    ? Promise.resolve()
    : setLanguage(stored).catch(() => undefined)
})()

export default i18n
