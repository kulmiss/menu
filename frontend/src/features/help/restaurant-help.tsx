import { lazy, Suspense, useState } from 'react'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { Compass } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant, useFeature } from '@/features/auth/use-me'
import { HelpCentre } from './help-page'

const WelcomeTour = lazy(() => import('./tour'))

/** /admin/help: the restaurant's Help centre (backend QUESTIONS #194). */
export function RestaurantHelpPage() {
  const { t } = useTranslation()
  const tenant = useCurrentTenant()
  const { a } = useSearch({ strict: false }) as { a?: string }
  const navigate = useNavigate()
  const soldOut = useFeature('sold_out')
  const [touring, setTouring] = useState(false)
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
          extra={
            <Button
              variant='outline'
              className='min-h-11'
              onClick={() => setTouring(true)}
            >
              <Compass />
              {t('tour.open')}
            </Button>
          }
          onOpen={(slug) => {
            void navigate({
              to: '/admin/help',
              search: slug ? { a: slug } : {},
            })
            window.scrollTo({ top: 0 })
          }}
        />
        {touring && (
          <Suspense fallback={null}>
            <WelcomeTour
              role={tenant.membership?.role ?? null}
              soldOut={soldOut}
              onClose={() => setTouring(false)}
            />
          </Suspense>
        )}
      </Main>
    </>
  )
}
