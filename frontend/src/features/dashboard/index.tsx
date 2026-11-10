import { useTranslation } from 'react-i18next'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'

export function Dashboard() {
  const { t } = useTranslation()

  return (
    <>
      <AdminHeader />

      <Main>
        <h1 className='text-2xl font-bold tracking-tight'>
          {t('dashboard.title')}
        </h1>
        <p className='mt-1 text-muted-foreground'>{t('dashboard.intro')}</p>
      </Main>
    </>
  )
}
