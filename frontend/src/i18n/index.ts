import am from '@/locales/am/common.json'
import ar from '@/locales/ar/common.json'
import en from '@/locales/en/common.json'
import so from '@/locales/so/common.json'
import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'

export const LANGUAGES = [
  { code: 'en', dir: 'ltr' },
  { code: 'so', dir: 'ltr' },
  { code: 'am', dir: 'ltr' },
  { code: 'ar', dir: 'rtl' },
] as const

export type LanguageCode = (typeof LANGUAGES)[number]['code']

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

/** True while a locale file is still an unreviewed machine draft. */
export function isDraftLanguage(code: string): boolean {
  const bundle = i18n.getResourceBundle(code, 'common') as
    | { _status?: string }
    | undefined
  return bundle?._status === 'machine_draft'
}

void i18n.use(initReactI18next).init({
  resources: {
    en: { common: en },
    so: { common: so },
    am: { common: am },
    ar: { common: ar },
  },
  lng: storedLanguage(),
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

export default i18n
