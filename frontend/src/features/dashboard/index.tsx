import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { BarChart3, ExternalLink, QrCode } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant } from '@/features/auth/use-me'
import { dashboardQuery } from '@/features/insights/api'
import { tr } from '@/features/menu/text'

/** Dashboard (PLAN.md screen 1): today at a glance and what is sold out. */
export function Dashboard() {
  const { t } = useTranslation()
  const tenant = useCurrentTenant()
  const tenantId = tenant?.id ?? 0
  const role = tenant?.membership?.role
  const manager = role === 'owner' || role === 'manager'
  const data = useQuery({ ...dashboardQuery(tenantId), enabled: !!tenant })
  const qc = useQueryClient()
  // Own small call (not the menu editor's module) to keep the first page light.
  const soldOut = useMutation({
    mutationFn: (id: number) =>
      api.patch(tenantUrl(tenantId, `items/${id}/sold-out`), {
        is_sold_out: false,
      }),
    onSettled: () => qc.invalidateQueries({ queryKey: ['tenant', tenantId] }),
  })
  const d = data.data
  const locale = tenant?.default_locale ?? 'en'

  const stat = (key: 'views' | 'scans') => (
    <Card className='py-4'>
      <CardContent>
        <p className='text-sm text-muted-foreground'>
          {t(`dashboard.today_${key}`)}
        </p>
        {d ? (
          <>
            <p className='text-3xl font-bold tabular-nums'>
              {d.today[key].toLocaleString()}
            </p>
            <p className='text-xs text-muted-foreground'>
              {t('dashboard.yesterday', { count: d.yesterday[key] })}
            </p>
          </>
        ) : (
          <Skeleton className='mt-1 h-12 w-24' />
        )}
      </CardContent>
    </Card>
  )

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('dashboard.title')}
            </h1>
            <p className='mt-1 text-muted-foreground'>{t('dashboard.intro')}</p>
          </div>
          {tenant && (
            <Button asChild variant='outline'>
              <a href={`/${tenant.slug}`} target='_blank' rel='noreferrer'>
                <ExternalLink /> {t('dashboard.view_menu')}
              </a>
            </Button>
          )}
        </div>
        <div className='grid gap-4 sm:grid-cols-2'>
          {stat('views')}
          {stat('scans')}
        </div>
        <Card className='mt-4'>
          <CardHeader>
            <CardTitle>{t('dashboard.sold_out')}</CardTitle>
            <CardDescription>{t('dashboard.sold_out_hint')}</CardDescription>
          </CardHeader>
          <CardContent>
            {!d ? (
              <Skeleton className='h-16' />
            ) : d.sold_out.length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('dashboard.nothing_sold_out')}
              </p>
            ) : (
              <ul className='divide-y rounded-md border'>
                {d.sold_out.map((i) => (
                  <li
                    key={i.id}
                    className='flex items-center justify-between gap-2 px-3 py-1.5 text-sm'
                  >
                    <span>{tr(i.name, locale)}</span>
                    <Button
                      size='sm'
                      variant='outline'
                      disabled={
                        soldOut.isPending || !tenant?.subscription.allows_writes
                      }
                      onClick={() => soldOut.mutate(i.id)}
                    >
                      {t('dashboard.available_again', {
                        name: tr(i.name, locale),
                      })}
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
        {manager && (
          <div className='mt-4 flex flex-wrap gap-2'>
            <Button asChild variant='secondary'>
              <Link to='/admin/insights'>
                <BarChart3 /> {t('dashboard.open_insights')}
              </Link>
            </Button>
            <Button asChild variant='secondary'>
              <Link to='/admin/qr-codes'>
                <QrCode /> {t('dashboard.open_qr')}
              </Link>
            </Button>
          </div>
        )}
      </Main>
    </>
  )
}
