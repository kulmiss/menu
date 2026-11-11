import { type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Check, ImagePlus, RefreshCw } from 'lucide-react'
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
import { tr } from '@/features/menu/text'

/**
 * Dashboard widgets (owner, 2026-11-11), downloaded after the page itself.
 * Each widget asks its own endpoint, so they load in parallel; a failing one
 * shows its own error and retry, never breaking the others.
 */
type Names = Record<string, string>
type Setup = {
  steps: { key: string; done: boolean; link: string }[]
  percent: number
  complete: boolean
  hidden: boolean
}
type Day = { date: string; views: number; scans: number }
type Trend = { days: Day[]; today: Day; yesterday: Day }
type Missing = {
  enabled: boolean
  total: number
  items: { id: number; name: Names; views: number }[]
}
type Period = {
  top: { id: number; name: Names; added: number }[]
  lists_shown: number
  average_minor: number | null
}
type Orders =
  | { enabled: false }
  | { enabled: true; currency: string; periods: Record<'7' | '30', Period> }
type Usage = { key: string; used: number; limit: number | null }
type PlanInfo = {
  plan: Names | null
  trial: boolean
  days_left: number | null
  usage: Usage[]
}

const widget = <T,>(t: number, name: string) => ({
  queryKey: ['tenant', t, 'widgets', name],
  queryFn: async () =>
    (await api.get<{ data: T }>(tenantUrl(t, `widgets/${name}`))).data.data,
  staleTime: 60_000,
})

type Props = { tenantId: number; locale: string; manager: boolean }

export default function DashboardWidgets({ tenantId, locale, manager }: Props) {
  return (
    // minmax(0, 1fr): a card never grows past the screen because of its content.
    <div
      className='grid gap-4'
      style={{ gridTemplateColumns: 'minmax(0, 1fr)' }}
    >
      {manager && <SetupWidget tenantId={tenantId} />}
      <TrendWidget tenantId={tenantId} />
      {manager && (
        <div className='grid min-w-0 gap-4 lg:grid-cols-2'>
          <PlanWidget tenantId={tenantId} locale={locale} />
          <OrdersWidget tenantId={tenantId} locale={locale} />
        </div>
      )}
      {manager && <MissingPhotosWidget tenantId={tenantId} locale={locale} />}
    </div>
  )
}

/** Card with the three states every widget shares: loading, error (retry), content. */
function Widget<T>({
  title,
  hint,
  query,
  children,
}: {
  title: string
  hint?: string
  query: {
    data?: T
    isError: boolean
    refetch: () => unknown
    isFetching: boolean
  }
  children: (data: T) => ReactNode
}) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        {hint && <CardDescription>{hint}</CardDescription>}
      </CardHeader>
      <CardContent>
        {query.data !== undefined ? (
          children(query.data)
        ) : query.isError ? (
          <div className='flex flex-wrap items-center gap-3 text-sm'>
            <span className='text-destructive'>{t('dashboard.w.error')}</span>
            <Button
              variant='outline'
              size='sm'
              className='min-h-11 sm:min-h-9'
              disabled={query.isFetching}
              onClick={() => query.refetch()}
            >
              <RefreshCw /> {t('dashboard.w.retry')}
            </Button>
          </div>
        ) : (
          <div className='grid gap-2' aria-busy='true'>
            <Skeleton className='h-5' style={{ width: '66%' }} />
            <Skeleton className='h-5' style={{ width: '50%' }} />
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function SetupWidget({ tenantId }: { tenantId: number }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const query = useQuery(widget<Setup>(tenantId, 'setup'))
  const hide = useMutation({
    mutationFn: () => api.post(tenantUrl(tenantId, 'widgets/setup/hide')),
    onSuccess: () =>
      qc.invalidateQueries({
        queryKey: ['tenant', tenantId, 'widgets', 'setup'],
      }),
  })
  // Shown until everything is done, unless hidden.
  if (query.data && (query.data.complete || query.data.hidden)) return null
  return (
    <Widget
      title={t('dashboard.w.setup')}
      hint={t('dashboard.w.setup_hint')}
      query={query}
    >
      {(s) => (
        <div className='grid gap-3'>
          <div className='flex items-center gap-3'>
            <Bar
              value={s.percent}
              label={t('dashboard.w.setup_done', { percent: s.percent })}
            />
            <span className='text-sm font-medium tabular-nums'>
              {s.percent}%
            </span>
          </div>
          <ul className='grid gap-1 sm:grid-cols-2'>
            {s.steps.map((step) => (
              <li key={step.key}>
                <Link
                  to={step.link}
                  className='flex min-h-11 items-center gap-2 rounded-md px-2 text-sm hover:bg-muted'
                >
                  <span
                    className={
                      step.done
                        ? 'flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground'
                        : 'size-5 rounded-full border-2'
                    }
                  >
                    {step.done && <Check className='size-3.5' aria-hidden />}
                  </span>
                  <span
                    className={
                      step.done ? 'text-muted-foreground line-through' : ''
                    }
                  >
                    {t(`dashboard.w.steps.${step.key}`)}
                  </span>
                  <span className='sr-only'>
                    {step.done ? t('dashboard.w.done') : t('dashboard.w.todo')}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
          <div>
            <Button
              variant='ghost'
              size='sm'
              className='min-h-11 sm:min-h-9'
              disabled={hide.isPending}
              onClick={() => hide.mutate()}
            >
              {t('dashboard.w.hide')}
            </Button>
          </div>
        </div>
      )}
    </Widget>
  )
}

function TrendWidget({ tenantId }: { tenantId: number }) {
  const { t } = useTranslation()
  const query = useQuery(widget<Trend>(tenantId, 'trend'))
  return (
    <Widget title={t('dashboard.w.trend')} query={query}>
      {(d) => (
        <div className='grid grid-cols-2 gap-4'>
          {(['views', 'scans'] as const).map((key) => (
            <div key={key}>
              <p className='text-sm text-muted-foreground'>
                {t(`dashboard.today_${key}`)}
              </p>
              <p className='text-3xl font-bold tabular-nums'>
                {d.today[key].toLocaleString()}
              </p>
              <p className='text-xs text-muted-foreground'>
                {t('dashboard.yesterday', { count: d.yesterday[key] })}
              </p>
            </div>
          ))}
          <div style={{ gridColumn: '1 / -1' }}>
            <MiniBars days={d.days} label={t('dashboard.w.trend_chart')} />
          </div>
        </div>
      )}
    </Widget>
  )
}

/** 7-day views as plain SVG bars (no chart library). */
function MiniBars({ days, label }: { days: Day[]; label: string }) {
  const { t } = useTranslation()
  if (days.every((d) => d.views === 0)) {
    return (
      <p className='text-sm text-muted-foreground'>
        {label}: {t('dashboard.w.no_data')}
      </p>
    )
  }
  const max = Math.max(1, ...days.map((d) => d.views))
  const name = (iso: string) =>
    new Date(iso + 'T12:00:00').toLocaleDateString(undefined, {
      weekday: 'short',
    })
  const summary = days.map((d) => `${name(d.date)} ${d.views}`).join(', ')
  return (
    <figure className='m-0'>
      <svg
        viewBox='0 0 280 90'
        className='h-24 w-full'
        role='img'
        aria-label={`${label}: ${summary}`}
      >
        {days.map((d, i) => {
          const h = Math.round((d.views / max) * 64)
          return (
            <g key={d.date}>
              <rect
                x={i * 40 + 6}
                y={70 - h}
                width='28'
                height={Math.max(h, 1)}
                rx='4'
                fill='var(--primary)'
              />
              <text
                x={i * 40 + 20}
                y='86'
                textAnchor='middle'
                fontSize='11'
                fill='currentColor'
                opacity='0.75'
              >
                {name(d.date)}
              </text>
            </g>
          )
        })}
      </svg>
      <figcaption className='text-xs text-muted-foreground'>{label}</figcaption>
    </figure>
  )
}

function MissingPhotosWidget({
  tenantId,
  locale,
}: {
  tenantId: number
  locale: string
}) {
  const { t } = useTranslation()
  const query = useQuery(widget<Missing>(tenantId, 'missing-photos'))
  // Only when the menu shows photos, and something is missing.
  if (query.data && (!query.data.enabled || query.data.total === 0)) return null
  return (
    <Widget
      title={t('dashboard.w.missing')}
      hint={
        query.data
          ? t('dashboard.w.missing_hint', { count: query.data.total })
          : undefined
      }
      query={query}
    >
      {(m) => (
        <ul className='grid gap-1 sm:grid-cols-2'>
          {m.items.map((i) => (
            <li
              key={i.id}
              className='flex min-h-11 items-center justify-between gap-2 text-sm'
            >
              <span className='min-w-0 truncate'>
                {tr(i.name, locale)}
                <span className='ms-2 text-muted-foreground'>
                  {t('dashboard.w.views_n', { count: i.views })}
                </span>
              </span>
              <Button
                asChild
                variant='outline'
                size='sm'
                className='min-h-11 sm:min-h-9'
              >
                <Link to='/admin/menu' search={{ item: i.id }}>
                  <ImagePlus /> {t('dashboard.w.add_photo')}
                </Link>
              </Button>
            </li>
          ))}
        </ul>
      )}
    </Widget>
  )
}

function OrdersWidget({
  tenantId,
  locale,
}: {
  tenantId: number
  locale: string
}) {
  const { t } = useTranslation()
  const query = useQuery(widget<Orders>(tenantId, 'orders'))
  if (query.data && !query.data.enabled) return null
  return (
    <Widget
      title={t('dashboard.w.orders')}
      hint={t('dashboard.w.orders_hint')}
      query={query}
    >
      {(o) =>
        !o.enabled ? null : (
          <div className='grid gap-4 sm:grid-cols-2'>
            {(['7', '30'] as const).map((days) => {
              const p = o.periods[days]
              return (
                <div key={days} className='grid content-start gap-2'>
                  <p className='text-sm font-medium'>
                    {t('dashboard.w.last_days', { count: Number(days) })}
                  </p>
                  {p.lists_shown === 0 && p.top.length === 0 ? (
                    <p className='text-sm text-muted-foreground'>
                      {t('dashboard.w.no_data')}
                    </p>
                  ) : (
                    <>
                      <p className='text-sm'>
                        {t('dashboard.w.average', {
                          price:
                            p.average_minor === null
                              ? '–'
                              : money(p.average_minor, o.currency),
                          count: p.lists_shown,
                        })}
                      </p>
                      <ol className='grid gap-1 text-sm'>
                        {p.top.map((d) => (
                          <li key={d.id} className='flex justify-between gap-2'>
                            <span className='truncate'>
                              {tr(d.name, locale)}
                            </span>
                            <span className='text-muted-foreground tabular-nums'>
                              × {d.added}
                            </span>
                          </li>
                        ))}
                      </ol>
                    </>
                  )}
                </div>
              )
            })}
          </div>
        )
      }
    </Widget>
  )
}

function PlanWidget({
  tenantId,
  locale,
}: {
  tenantId: number
  locale: string
}) {
  const { t } = useTranslation()
  const query = useQuery(widget<PlanInfo>(tenantId, 'plan'))
  return (
    <Widget title={t('dashboard.w.plan')} query={query}>
      {(p) => (
        <div className='grid gap-3'>
          <p className='text-sm'>
            <span className='font-semibold'>
              {p.plan ? tr(p.plan, locale) : t('dashboard.w.no_plan')}
            </span>
            {p.days_left !== null && (
              <span className='ms-2 text-muted-foreground'>
                {t(
                  p.trial ? 'dashboard.w.trial_left' : 'dashboard.w.days_left',
                  { count: p.days_left }
                )}
              </span>
            )}
          </p>
          {p.usage.map((u) => {
            const pct = u.limit
              ? Math.min(100, Math.round((u.used * 100) / u.limit))
              : 0
            return (
              <div key={u.key} className='grid gap-1'>
                <div className='flex justify-between text-sm'>
                  <span>{t(`dashboard.w.usage.${u.key}`)}</span>
                  <span className='text-muted-foreground tabular-nums'>
                    {u.limit === null
                      ? t('dashboard.w.unlimited', { count: u.used })
                      : `${u.used} / ${u.limit}`}
                  </span>
                </div>
                {u.limit !== null && (
                  <Bar value={pct} label={t(`dashboard.w.usage.${u.key}`)} />
                )}
                {u.limit !== null && pct >= 80 && (
                  <p className='text-xs font-medium'>
                    {t('dashboard.w.near_limit')}
                  </p>
                )}
              </div>
            )
          })}
        </div>
      )}
    </Widget>
  )
}

function Bar({ value, label }: { value: number; label: string }) {
  return (
    <div
      role='progressbar'
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={value}
      className='h-2 flex-1 overflow-hidden rounded-full bg-muted'
    >
      <div
        className='h-full rounded-full bg-primary'
        style={{ width: `${value}%` }}
      />
    </div>
  )
}

function money(minor: number, currency: string) {
  const symbol =
    (
      { ETB: 'Br', USD: '$', EUR: '€', SOS: 'Sh', KES: 'KSh' } as Record<
        string,
        string
      >
    )[currency] ?? currency
  const n = minor / 100
  return `${symbol} ${n.toLocaleString('en-US', { minimumFractionDigits: minor % 100 ? 2 : 0, maximumFractionDigits: 2 })}`
}
