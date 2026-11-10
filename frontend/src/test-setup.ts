// Loads the i18n resources so components render real English text in tests.
import i18n from '@/i18n'
import screens from '@/locales/en/screens.json'
import { beforeEach } from 'vitest'

// Screen texts load with the admin area in the app; tests render screens directly.
i18n.addResourceBundle('en', 'common', screens, true, false)

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
