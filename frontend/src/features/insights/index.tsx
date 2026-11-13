import { lazy, Suspense, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { branchesQuery } from '@/features/branches/api'
import { tr } from '@/features/menu/text'
import { useMenuContext } from '@/features/menu/use-menu-context'
import { insightsQuery } from './api'
import { ExportMenu, MonthlyReportCard } from './export'

const ViewsChart = lazy(() => import('./views-chart'))

/** Insights (PLAN.md screen 12): views and scans per day, top dishes, scans per code. */
export function InsightsPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const [days, setDays] = useState(30)
  const [branch, setBranch] = useState('all')
  const branches = useQuery(branchesQuery(ctx.tenantId))
  const data = useQuery(
    insightsQuery(ctx.tenantId, days, branch === 'all' ? null : Number(branch))
  )
  const d = data.data
  const max = Math.max(1, ...(d?.top_items.map((i) => i.count) ?? []))

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 flex flex-wrap items-end justify-between gap-3'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('insights.title')}
            </h1>
            <p className='text-muted-foreground'>{t('insights.description')}</p>
          </div>
          <div className='flex flex-wrap gap-2'>
            {(branches.data?.length ?? 0) > 1 && (
              <Select value={branch} onValueChange={setBranch}>
                <SelectTrigger
                  className='w-44'
                  aria-label={t('insights.branch')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('insights.all_branches')}
                  </SelectItem>
                  {branches.data?.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      {tr(b.name, ctx.locale)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            <Select
              value={String(days)}
              onValueChange={(v) => setDays(Number(v))}
            >
              <SelectTrigger className='w-40' aria-label={t('insights.range')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[7, 30, 90].map((n) => (
                  <SelectItem key={n} value={String(n)}>
                    {t('insights.last_days', { count: n })}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <ExportMenu
              tenantId={ctx.tenantId}
              days={days}
              branch={branch === 'all' ? null : Number(branch)}
            />
          </div>
        </div>

        <div className='grid gap-4 sm:grid-cols-3'>
          {(['views', 'scans', 'item_views'] as const).map((k) => (
            <Card key={k} className='py-4'>
              <CardContent>
                <p className='text-sm text-muted-foreground'>
                  {t(`insights.total_${k}`)}
                </p>
                <p className='text-3xl font-bold tabular-nums'>
                  {d ? (
                    d.totals[k].toLocaleString()
                  ) : (
                    <Skeleton className='mt-1 h-9 w-20' />
                  )}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>

        <Card className='mt-4'>
          <CardHeader>
            <CardTitle>{t('insights.per_day')}</CardTitle>
            <CardDescription>{t('insights.per_day_hint')}</CardDescription>
          </CardHeader>
          <CardContent>
            {d ? (
              <Suspense fallback={<Skeleton className='h-64' />}>
                <ViewsChart days={d.days} />
              </Suspense>
            ) : (
              <Skeleton className='h-64' />
            )}
          </CardContent>
        </Card>

        <div className='mt-4 grid gap-4 lg:grid-cols-2'>
          <Card>
            <CardHeader>
              <CardTitle>{t('insights.top_items')}</CardTitle>
              <CardDescription>{t('insights.top_items_hint')}</CardDescription>
            </CardHeader>
            <CardContent>
              {d?.top_items.length ? (
                <ol className='grid gap-2'>
                  {d.top_items.map((i) => (
                    <li key={i.id} className='grid gap-1'>
                      <div className='flex justify-between gap-2 text-sm'>
                        <span className='truncate'>
                          {i.name ?? t('insights.deleted_item')}
                        </span>
                        <span className='text-muted-foreground tabular-nums'>
                          {i.count}
                        </span>
                      </div>
                      <div className='h-1.5 rounded bg-muted'>
                        <div
                          className='h-1.5 rounded bg-primary'
                          style={{ width: `${(i.count / max) * 100}%` }}
                        />
                      </div>
                    </li>
                  ))}
                </ol>
              ) : (
                <p className='text-sm text-muted-foreground'>
                  {d ? t('insights.nothing_yet') : ''}
                </p>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t('insights.scans_per_code')}</CardTitle>
              <CardDescription>{t('insights.scans_hint')}</CardDescription>
            </CardHeader>
            <CardContent>
              {d?.scans.length ? (
                <table className='w-full text-sm'>
                  <tbody>
                    {d.scans.map((s) => (
                      <tr key={s.qr_id} className='border-b last:border-0'>
                        <td className='py-1.5'>
                          {s.label ?? t('insights.deleted_code')}
                          {s.deleted && s.label && (
                            <span className='text-muted-foreground'>
                              {' '}
                              ({t('insights.deleted')})
                            </span>
                          )}
                        </td>
                        <td className='py-1.5 font-mono text-xs text-muted-foreground'>
                          {s.code}
                        </td>
                        <td className='py-1.5 text-end tabular-nums'>
                          {s.count}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className='text-sm text-muted-foreground'>
                  {d ? t('insights.nothing_yet') : ''}
                </p>
              )}
            </CardContent>
          </Card>
        </div>
        <MonthlyReportCard tenantId={ctx.tenantId} canEdit={ctx.canWrite} />
        <p className='mt-4 text-xs text-muted-foreground'>
          {t('insights.privacy')}
        </p>
      </Main>
    </>
  )
}
