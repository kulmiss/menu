import { type ReactNode, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { ArrowDownRight, ArrowRight, ArrowUpRight, Minus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Bar, Widget } from '@/features/dashboard/widget-card'

/**
 * The Super Admin insights (QUESTIONS #186), downloaded on demand. Every
 * widget asks its own /platform/insights/* endpoint (summary tables only,
 * cached on the server), shows its own skeleton or error, and links to the
 * matching table. Charts are plain SVG, one series each.
 */
export type InsightFilters = {
  days?: number
  from?: string
  to?: string
  plan?: number
}

type Kpi = { value: number; previous: number }
type Overview = {
  from: string
  to: string
  currency: string
  kpis: Record<
    | 'restaurants'
    | 'active'
    | 'new_restaurants'
    | 'paying'
    | 'free'
    | 'mrr_minor'
    | 'revenue_minor'
    | 'menu_views'
    | 'qr_scans',
    Kpi
  >
  series: {
    date: string
    menu_views: number
    qr_scans: number
    new_restaurants: number
    revenue_minor: number
  }[]
}
type Growth = {
  from: string
  to: string
  weeks: { week: string; count: number }[]
  funnel: { key: string; count: number; percent: number }[]
  conversion: { signed_up: number; paid: number; percent: number | null }
  churn: { paying_at_start: number; churned: number; percent: number | null }
  cohorts: {
    month: string
    signed_up: number
    active: number
    paying: number
  }[]
}
type Listed = {
  id: number
  name: string
  slug: string
  score: number
  setup_pct: number
  views_30d: number
  edits_30d: number
  last_edit_at: string | null
}
type Band = 'healthy' | 'at_risk' | 'inactive'
type Engagement = {
  total: number
  edited_7d: number
  edited_30d: number
  active_7d: number
  active_30d: number
  last_edit_at: string | null
  edits_per_week: number
  setup_avg: number
  setup_done: number
  bands: Record<Band, number>
  lists: Record<Band, Listed[]>
}
type Usage = {
  total: number
  features: { key: string; count: number }[]
  styles: { style: string; count: number }[]
  limits: { key: string; count: number }[]
}
type Ranked = { id: number; name: string; total: number }
type Top = {
  currency: string
  windows: { views: string; scans: string; revenue: string }
  views: Ranked[]
  scans: Ranked[]
  growth: Ranked[]
  revenue: Ranked[]
}
type Team = {
  members: number
  owners: number
  managers: number
  staff: number
  with_2fa: number
  percent_2fa: number | null
  owners_percent_2fa: number | null
  active_7d: number
  active_30d: number
}

const insightParams = (f: InsightFilters) => {
  const p = new URLSearchParams()
  if (f.from && f.to) {
    p.set('from', f.from)
    p.set('to', f.to)
  } else p.set('days', String(f.days ?? 30))
  if (f.plan) p.set('plan', String(f.plan))
  return p.toString()
}

const useInsight = <T,>(widget: string, f: InsightFilters) =>
  useQuery({
    queryKey: ['platform', 'insights', widget, insightParams(f)],
    queryFn: async () =>
      (
        await api.get<{ data: T }>(
          `/platform/insights/${widget}?${insightParams(f)}`
        )
      ).data.data,
    staleTime: 60_000,
  })

const money = (minor: number, currency: string) =>
  `${Math.round(minor / 100).toLocaleString()} ${currency}`
const num = (n: number) => n.toLocaleString()

type TableSearch = {
  state?: string
  open?: number
  health?: string
  usage?: string
  plan?: string
  from?: string
  to?: string
  sort?: string
}

/** A link to the restaurants table with the dashboard's plan filter kept. */
function ToTable({
  search,
  f,
  children,
  className,
  label,
}: {
  search: TableSearch
  f: InsightFilters
  children: ReactNode
  className?: string
  label?: string
}) {
  return (
    <Link
      to='/platform/$section'
      params={{ section: 'restaurants' }}
      search={{ ...(f.plan ? { plan: String(f.plan) } : {}), ...search }}
      aria-label={label}
      className={
        className ??
        'flex min-h-11 items-center justify-between gap-2 rounded-md px-2 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
      }
    >
      {children}
    </Link>
  )
}

export default function PlatformInsights({ f }: { f: InsightFilters }) {
  return (
    <div className='grid min-w-0 gap-4'>
      <OverviewWidget f={f} />
      <div className='grid min-w-0 gap-4 lg:grid-cols-2'>
        <GrowthWidget f={f} />
        <HealthBandsWidget f={f} />
        <EngagementWidget f={f} />
        <UsageWidget f={f} />
        <TopWidget f={f} />
        <TeamWidget f={f} />
      </div>
      <CohortWidget f={f} />
    </div>
  )
}

function Trend({ kpi }: { kpi: Kpi }) {
  const { t } = useTranslation()
  if (kpi.previous === 0)
    return (
      <span className='text-xs text-muted-foreground'>
        {kpi.value === 0 ? '' : t('platform.ins.new_vs_none')}
      </span>
    )
  const change = Math.round(((kpi.value - kpi.previous) * 100) / kpi.previous)
  const Icon = change > 0 ? ArrowUpRight : change < 0 ? ArrowDownRight : Minus
  return (
    <span
      className={`inline-flex items-center gap-0.5 text-xs ${change > 0 ? 'text-emerald-700 dark:text-emerald-400' : change < 0 ? 'text-red-700 dark:text-red-400' : 'text-muted-foreground'}`}
    >
      <Icon className='size-3.5' aria-hidden />
      {t('platform.ins.vs_previous', {
        change: `${change > 0 ? '+' : ''}${change}%`,
      })}
    </span>
  )
}

function OverviewWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Overview>('overview', f)
  return (
    <Widget
      title={t('platform.ins.overview')}
      hint={t('platform.ins.overview_hint')}
      query={query}
    >
      {(o) => {
        const range = { from: o.from, to: o.to }
        const tiles: {
          key: keyof Overview['kpis']
          value: string
          search?: TableSearch
          section?: 'payments'
        }[] = [
          {
            key: 'restaurants',
            value: num(o.kpis.restaurants.value),
            search: {},
          },
          {
            key: 'active',
            value: num(o.kpis.active.value),
            search: { health: 'healthy,at_risk' },
          },
          {
            key: 'new_restaurants',
            value: num(o.kpis.new_restaurants.value),
            search: range,
          },
          {
            key: 'paying',
            value: num(o.kpis.paying.value),
            search: { usage: 'paying' },
          },
          {
            key: 'free',
            value: num(o.kpis.free.value),
            search: { usage: 'not_paying' },
          },
          {
            key: 'mrr_minor',
            value: money(o.kpis.mrr_minor.value, o.currency),
            section: 'payments',
          },
          {
            key: 'revenue_minor',
            value: money(o.kpis.revenue_minor.value, o.currency),
            section: 'payments',
          },
          {
            key: 'menu_views',
            value: num(o.kpis.menu_views.value),
            search: { sort: '-views' },
          },
          {
            key: 'qr_scans',
            value: num(o.kpis.qr_scans.value),
            search: { sort: '-scans' },
          },
        ]
        return (
          <div className='grid gap-4'>
            <ul className='grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5'>
              {tiles.map((tile) => {
                const body = (
                  <>
                    <span className='text-sm text-muted-foreground'>
                      {t(`platform.ins.kpi.${tile.key}`)}
                    </span>
                    <span className='text-xl font-bold tabular-nums'>
                      {tile.value}
                    </span>
                    <Trend kpi={o.kpis[tile.key]} />
                  </>
                )
                const cls =
                  'flex min-h-11 flex-col gap-0.5 rounded-lg border p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                return (
                  <li key={tile.key} className='min-w-0'>
                    {tile.section ? (
                      <Link
                        to='/platform/$section'
                        params={{ section: tile.section }}
                        search={tile.key === 'revenue_minor' ? range : {}}
                        className={cls}
                      >
                        {body}
                      </Link>
                    ) : (
                      <ToTable f={f} search={tile.search ?? {}} className={cls}>
                        {body}
                      </ToTable>
                    )}
                  </li>
                )
              })}
            </ul>
            <div className='grid gap-4 md:grid-cols-2'>
              <Columns
                title={t('platform.ins.chart.views')}
                points={o.series.map((s) => ({
                  label: s.date,
                  value: s.menu_views,
                }))}
                format={num}
              />
              <Columns
                title={t('platform.ins.chart.revenue')}
                points={o.series.map((s) => ({
                  label: s.date,
                  value: s.revenue_minor,
                }))}
                format={(v) => money(v, o.currency)}
              />
            </div>
          </div>
        )
      }}
    </Widget>
  )
}

/** One series of columns with a hover/focus readout; the title names it. */
function Columns({
  title,
  points,
  format,
}: {
  title: string
  points: { label: string; value: number }[]
  format: (v: number) => string
}) {
  const { t } = useTranslation()
  const [hover, setHover] = useState<number | null>(null)
  const max = Math.max(1, ...points.map((p) => p.value))
  const width = 320
  const step = width / Math.max(1, points.length)
  const gap = step > 6 ? 2 : 0
  const shown = hover !== null ? points[hover] : null
  const total = points.reduce((a, p) => a + p.value, 0)
  return (
    <figure className='grid gap-1'>
      <figcaption className='flex items-baseline justify-between gap-2 text-sm'>
        <span className='font-medium'>{title}</span>
        <span className='text-muted-foreground tabular-nums' aria-live='polite'>
          {shown
            ? `${shown.label}: ${format(shown.value)}`
            : t('platform.ins.total', { value: format(total) })}
        </span>
      </figcaption>
      {total === 0 ? (
        <p className='text-sm text-muted-foreground'>
          {t('platform.w.no_data')}
        </p>
      ) : (
        <svg
          viewBox={`0 0 ${width} 100`}
          className='h-28 w-full'
          preserveAspectRatio='none'
          role='img'
          aria-label={`${title}: ${t('platform.ins.total', { value: format(total) })}`}
          onMouseLeave={() => setHover(null)}
        >
          <line
            x1='0'
            x2={width}
            y1='99.5'
            y2='99.5'
            stroke='currentColor'
            opacity='0.2'
          />
          {points.map((p, i) => {
            const h = (p.value / max) * 92
            return (
              <g key={p.label} onMouseEnter={() => setHover(i)}>
                <rect
                  x={i * step + gap / 2}
                  y={99 - h}
                  width={Math.max(0.5, step - gap)}
                  height={Math.max(h, p.value > 0 ? 1 : 0)}
                  rx={step > 8 ? 2 : 0}
                  fill='var(--primary)'
                  opacity={hover === null || hover === i ? 1 : 0.45}
                />
                {/* Bigger than the mark, so thin columns are easy to hover. */}
                <rect
                  x={i * step}
                  y='0'
                  width={step}
                  height='100'
                  fill='transparent'
                >
                  <title>{`${p.label}: ${format(p.value)}`}</title>
                </rect>
              </g>
            )
          })}
        </svg>
      )}
    </figure>
  )
}

function GrowthWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Growth>('growth', f)
  return (
    <Widget title={t('platform.ins.growth')} query={query}>
      {(g) => {
        const range = { from: g.from, to: g.to }
        return (
          <div className='grid gap-4'>
            <Columns
              title={t('platform.ins.signups_week')}
              points={g.weeks.map((w) => ({ label: w.week, value: w.count }))}
              format={num}
            />
            <div className='grid gap-1'>
              <p className='text-sm font-medium'>{t('platform.ins.funnel')}</p>
              {g.funnel.map((s) => (
                <ToTable
                  key={s.key}
                  f={f}
                  search={
                    s.key === 'paid' ? { ...range, usage: 'paying' } : range
                  }
                >
                  <span className='w-36 shrink-0 text-sm'>
                    {t(`platform.ins.step.${s.key}`)}
                  </span>
                  <Bar
                    value={s.percent}
                    label={t(`platform.ins.step.${s.key}`)}
                  />
                  <span className='w-20 shrink-0 text-end text-sm tabular-nums'>
                    {num(s.count)}{' '}
                    <span className='text-muted-foreground'>{s.percent}%</span>
                  </span>
                </ToTable>
              ))}
            </div>
            <dl className='grid grid-cols-2 gap-2 text-sm'>
              <ToTable
                f={f}
                search={{ ...range, usage: 'paying' }}
                className='rounded-lg border p-3 hover:bg-accent'
              >
                <div>
                  <dt className='text-muted-foreground'>
                    {t('platform.ins.conversion')}
                  </dt>
                  <dd className='text-lg font-bold tabular-nums'>
                    {g.conversion.percent === null
                      ? '–'
                      : `${g.conversion.percent}%`}
                  </dd>
                  <dd className='text-xs text-muted-foreground'>
                    {t('platform.ins.conversion_hint', {
                      paid: g.conversion.paid,
                      total: g.conversion.signed_up,
                    })}
                  </dd>
                </div>
              </ToTable>
              <ToTable
                f={f}
                search={{ state: 'read_only,unavailable' }}
                className='rounded-lg border p-3 hover:bg-accent'
              >
                <div>
                  <dt className='text-muted-foreground'>
                    {t('platform.ins.churn')}
                  </dt>
                  <dd className='text-lg font-bold tabular-nums'>
                    {g.churn.percent === null ? '–' : `${g.churn.percent}%`}
                  </dd>
                  <dd className='text-xs text-muted-foreground'>
                    {t('platform.ins.churn_hint', {
                      churned: g.churn.churned,
                      total: g.churn.paying_at_start,
                    })}
                  </dd>
                </div>
              </ToTable>
            </dl>
          </div>
        )
      }}
    </Widget>
  )
}

function CohortWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Growth>('growth', f)
  const monthName = (m: string) =>
    new Date(m + '-15').toLocaleDateString(undefined, {
      month: 'short',
      year: 'numeric',
    })
  const end = (m: string) => {
    const [y, mo] = m.split('-').map(Number)
    const d = new Date(Date.UTC(y, mo, 0))
    return d.toISOString().slice(0, 10)
  }
  return (
    <Widget
      title={t('platform.ins.cohorts')}
      hint={t('platform.ins.cohorts_hint')}
      query={query}
    >
      {(g) =>
        g.cohorts.length === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('platform.w.no_data')}
          </p>
        ) : (
          <div className='overflow-x-auto'>
            <table className='w-full text-sm'>
              <thead>
                <tr className='text-start text-muted-foreground'>
                  <th className='py-1 text-start font-normal'>
                    {t('platform.ins.month')}
                  </th>
                  <th className='py-1 text-end font-normal'>
                    {t('platform.ins.step.signed_up')}
                  </th>
                  <th className='py-1 text-end font-normal'>
                    {t('platform.ins.still_active')}
                  </th>
                  <th className='py-1 text-end font-normal'>
                    {t('platform.ins.paying_now')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {g.cohorts.map((c) => {
                  const pct = (n: number) =>
                    c.signed_up ? Math.round((n * 100) / c.signed_up) : 0
                  return (
                    <tr key={c.month} className='border-t'>
                      <td className='py-0'>
                        <ToTable
                          f={f}
                          search={{ from: `${c.month}-01`, to: end(c.month) }}
                          className='flex min-h-11 items-center underline-offset-4 hover:underline'
                        >
                          {monthName(c.month)}
                        </ToTable>
                      </td>
                      <td className='text-end tabular-nums'>{c.signed_up}</td>
                      <td className='text-end tabular-nums'>
                        {c.active}{' '}
                        <span className='text-muted-foreground'>
                          {pct(c.active)}%
                        </span>
                      </td>
                      <td className='text-end tabular-nums'>
                        {c.paying}{' '}
                        <span className='text-muted-foreground'>
                          {pct(c.paying)}%
                        </span>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )
      }
    </Widget>
  )
}

const BANDS: Band[] = ['healthy', 'at_risk', 'inactive']
const BAND_FILL: Record<Band, string> = {
  healthy: 'bg-emerald-600 dark:bg-emerald-500',
  at_risk: 'bg-amber-500 dark:bg-amber-400',
  inactive: 'bg-muted-foreground/50',
}

function HealthBandsWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Engagement>('engagement', f)
  const [band, setBand] = useState<Band>('at_risk')
  return (
    <Widget
      title={t('platform.ins.health')}
      hint={t('platform.ins.health_hint')}
      query={query}
    >
      {(e) => {
        const total = Math.max(1, e.total)
        return (
          <div className='grid gap-3'>
            <div
              className='flex h-3 gap-0.5 overflow-hidden rounded-full'
              role='img'
              aria-label={BANDS.map(
                (b) => `${t(`platform.ins.band.${b}`)} ${e.bands[b]}`
              ).join(', ')}
            >
              {BANDS.map((b) =>
                e.bands[b] > 0 ? (
                  <div
                    key={b}
                    className={BAND_FILL[b]}
                    style={{ width: `${(e.bands[b] * 100) / total}%` }}
                  />
                ) : null
              )}
            </div>
            <div className='flex flex-wrap gap-2' role='group'>
              {BANDS.map((b) => (
                <Button
                  key={b}
                  size='sm'
                  variant={band === b ? 'default' : 'outline'}
                  aria-pressed={band === b}
                  className='min-h-11 sm:min-h-9'
                  onClick={() => setBand(b)}
                >
                  <span
                    className={`size-2.5 rounded-full ${BAND_FILL[b]}`}
                    aria-hidden
                  />
                  {t(`platform.ins.band.${b}`)}{' '}
                  <span className='tabular-nums'>{e.bands[b]}</span>
                </Button>
              ))}
            </div>
            {e.lists[band].length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.ins.none_in_band')}
              </p>
            ) : (
              <ul className='grid gap-0.5 text-sm'>
                {e.lists[band].map((r) => (
                  <li key={r.id}>
                    <ToTable f={f} search={{ open: r.id }}>
                      <span className='min-w-0 truncate'>{r.name}</span>
                      <span className='shrink-0 text-xs text-muted-foreground tabular-nums'>
                        {t('platform.ins.band_row', {
                          score: r.score,
                          setup: r.setup_pct,
                          views: num(r.views_30d),
                          edits: r.edits_30d,
                        })}
                      </span>
                    </ToTable>
                  </li>
                ))}
              </ul>
            )}
            <ToTable
              f={f}
              search={{
                health: band,
                sort: band === 'healthy' ? '-health' : 'health',
              }}
            >
              <span className='text-sm font-medium'>
                {t('platform.ins.see_all')}
              </span>
              <ArrowRight className='size-4' aria-hidden />
            </ToTable>
          </div>
        )
      }}
    </Widget>
  )
}

function EngagementWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Engagement>('engagement', f)
  return (
    <Widget
      title={t('platform.ins.engagement')}
      hint={t('platform.ins.engagement_hint')}
      query={query}
    >
      {(e) => (
        <div className='grid gap-3'>
          <dl className='grid grid-cols-2 gap-2 text-sm'>
            {(
              [
                ['edited_7d', e.edited_7d],
                ['edited_30d', e.edited_30d],
                ['active_7d', e.active_7d],
                ['active_30d', e.active_30d],
                ['edits_per_week', e.edits_per_week],
                ['setup_done', e.setup_done],
              ] as const
            ).map(([k, v]) => (
              <div key={k} className='rounded-lg border p-3'>
                <dt className='text-muted-foreground'>
                  {t(`platform.ins.e.${k}`)}
                </dt>
                <dd className='text-lg font-bold tabular-nums'>{num(v)}</dd>
              </div>
            ))}
          </dl>
          <div className='flex items-center gap-3 text-sm'>
            <span className='w-36 shrink-0'>
              {t('platform.ins.e.setup_avg')}
            </span>
            <Bar value={e.setup_avg} label={t('platform.ins.e.setup_avg')} />
            <span className='w-10 text-end tabular-nums'>{e.setup_avg}%</span>
          </div>
          <p className='text-xs text-muted-foreground'>
            {t('platform.ins.e.last_edit', {
              date: e.last_edit_at
                ? new Date(e.last_edit_at).toLocaleString()
                : '–',
            })}
          </p>
          <Link
            to='/platform/$section'
            params={{ section: 'activity' }}
            className='flex min-h-11 items-center justify-between rounded-md px-2 text-sm font-medium hover:bg-accent'
          >
            {t('platform.ins.open_activity')}
            <ArrowRight className='size-4' aria-hidden />
          </Link>
        </div>
      )}
    </Widget>
  )
}

function UsageWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Usage>('usage', f)
  return (
    <Widget
      title={t('platform.ins.usage')}
      hint={t('platform.ins.usage_hint')}
      query={query}
    >
      {(u) => {
        const pct = (n: number) =>
          u.total ? Math.round((n * 100) / u.total) : 0
        const row = (key: string, label: string, count: number) => (
          <li key={key}>
            <ToTable f={f} search={{ usage: key }}>
              <span className='w-36 shrink-0 text-sm'>{label}</span>
              <Bar value={pct(count)} label={label} />
              <span className='w-20 shrink-0 text-end text-sm tabular-nums'>
                {num(count)}{' '}
                <span className='text-muted-foreground'>{pct(count)}%</span>
              </span>
            </ToTable>
          </li>
        )
        return (
          <div className='grid gap-3'>
            <ul className='grid gap-0.5'>
              {u.features.map((x) =>
                row(x.key, t(`platform.ins.feature.${x.key}`), x.count)
              )}
            </ul>
            <p className='text-sm font-medium'>{t('platform.ins.styles')}</p>
            <ul className='grid gap-0.5'>
              {u.styles.map((s) =>
                row(
                  `style:${s.style}`,
                  t(`platform.ins.style.${s.style}`, { defaultValue: s.style }),
                  s.count
                )
              )}
            </ul>
            <p className='text-sm font-medium'>{t('platform.ins.limits')}</p>
            <ul className='grid gap-0.5'>
              {u.limits.map((l) =>
                row(l.key, t(`platform.ins.limit.${l.key}`), l.count)
              )}
            </ul>
          </div>
        )
      }}
    </Widget>
  )
}

const TOP_TABS = ['views', 'scans', 'growth', 'revenue'] as const

function TopWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Top>('top', f)
  const [tab, setTab] = useState<(typeof TOP_TABS)[number]>('views')
  return (
    <Widget title={t('platform.ins.top')} query={query}>
      {(d) => {
        const windowOf =
          tab === 'growth'
            ? '30d'
            : d.windows[
                tab === 'views'
                  ? 'views'
                  : tab === 'scans'
                    ? 'scans'
                    : 'revenue'
              ]
        return (
          <div className='grid gap-3'>
            <div className='flex flex-wrap gap-2' role='group'>
              {TOP_TABS.map((k) => (
                <Button
                  key={k}
                  size='sm'
                  variant={tab === k ? 'default' : 'outline'}
                  aria-pressed={tab === k}
                  className='min-h-11 sm:min-h-9'
                  onClick={() => setTab(k)}
                >
                  {t(`platform.ins.top_tab.${k}`)}
                </Button>
              ))}
            </div>
            <p className='text-xs text-muted-foreground'>
              {t(`platform.ins.window.${windowOf}`)}
            </p>
            {d[tab].length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.w.no_data')}
              </p>
            ) : (
              <ol className='grid gap-0.5 text-sm'>
                {d[tab].map((r, i) => (
                  <li key={r.id}>
                    <ToTable f={f} search={{ open: r.id }}>
                      <span className='min-w-0 truncate'>
                        <span className='me-2 text-muted-foreground tabular-nums'>
                          {i + 1}
                        </span>
                        {r.name}
                      </span>
                      <span className='shrink-0 tabular-nums'>
                        {tab === 'revenue'
                          ? money(r.total, d.currency)
                          : `${tab === 'growth' ? '+' : ''}${num(r.total)}`}
                      </span>
                    </ToTable>
                  </li>
                ))}
              </ol>
            )}
            {(tab === 'views' || tab === 'scans') && (
              <ToTable f={f} search={{ sort: `-${tab}` }}>
                <span className='text-sm font-medium'>
                  {t('platform.ins.see_all')}
                </span>
                <ArrowRight className='size-4' aria-hidden />
              </ToTable>
            )}
          </div>
        )
      }}
    </Widget>
  )
}

function TeamWidget({ f }: { f: InsightFilters }) {
  const { t } = useTranslation()
  const query = useInsight<Team>('admins', f)
  return (
    <Widget
      title={t('platform.ins.team')}
      hint={t('platform.ins.team_hint')}
      query={query}
    >
      {(d) => (
        <div className='grid gap-3'>
          <dl className='grid grid-cols-2 gap-2 text-sm'>
            {(
              [
                ['members', num(d.members)],
                ['roles', `${d.owners} · ${d.managers} · ${d.staff}`],
                ['active_7d', num(d.active_7d)],
                ['active_30d', num(d.active_30d)],
              ] as const
            ).map(([k, v]) => (
              <div key={k} className='rounded-lg border p-3'>
                <dt className='text-muted-foreground'>
                  {t(`platform.ins.t.${k}`)}
                </dt>
                <dd className='text-lg font-bold tabular-nums'>{v}</dd>
              </div>
            ))}
          </dl>
          {(
            [
              ['percent_2fa', d.percent_2fa],
              ['owners_percent_2fa', d.owners_percent_2fa],
            ] as const
          ).map(([k, v]) => (
            <div key={k} className='flex items-center gap-3 text-sm'>
              <span className='w-36 shrink-0'>{t(`platform.ins.t.${k}`)}</span>
              <Bar value={v ?? 0} label={t(`platform.ins.t.${k}`)} />
              <span className='w-10 text-end tabular-nums'>
                {v === null ? '–' : `${v}%`}
              </span>
            </div>
          ))}
          <Link
            to='/platform/$section'
            params={{ section: 'activity' }}
            className='flex min-h-11 items-center justify-between rounded-md px-2 text-sm font-medium hover:bg-accent'
          >
            {t('platform.ins.open_activity')}
            <ArrowRight className='size-4' aria-hidden />
          </Link>
        </div>
      )}
    </Widget>
  )
}
