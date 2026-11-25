import { lazy, Suspense, useEffect } from 'react'
import { Outlet, useRouterState } from '@tanstack/react-router'
import { getCookie } from '@/lib/cookies'
import { cn } from '@/lib/utils'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { PasswordBanner } from '@/components/layout/password-banner'
import { canOpen } from '@/components/layout/role-access'
import { SubscriptionBanner } from '@/components/layout/subscription-banner'
import { SkipToMain } from '@/components/skip-to-main'
import { useGoogleLinkedNotice } from '@/features/auth/google-linked'
import { useCurrentTenant, useMe } from '@/features/auth/use-me'

// Rare screens: downloaded only when needed.
const TwoFactorGate = lazy(() =>
  import('@/features/settings/two-factor-gate').then((m) => ({
    default: m.TwoFactorGate,
  }))
)
const NoAccess = lazy(() =>
  import('@/features/errors/error-page').then((m) => ({
    default: () => (
      <m.ErrorPage code='403' kind='forbidden' className='min-h-0' />
    ),
  }))
)
const NoRestaurant = lazy(() =>
  import('@/features/errors/no-restaurant').then((m) => ({
    default: m.NoRestaurant,
  }))
)
const RestaurantBlocked = lazy(() =>
  import('@/features/errors/restaurant-blocked').then((m) => ({
    default: m.RestaurantBlocked,
  }))
)

type AuthenticatedLayoutProps = {
  children?: React.ReactNode
}

export function AuthenticatedLayout({ children }: AuthenticatedLayoutProps) {
  const me = useMe()
  const tenant = useCurrentTenant()
  const path = useRouterState({ select: (s) => s.location.pathname })
  const defaultOpen = getCookie('sidebar_state') !== 'false'
  useGoogleLinkedNotice()

  // Until the address is confirmed, only "Check your inbox" (owner, 2026-11-13),
  // a server page (QUESTIONS #222). The server sends people there before this
  // page loads; this covers an address changed while the admin is open.
  if (!me.email_verified && !me.impersonation) {
    return <GoTo href='/verify-email' />
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

  // Blocked by the Super Admin: replace the whole admin with a card and the reason (owner, 2026-11-25).
  if (tenant.blocked) {
    return (
      <Suspense fallback={null}>
        <RestaurantBlocked reason={tenant.blocked_reason} />
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
            {canOpen(path, tenant.membership?.role ?? null) ? (
              (children ?? <Outlet />)
            ) : (
              <Suspense fallback={null}>
                <NoAccess />
              </Suspense>
            )}
          </SidebarInset>
        </SidebarProvider>
      </LayoutProvider>
    </SearchProvider>
  )
}

/** A full page load to a server page. */
function GoTo({ href }: { href: string }) {
  useEffect(() => window.location.assign(href), [href])
  return null
}
