import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { dashboardQ, money, STATES } from './api'
import { PageTitle } from './layout'

export function PlatformDashboard() {
  const { t } = useTranslation()
  const { data: d } = useQuery(dashboardQ())
  const stat = (label: string, value?: number | string) => (
    <Card className='py-4'>
      <CardContent>
        <p className='text-sm text-muted-foreground'>{label}</p>
        {value === undefined ? (
          <Skeleton className='mt-1 h-8 w-16' />
        ) : (
          <p className='text-2xl font-bold tabular-nums'>{value}</p>
        )}
      </CardContent>
    </Card>
  )

  return (
    <>
      <PageTitle title={t('platform.nav.dashboard')} />
      <div className='grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
        {stat(t('platform.dash.restaurants'), d?.restaurants)}
        {stat(t('platform.dash.signups'), d?.signups_30d)}
        {stat(t('platform.dash.views'), d?.views_30d.toLocaleString())}
        {stat(t('platform.dash.scans'), d?.scans_30d.toLocaleString())}
      </div>
      <div className='mt-4 grid gap-4 lg:grid-cols-3'>
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.dash.by_state')}</CardTitle>
          </CardHeader>
          <CardContent className='grid gap-1 text-sm'>
            {STATES.map((s) => (
              <Link
                key={s}
                to='/platform/$section'
                params={{ section: 'restaurants' }}
                search={{ state: s }}
                className='flex justify-between rounded px-1 hover:bg-accent'
              >
                <span>{t(`platform.states.${s}`)}</span>
                <span className='tabular-nums'>{d?.states[s] ?? '…'}</span>
              </Link>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.dash.per_plan')}</CardTitle>
          </CardHeader>
          <CardContent className='grid gap-1 text-sm'>
            {Object.entries(d?.per_plan ?? {}).map(([plan, n]) => (
              <div key={plan} className='flex justify-between'>
                <span>{plan}</span>
                <span className='tabular-nums'>{n}</span>
              </div>
            ))}
            <p className='mt-2 text-muted-foreground'>
              {t('platform.dash.revenue')}:{' '}
              {Object.entries(d?.revenue_30d ?? {})
                .map(([c, v]) => money(v, c))
                .join(' · ') || '—'}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.dash.expiring')}</CardTitle>
          </CardHeader>
          <CardContent className='grid gap-1 text-sm'>
            {d?.expiring.length === 0 && (
              <p className='text-muted-foreground'>
                {t('platform.dash.none_expiring')}
              </p>
            )}
            {d?.expiring.map((r) => (
              <Link
                key={r.id}
                to='/platform/$section'
                params={{ section: 'restaurants' }}
                search={{ open: r.id }}
                className='flex justify-between rounded px-1 hover:bg-accent'
              >
                <span className='truncate'>{r.name}</span>
                <span className='text-muted-foreground'>{r.ends_at}</span>
              </Link>
            ))}
          </CardContent>
        </Card>
      </div>
    </>
  )
}
