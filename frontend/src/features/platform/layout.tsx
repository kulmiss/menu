import { useQueryClient } from '@tanstack/react-query'
import { Link, Outlet, useLocation, useNavigate } from '@tanstack/react-router'
import { LogOut, Shield } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { ThemeSwitch } from '@/components/theme-switch'
import { signOut } from '@/features/auth/api'
import { useMe } from '@/features/auth/use-me'

const NAV = [
  'restaurants',
  'plans',
  'payments',
  'themes',
  'announcements',
  'activity',
  'settings',
] as const

const NAV_LINK =
  'rounded-md px-3 py-1.5 text-sm whitespace-nowrap text-muted-foreground hover:bg-accent data-[status=active]:bg-accent data-[status=active]:font-medium data-[status=active]:text-foreground'

/** Super Admin area: a slim top bar, its own route tree (PLAN.md §3.2). */
export function PlatformLayout() {
  const { t } = useTranslation()
  const me = useMe()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const path = useLocation({ select: (l) => l.pathname })
  const needs2fa = !me.two_factor_enabled && path !== '/platform/security'

  return (
    <div className='min-h-svh bg-background'>
      <header className='sticky top-0 z-20 border-b bg-background/95 backdrop-blur'>
        <div className='flex items-center gap-3 px-4 py-2'>
          <Shield className='size-5 text-primary' aria-hidden />
          <span className='font-semibold'>{t('platform.title')}</span>
          <span className='hidden text-sm text-muted-foreground sm:inline'>
            {me.email}
          </span>
          <div className='ms-auto flex items-center gap-1'>
            {me.tenants.length > 0 && (
              <Button asChild variant='ghost' size='sm'>
                <Link to='/admin'>{t('platform.to_admin')}</Link>
              </Button>
            )}
            <ThemeSwitch />
            <Button
              variant='ghost'
              size='icon'
              aria-label={t('auth.sign_out')}
              onClick={async () => {
                try {
                  await signOut(qc)
                } finally {
                  await navigate({ to: '/auth/sign-in', replace: true })
                }
              }}
            >
              <LogOut />
            </Button>
          </div>
        </div>
        <nav
          aria-label={t('platform.title')}
          className='flex gap-1 overflow-x-auto px-2 pb-2'
        >
          <Link
            to='/platform'
            activeOptions={{ exact: true }}
            className={NAV_LINK}
          >
            {t('platform.nav.dashboard')}
          </Link>
          {NAV.map((key) => (
            <Link
              key={key}
              to='/platform/$section'
              params={{ section: key }}
              className={NAV_LINK}
            >
              {t(`platform.nav.${key}`)}
            </Link>
          ))}
        </nav>
      </header>
      <main id='content' className='mx-auto max-w-6xl px-4 py-6'>
        {needs2fa ? (
          <div
            role='alert'
            className='mx-auto grid max-w-md gap-3 rounded-md border p-6 text-center'
          >
            <Shield className='mx-auto size-8 text-primary' aria-hidden />
            <p className='font-semibold'>{t('platform.two_factor_title')}</p>
            <p className='text-sm text-muted-foreground'>
              {t('platform.two_factor_text')}
            </p>
            <Button asChild>
              <Link to='/platform/$section' params={{ section: 'security' }}>
                {t('platform.two_factor_setup')}
              </Link>
            </Button>
          </div>
        ) : (
          <Outlet />
        )}
      </main>
    </div>
  )
}

/** Page heading used by every platform screen. */
export function PageTitle({
  title,
  hint,
  children,
}: {
  title: string
  hint?: string
  children?: React.ReactNode
}) {
  return (
    <div className='mb-4 flex flex-wrap items-end justify-between gap-2'>
      <div>
        <h1 className='text-2xl font-bold tracking-tight'>{title}</h1>
        {hint && <p className='text-muted-foreground'>{hint}</p>}
      </div>
      {children && <div className='flex flex-wrap gap-2'>{children}</div>}
    </div>
  )
}
