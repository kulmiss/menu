import { Outlet } from '@tanstack/react-router'
import { Palette, Wrench, UserCog } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Separator } from '@/components/ui/separator'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { SidebarNav } from './components/sidebar-nav'

const sidebarNavItems = [
  {
    title: 'nav.profile',
    href: '/admin/settings',
    icon: <UserCog size={18} />,
  },
  {
    title: 'nav.account',
    href: '/admin/settings/account',
    icon: <Wrench size={18} />,
  },
  {
    title: 'nav.appearance',
    href: '/admin/settings/appearance',
    icon: <Palette size={18} />,
  },
]

export function Settings() {
  const { t } = useTranslation()
  return (
    <>
      <AdminHeader />

      <Main fixed>
        <div className='space-y-0.5'>
          <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>
            {t('settings.title')}
          </h1>
          <p className='text-muted-foreground'>{t('settings.intro')}</p>
        </div>
        <Separator className='my-4 lg:my-6' />
        <div className='flex flex-1 flex-col space-y-2 overflow-hidden md:space-y-2 lg:flex-row lg:space-y-0 lg:space-x-12'>
          <aside className='top-0 lg:sticky lg:w-1/5'>
            <SidebarNav
              items={sidebarNavItems.map((i) => ({ ...i, title: t(i.title) }))}
            />
          </aside>
          <div className='flex w-full overflow-y-hidden p-1'>
            <Outlet />
          </div>
        </div>
      </Main>
    </>
  )
}
