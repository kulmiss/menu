import { Link, Outlet, useLocation } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant } from '@/features/auth/use-me'

/** Personal settings: profile and language, password and two-factor. */
export function Settings() {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const owner = useCurrentTenant()?.membership?.role === 'owner'
  const tabs = [
    { to: '/admin/settings', label: t('nav.profile') },
    { to: '/admin/settings/security', label: t('nav.security') },
    // Plan and invoices: owners only (owner, 2026-11-13).
    ...(owner
      ? [{ to: '/admin/settings/billing', label: t('settings.billing.tab') }]
      : []),
  ] as const

  return (
    <>
      <AdminHeader />
      <Main>
        <h1 className='text-2xl font-bold tracking-tight'>
          {t('settings.title')}
        </h1>
        <p className='mb-4 text-muted-foreground'>{t('settings.intro')}</p>
        <nav
          aria-label={t('settings.title')}
          className='mb-6 flex gap-2 border-b'
        >
          {tabs.map((tab) => (
            <Link
              key={tab.to}
              to={tab.to}
              aria-current={pathname === tab.to ? 'page' : undefined}
              className={cn(
                '-mb-px flex min-h-11 items-center border-b-2 px-3 text-sm font-medium',
                pathname === tab.to
                  ? 'border-primary'
                  : 'border-transparent text-muted-foreground'
              )}
            >
              {tab.label}
            </Link>
          ))}
        </nav>
        <div className='grid gap-8'>
          <Outlet />
        </div>
      </Main>
    </>
  )
}
