// Loads the i18n resources so components render real English text in tests.
import i18n from '@/i18n'
import { beforeEach } from 'vitest'

// Test files run side by side and share localStorage: a language chosen in
// one file must not leak into another.
beforeEach(async () => {
  try {
    localStorage.removeItem('ui_language')
  } catch {
    // storage unavailable
  }
  if (i18n.language !== 'en') await i18n.changeLanguage('en')
})
