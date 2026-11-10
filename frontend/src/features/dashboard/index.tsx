import { useTranslation } from 'react-i18next'
import { ConfigDrawer } from '@/components/config-drawer'
import { LanguageSwitch } from '@/components/language-switch'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'

export function Dashboard() {
  const { t } = useTranslation()

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <LanguageSwitch />
        <ThemeSwitch />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main>
        <h1 className='text-2xl font-bold tracking-tight'>
          {t('dashboard.title')}
        </h1>
        <p className='mt-1 text-muted-foreground'>{t('dashboard.intro')}</p>
      </Main>
    </>
  )
}
