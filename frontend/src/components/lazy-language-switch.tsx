import { lazy, Suspense } from 'react'
import { LANGUAGES } from '@/i18n'

// Shown only once a second language exists (English only for now), so the
// menu's code stays out of the first download until then.
const LanguageSwitch = lazy(() =>
  import('@/components/language-switch').then((m) => ({
    default: m.LanguageSwitch,
  }))
)

export function LazyLanguageSwitch() {
  if (LANGUAGES.length < 2) return null
  return (
    <Suspense fallback={null}>
      <LanguageSwitch />
    </Suspense>
  )
}
