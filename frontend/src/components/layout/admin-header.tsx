import { Header } from '@/components/layout/header'
import { LazyConfigDrawer } from '@/components/layout/lazy-config-drawer'
import { LazyLanguageSwitch as LanguageSwitch } from '@/components/lazy-language-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'

/** The top bar shared by every /admin page. */
export function AdminHeader({ fixed }: { fixed?: boolean }) {
  return (
    <Header fixed={fixed}>
      <Search className='me-auto' />
      <LanguageSwitch />
      <ThemeSwitch />
      <LazyConfigDrawer />
      <ProfileDropdown />
    </Header>
  )
}
