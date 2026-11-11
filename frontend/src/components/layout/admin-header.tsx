import { lazy, Suspense } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Header } from '@/components/layout/header'
import { LazyConfigDrawer } from '@/components/layout/lazy-config-drawer'
import { LazyLanguageSwitch as LanguageSwitch } from '@/components/lazy-language-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { meQueryOptions } from '@/features/auth/api'

// Rare: only while a Super Admin is "logged in as" a restaurant.
const ViewingAs = lazy(() =>
  import('@/components/layout/viewing-as').then((m) => ({
    default: m.ViewingAs,
  }))
)

/** The top bar shared by every /admin page. */
export function AdminHeader({ fixed }: { fixed?: boolean }) {
  const viewing = useQuery(meQueryOptions).data?.impersonation
  return (
    <Header fixed={fixed}>
      <Search className='me-auto' />
      {viewing && (
        <Suspense fallback={null}>
          <ViewingAs info={viewing} />
        </Suspense>
      )}
      <LanguageSwitch />
      <ThemeSwitch />
      <LazyConfigDrawer />
      <ProfileDropdown />
    </Header>
  )
}
