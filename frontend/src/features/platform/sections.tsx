import { lazy, Suspense } from 'react'
import { Skeleton } from '@/components/ui/skeleton'

// Each platform screen downloads on its own, when first opened.
const SCREENS = {
  restaurants: lazy(() => import('./restaurants-route')),
  plans: lazy(() =>
    import('./screens').then((m) => ({ default: m.PlansPage }))
  ),
  payments: lazy(() =>
    import('./screens').then((m) => ({ default: m.PaymentsPage }))
  ),
  themes: lazy(() =>
    import('./screens').then((m) => ({ default: m.ThemesPage }))
  ),
  announcements: lazy(() =>
    import('./screens').then((m) => ({ default: m.AnnouncementsPage }))
  ),
  activity: lazy(() =>
    import('./screens').then((m) => ({ default: m.ActivityPage }))
  ),
  settings: lazy(() =>
    import('./screens').then((m) => ({ default: m.SettingsPage }))
  ),
  security: lazy(() =>
    import('@/features/settings/security').then((m) => ({
      default: m.SecuritySettings,
    }))
  ),
} as const

export function PlatformSection({ section }: { section: string }) {
  const Screen = SCREENS[section as keyof typeof SCREENS]
  return (
    <Suspense fallback={<Skeleton className='h-64' />}>
      <Screen />
    </Suspense>
  )
}
