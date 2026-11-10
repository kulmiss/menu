import { lazy, Suspense } from 'react'
import { Outlet } from '@tanstack/react-router'
import { getCookie } from '@/lib/cookies'
import { cn } from '@/lib/utils'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { SubscriptionBanner } from '@/components/layout/subscription-banner'
import { SkipToMain } from '@/components/skip-to-main'
import { useCurrentTenant, useMe } from '@/features/auth/use-me'
import { VerifyEmailBanner } from '@/features/auth/verify-email'

// Rare: only while a Super Admin is "logged in as" someone.
const ImpersonationBanner = lazy(() =>
  import('@/components/layout/impersonation-banner').then((m) => ({
    default: m.ImpersonationBanner,
  }))
)

// Rare screen: downloaded only when needed.
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
            {me.impersonation && (
              <Suspense fallback={null}>
                <ImpersonationBanner
                  userName={me.impersonation.user_name}
                  endsAt={me.impersonation.ends_at}
                />
              </Suspense>
            )}
            {!me.email_verified && <VerifyEmailBanner email={me.email} />}
            <SubscriptionBanner tenant={tenant} />
            {children ?? <Outlet />}
          </SidebarInset>
        </SidebarProvider>
      </LayoutProvider>
    </SearchProvider>
  )
}
