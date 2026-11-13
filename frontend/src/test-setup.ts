// Loads the i18n resources so components render real English text in tests.
import i18n from '@/i18n'
import appearance from '@/locales/en/appearance.json'
import platform from '@/locales/en/platform.json'
import screens from '@/locales/en/screens.json'
import signup from '@/locales/en/signup.json'
import { beforeEach } from 'vitest'

// Screen texts load with the admin area in the app; tests render screens directly.
i18n.addResourceBundle('en', 'common', screens, true, false)
i18n.addResourceBundle('en', 'common', { appearance }, true, false)
i18n.addResourceBundle('en', 'common', { platform }, true, false)
i18n.addResourceBundle('en', 'common', { signup }, true, false)

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
