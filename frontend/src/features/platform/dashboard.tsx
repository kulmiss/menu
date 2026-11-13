import { lazy, Suspense, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useSearch } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { dashboardQ, STATES } from './api'
import type { InsightFilters } from './insights'
import { PageTitle } from './layout'

// Insights and the to-do widgets are downloaded after the page (owner, 2026-11-11 / QUESTIONS #186).
const Insights = lazy(() => import('./insights'))
const Widgets = lazy(() => import('./widgets'))

type Meta = {
  plans: { id: number; name: Record<string, string> }[]
  computed_at: string | null
}

const RANGES = [7, 30, 90, 365] as const

export function PlatformDashboard() {
  const { t } = useTranslation()
  const search = useSearch({ from: '/platform/' })
  const navigate = useNavigate({ from: '/platform/' })
  const f: InsightFilters = search
  const custom = Boolean(f.from && f.to)
  const [picking, setPicking] = useState(custom)
  const [from, setFrom] = useState(f.from ?? '')
  const [to, setTo] = useState(f.to ?? '')
  const meta = useQuery({
    queryKey: ['platform', 'insights', 'meta'],
    queryFn: async () =>
      (await api.get<{ data: Meta }>('/platform/insights')).data.data,
    staleTime: 60_000,
  })
  const set = (next: InsightFilters) =>
    navigate({ search: { ...search, ...next }, replace: true })
  const today = new Date().toISOString().slice(0, 10)

  return (
    <>
      <PageTitle
        title={t('platform.nav.dashboard')}
        hint={t('platform.ins.hint')}
      />
      <section
        aria-label={t('platform.ins.filters')}
        className='mb-4 flex flex-wrap items-end gap-2'
      >
        <div
          className='flex flex-wrap gap-2'
          role='group'
          aria-label={t('platform.ins.range')}
        >
          {RANGES.map((d) => {
            const on = !custom && !picking && (f.days ?? 30) === d
            return (
              <Button
                key={d}
                size='sm'
                variant={on ? 'default' : 'outline'}
                aria-pressed={on}
                className='min-h-11 sm:min-h-9'
                onClick={() => {
                  setPicking(false)
                  set({
                    days: d === 30 ? undefined : d,
                    from: undefined,
                    to: undefined,
                  })
                }}
              >
                {t('platform.ins.last_days', { count: d })}
              </Button>
            )
          })}
          <Button
            size='sm'
            variant={custom || picking ? 'default' : 'outline'}
            aria-pressed={custom || picking}
            className='min-h-11 sm:min-h-9'
            onClick={() => setPicking(true)}
          >
            {t('platform.ins.custom')}
          </Button>
        </div>
        {(picking || custom) && (
          <form
            className='flex flex-wrap items-end gap-2'
            onSubmit={(e) => {
              e.preventDefault()
              if (from && to && from <= to) set({ days: undefined, from, to })
            }}
          >
            <div className='grid gap-1'>
              <Label htmlFor='ins-from'>{t('platform.ins.from')}</Label>
              <Input
                id='ins-from'
                type='date'
                className='min-h-11 sm:min-h-9'
                max={to || today}
                value={from}
                onChange={(e) => setFrom(e.target.value)}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='ins-to'>{t('platform.ins.to')}</Label>
              <Input
                id='ins-to'
                type='date'
                className='min-h-11 sm:min-h-9'
                min={from || undefined}
                max={today}
                value={to}
                onChange={(e) => setTo(e.target.value)}
              />
            </div>
            <Button
              type='submit'
              size='sm'
              className='min-h-11 sm:min-h-9'
              disabled={!from || !to || from > to}
            >
              {t('platform.ins.apply')}
            </Button>
          </form>
        )}
        <div className='grid gap-1'>
          <Label htmlFor='ins-plan'>{t('platform.ins.plan')}</Label>
          <select
            id='ins-plan'
            className='min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9'
            value={f.plan ?? ''}
            onChange={(e) =>
              set({ plan: e.target.value ? Number(e.target.value) : undefined })
            }
          >
            <option value=''>{t('platform.ins.all_plans')}</option>
            {(meta.data?.plans ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name.en ?? Object.values(p.name)[0]}
              </option>
            ))}
          </select>
        </div>
        <p className='ms-auto text-xs text-muted-foreground'>
          {meta.data
            ? meta.data.computed_at
              ? t('platform.ins.updated', {
                  date: new Date(meta.data.computed_at).toLocaleString(),
                })
              : t('platform.ins.not_built')
            : null}
        </p>
      </section>
      <Suspense fallback={<Skeleton className='h-64 w-full' />}>
        <Insights f={f} />
      </Suspense>
      <h2 className='mt-8 mb-2 text-lg font-semibold'>
        {t('platform.ins.to_do')}
      </h2>
      <StatesCard />
      <Suspense fallback={<Skeleton className='mt-4 h-64 w-full' />}>
        <Widgets />
      </Suspense>
    </>
  )
}

/** Restaurants per subscription state, right now; each opens the table. */
function StatesCard() {
  const { t } = useTranslation()
  const { data: d } = useQuery(dashboardQ())
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.dash.by_state')}</CardTitle>
      </CardHeader>
      <CardContent className='grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-4'>
        {STATES.map((s) => (
          <Link
            key={s}
            to='/platform/$section'
            params={{ section: 'restaurants' }}
            search={{ state: s }}
            className='flex min-h-11 items-center justify-between rounded px-2 hover:bg-accent'
          >
            <span>{t(`platform.states.${s}`)}</span>
            <span className='tabular-nums'>{d?.states[s] ?? '…'}</span>
          </Link>
        ))}
      </CardContent>
    </Card>
  )
}
