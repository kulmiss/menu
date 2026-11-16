import { useNavigate, useSearch } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant } from '@/features/auth/use-me'
import { HelpCentre } from './help-page'

/** /admin/help: the restaurant's Help centre (backend QUESTIONS #194). */
export function RestaurantHelpPage({ extra }: { extra?: React.ReactNode }) {
  const { t } = useTranslation()
  const tenant = useCurrentTenant()
  const { a } = useSearch({ strict: false }) as { a?: string }
  const navigate = useNavigate()
  if (!tenant) return null
  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-6'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('help.title')}
          </h1>
          <p className='text-muted-foreground'>{t('help.intro')}</p>
        </div>
        <HelpCentre
          source={{ audience: 'restaurant', tenantId: tenant.id }}
          slug={a}
          extra={extra}
          onOpen={(slug) => {
            void navigate({
              to: '/admin/help',
              search: slug ? { a: slug } : {},
            })
            window.scrollTo({ top: 0 })
          }}
        />
      </Main>
    </>
  )
}
