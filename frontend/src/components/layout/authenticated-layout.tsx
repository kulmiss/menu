import { lazy, Suspense } from 'react'
import { Outlet } from '@tanstack/react-router'
import { getCookie } from '@/lib/cookies'
import { cn } from '@/lib/utils'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { PasswordBanner } from '@/components/layout/password-banner'
import { SubscriptionBanner } from '@/components/layout/subscription-banner'
import { SkipToMain } from '@/components/skip-to-main'
import { useCurrentTenant, useMe } from '@/features/auth/use-me'

// Rare screens: downloaded only when needed.
const CheckInbox = lazy(() => import('@/features/signup/check-inbox'))
const TwoFactorGate = lazy(() =>
  import('@/features/settings/two-factor-gate').then((m) => ({
    default: m.TwoFactorGate,
  }))
)
const NoRestaurant = lazy(() =>
  import('@/features/errors/no-restaurant').then((m) => ({
    default: m.NoRestaurant,
  }))
)

type AuthenticatedLayoutProps = {
  children?: React.ReactNode
}

export function AuthenticatedLayout({ children }: AuthenticatedLayoutProps) {
  const me = useMe()
  const tenant = useCurrentTenant()
  const defaultOpen = getCookie('sidebar_state') !== 'false'

  // Until the address is confirmed, only "Check your inbox" (owner, 2026-11-13).
  if (!me.email_verified && !me.impersonation) {
    return (
      <Suspense fallback={null}>
        <CheckInbox />
      </Suspense>
    )
  }

  // The Super Admin requires two-factor for Owners and it isn't on yet (M17).
  if (me.two_factor_required) {
    return (
      <Suspense fallback={null}>
        <TwoFactorGate />
      </Suspense>
    )
  }

  if (!tenant) {
    return (
      <Suspense fallback={null}>
        <NoRestaurant />
      </Suspense>
    )
  }

  return (
    <SearchProvider>
      <LayoutProvider>
        <SidebarProvider defaultOpen={defaultOpen}>
          <SkipToMain />
          <AppSidebar />
          <SidebarInset
            className={cn(
              // Set content container, so we can use container queries
              '@container/content',

              // If layout is fixed, set the height
              // to 100svh to prevent overflow
              'has-data-[layout=fixed]:h-svh',

              // If layout is fixed and sidebar is inset,
              // set the height to 100svh - spacing (total margins) to prevent overflow
              'peer-data-[variant=inset]:has-data-[layout=fixed]:h-[calc(100svh-(var(--spacing)*4))]'
            )}
          >
            <SubscriptionBanner tenant={tenant} />
            <PasswordBanner />
            {children ?? <Outlet />}
          </SidebarInset>
        </SidebarProvider>
      </LayoutProvider>
    </SearchProvider>
  )
}
