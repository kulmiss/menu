import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { AlertTriangle, Check, Mail, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, isApiError } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Bar, Widget } from '@/features/dashboard/widget-card'
import { followUpsQ } from './api'
import { TagChip } from './crm'

/**
 * Super Admin dashboard widgets (owner, 2026-11-11), downloaded after the
 * page. Each asks its own /platform/widgets/* endpoint; one failing shows
 * its own error and never breaks the others. Charts are plain SVG.
 */
type Names = Record<string, string> | null
type Revenue = {
  currency: string
  this_month: number
  last_month: number
  mrr: number
  months: { month: string; total: number }[]
}
type Expiring = {
  id: number
  name: string
  slug: string
  kind: 'trial' | 'subscription'
  days_left: number | null
}
type Inactive = {
  id: number
  name: string
  slug: string
  last_activity: string | null
}
type Funnel = {
  days: number
  steps: { key: string; count: number; percent: number | null }[]
}
type Top = {
  views: Ranked[]
  scans: Ranked[]
  plans: { id: number; name: string; plan: Names; price_minor: number }[]
}
type Ranked = { id: number; name: string; total: number }
type Signup = {
  id: number
  name: string
  slug: string
  created_at: string
  percent: number
  steps: Record<string, boolean>
  signup_state?: 'unverified' | 'pending_approval' | null
}
type Health = {
  disk_free: number | null
  disk_total: number | null
  disk_free_percent: number | null
  photos_bytes: number
  photos_count: number
  cron_minutes_ago: number | null
  queued: number
  failed: number
  errors_today: number
  php: string
  laravel: string
  warnings: string[]
}
type PlanMix = {
  plans: { id: number | null; name: Names; total: number }[]
  upgrades: number
  downgrades: number
}

const w = <T,>(name: string) => ({
  queryKey: ['platform', 'widgets', name],
  queryFn: async () =>
    (await api.get<{ data: T }>(`/platform/widgets/${name}`)).data.data,
  staleTime: 60_000,
})
const nameOf = (n: Names) => (n ? (n.en ?? Object.values(n)[0] ?? '') : '')
const money = (minor: number, currency: string) =>
  `${(minor / 100).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${currency}`
const gb = (bytes: number | null) =>
  bytes === null ? '–' : `${(bytes / 1024 ** 3).toFixed(1)} GB`

export default function PlatformWidgets() {
  const [email, setEmail] = useState<{
    id: number
    name: string
    reason: 'expiring' | 'inactive'
    days?: number | null
  } | null>(null)
  return (
    <div
      className='mt-4 grid gap-4'
      style={{ gridTemplateColumns: 'minmax(0, 1fr)' }}
    >
      <HealthWidget />
      <FollowUpsWidget />
      <div className='grid min-w-0 gap-4 lg:grid-cols-2'>
        <RevenueWidget />
        <PlanMixWidget />
        <ExpiringWidget onEmail={setEmail} />
        <InactiveWidget onEmail={setEmail} />
        <FunnelWidget />
        <TopWidget />
      </div>
      <SignupsWidget />
      {email && <EmailDialog target={email} onClose={() => setEmail(null)} />}
    </div>
  )
}

function RevenueWidget() {
  const { t } = useTranslation()
  const query = useQuery(w<Revenue>('revenue'))
  return (
    <Widget title={t('platform.w.revenue')} query={query}>
      {(r) => {
        const change =
          r.last_month > 0
            ? Math.round(((r.this_month - r.last_month) * 100) / r.last_month)
            : null
        const max = Math.max(1, ...r.months.map((m) => m.total))
        const label = (m: string) =>
          new Date(m + '-15').toLocaleDateString(undefined, { month: 'short' })
        return (
          <div className='grid gap-3'>
            <div className='grid grid-cols-3 gap-2 text-sm'>
              <div>
                <p className='text-muted-foreground'>
                  {t('platform.w.this_month')}
                </p>
                <p className='text-lg font-bold tabular-nums'>
                  {money(r.this_month, r.currency)}
                </p>
                {change !== null && (
                  <p className='text-xs text-muted-foreground'>
                    {t('platform.w.vs_last', {
                      change: `${change > 0 ? '+' : ''}${change}%`,
                    })}
                  </p>
                )}
              </div>
              <div>
                <p className='text-muted-foreground'>
                  {t('platform.w.last_month')}
                </p>
                <p className='text-lg font-bold tabular-nums'>
                  {money(r.last_month, r.currency)}
                </p>
              </div>
              <div>
                <p className='text-muted-foreground'>{t('platform.w.mrr')}</p>
                <p className='text-lg font-bold tabular-nums'>
                  {money(r.mrr, r.currency)}
                </p>
              </div>
            </div>
            {r.months.every((m) => m.total === 0) ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.w.no_data')}
              </p>
            ) : (
              <svg
                viewBox='0 0 360 110'
                className='h-28 w-full'
                role='img'
                aria-label={`${t('platform.w.revenue_chart')}: ${r.months.map((m) => `${label(m.month)} ${money(m.total, r.currency)}`).join(', ')}`}
              >
                {r.months.map((m, i) => {
                  const h = Math.round((m.total / max) * 80)
                  return (
                    <g key={m.month}>
                      <rect
                        x={i * 30 + 4}
                        y={90 - h}
                        width='22'
                        height={Math.max(h, 1)}
                        rx='3'
                        fill='var(--primary)'
                      />
                      <text
                        x={i * 30 + 15}
                        y='106'
                        textAnchor='middle'
                        fontSize='10'
                        fill='currentColor'
                        opacity='0.75'
                      >
                        {label(m.month)}
                      </text>
                    </g>
                  )
                })}
              </svg>
            )}
          </div>
        )
      }}
    </Widget>
  )
}

type OnEmail = (t: {
  id: number
  name: string
  reason: 'expiring' | 'inactive'
  days?: number | null
}) => void

function ExpiringWidget({ onEmail }: { onEmail: OnEmail }) {
  const { t } = useTranslation()
  const [days, setDays] = useState<7 | 14>(7)
  const query = useQuery(w<Expiring[]>('expiring'))
  return (
    <Widget title={t('platform.w.expiring')} query={query}>
      {(rows) => {
        const list = rows.filter((r) => (r.days_left ?? 0) <= days)
        return (
          <div className='grid gap-2'>
            <Toggle
              value={days}
              options={[7, 14]}
              label={(d) => t('platform.w.next_days', { count: d })}
              onChange={setDays}
            />
            {list.length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.w.none_expiring')}
              </p>
            ) : (
              <ul className='grid gap-1 text-sm'>
                {list.map((r) => (
                  <li
                    key={r.id}
                    className='flex min-h-11 min-w-0 items-center justify-between gap-2'
                  >
                    <span className='min-w-0 truncate'>
                      {r.name}
                      <span className='ms-2 text-muted-foreground'>
                        {t(`platform.w.ends_${r.kind}`, {
                          count: r.days_left ?? 0,
                        })}
                      </span>
                    </span>
                    <Button
                      variant='outline'
                      size='sm'
                      className='min-h-11 sm:min-h-9'
                      onClick={() =>
                        onEmail({
                          id: r.id,
                          name: r.name,
                          reason: 'expiring',
                          days: r.days_left,
                        })
                      }
                    >
                      <Mail /> {t('platform.w.remind')}
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )
      }}
    </Widget>
  )
}

function InactiveWidget({ onEmail }: { onEmail: OnEmail }) {
  const { t } = useTranslation()
  const query = useQuery(w<Inactive[]>('inactive'))
  return (
    <Widget
      title={t('platform.w.inactive')}
      hint={t('platform.w.inactive_hint')}
      query={query}
    >
      {(rows) =>
        rows.length === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('platform.w.none_inactive')}
          </p>
        ) : (
          <ul className='grid gap-1 text-sm'>
            {rows.map((r) => (
              <li
                key={r.id}
                className='flex min-h-11 min-w-0 items-center justify-between gap-2'
              >
                <span className='min-w-0 truncate'>
                  {r.name}
                  <span className='ms-2 text-muted-foreground'>
                    {t('platform.w.last_activity', {
                      date: r.last_activity ?? '–',
                    })}
                  </span>
                </span>
                <Button
                  variant='outline'
                  size='sm'
                  className='min-h-11 sm:min-h-9'
                  onClick={() =>
                    onEmail({ id: r.id, name: r.name, reason: 'inactive' })
                  }
                >
                  <Mail /> {t('platform.w.email_owner')}
                </Button>
              </li>
            ))}
          </ul>
        )
      }
    </Widget>
  )
}

function FunnelWidget() {
  const { t } = useTranslation()
  const [days, setDays] = useState<30 | 90>(30)
  const query = useQuery(w<Funnel>(`funnel?days=${days}`))
  return (
    <Widget title={t('platform.w.funnel')} query={query}>
      {(f) => {
        const first = Math.max(1, f.steps[0]?.count ?? 0)
        return (
          <div className='grid gap-3'>
            <Toggle
              value={days}
              options={[30, 90]}
              label={(d) => t('platform.w.last_days', { count: d })}
              onChange={setDays}
            />
            {f.steps[0]?.count === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.w.no_data')}
              </p>
            ) : (
              f.steps.map((s) => (
                <div key={s.key} className='grid gap-1'>
                  <div className='flex justify-between text-sm'>
                    <span>{t(`platform.w.funnel_steps.${s.key}`)}</span>
                    <span className='tabular-nums'>
                      {s.count}
                      {s.percent !== null && (
                        <span className='ms-2 text-muted-foreground'>
                          ({s.percent}%)
                        </span>
                      )}
                    </span>
                  </div>
                  <Bar
                    value={Math.round((s.count * 100) / first)}
                    label={t(`platform.w.funnel_steps.${s.key}`)}
                  />
                </div>
              ))
            )}
          </div>
        )
      }}
    </Widget>
  )
}

function TopWidget() {
  const { t } = useTranslation()
  const query = useQuery(w<Top>('top'))
  const list = (
    title: string,
    rows: { id: number; name: string; value: string }[]
  ) => (
    <div className='grid content-start gap-1'>
      <p className='text-sm font-medium'>{title}</p>
      {rows.length === 0 ? (
        <p className='text-sm text-muted-foreground'>
          {t('platform.w.no_data')}
        </p>
      ) : (
        <ol className='grid gap-1 text-sm'>
          {rows.map((r) => (
            <li key={r.id} className='flex min-w-0 justify-between gap-2'>
              <span className='min-w-0 truncate'>{r.name}</span>
              <span className='text-muted-foreground tabular-nums'>
                {r.value}
              </span>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
  return (
    <Widget
      title={t('platform.w.top')}
      hint={t('platform.w.top_hint')}
      query={query}
    >
      {(d) => (
        <div className='grid gap-4 sm:grid-cols-3'>
          {list(
            t('platform.w.most_views'),
            d.views.map((r) => ({ ...r, value: r.total.toLocaleString() }))
          )}
          {list(
            t('platform.w.most_scans'),
            d.scans.map((r) => ({ ...r, value: r.total.toLocaleString() }))
          )}
          {list(
            t('platform.w.biggest_plans'),
            d.plans.map((r) => ({
              id: r.id,
              name: r.name,
              value: nameOf(r.plan),
            }))
          )}
        </div>
      )}
    </Widget>
  )
}

const STEPS = ['logo', 'banner', 'dishes', 'photos', 'hours', 'qr'] as const

/** CRM: follow-ups due today and overdue (owner, 2026-11-13). */
function FollowUpsWidget() {
  const { t } = useTranslation()
  const query = useQuery(followUpsQ())
  if (query.data && query.data.length === 0) return null
  return (
    <Widget title={t('platform.crm.widget')} query={query}>
      {(rows) => (
        <ul className='grid gap-2'>
          {rows.map((r) => (
            <li
              key={r.id}
              className='flex flex-wrap items-center gap-2 text-sm'
            >
              <Link
                to='/platform/$section'
                params={{ section: 'restaurants' }}
                search={{ open: r.id }}
                className='inline-flex min-h-11 items-center font-medium hover:underline sm:min-h-8'
              >
                {r.name}
              </Link>
              <span
                className={
                  r.overdue
                    ? 'font-medium text-destructive'
                    : 'text-muted-foreground'
                }
              >
                {r.overdue
                  ? t('platform.crm.overdue', { date: r.date })
                  : t('platform.crm.today')}
              </span>
              {r.reason && (
                <span className='text-muted-foreground'>· {r.reason}</span>
              )}
              {r.tags.map((g) => (
                <TagChip key={g.id} tag={g} />
              ))}
            </li>
          ))}
        </ul>
      )}
    </Widget>
  )
}

function SignupsWidget() {
  const { t } = useTranslation()
  const query = useQuery(w<Signup[]>('signups'))
  return (
    <Widget title={t('platform.w.signups')} query={query}>
      {(rows) =>
        rows.length === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('platform.w.no_data')}
          </p>
        ) : (
          <ul className='grid gap-3'>
            {rows.map((r) => (
              <li key={r.id} className='grid gap-1 text-sm'>
                <div className='flex min-w-0 justify-between gap-2'>
                  <span className='min-w-0 truncate font-medium'>{r.name}</span>
                  {r.signup_state && (
                    <Badge variant='outline' className='shrink-0'>
                      {t(`platform.restaurants.signup_short_${r.signup_state}`)}
                    </Badge>
                  )}
                  <span className='tabular-nums'>{r.percent}%</span>
                </div>
                <Bar
                  value={r.percent}
                  label={t('platform.w.setup_of', { name: r.name })}
                />
                <ul className='flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground'>
                  {STEPS.map((s) => (
                    <li key={s} className='flex items-center gap-1'>
                      {r.steps[s] ? (
                        <Check className='size-3.5' aria-hidden />
                      ) : (
                        <X className='size-3.5' aria-hidden />
                      )}
                      <span>{t(`platform.w.steps.${s}`)}</span>
                      <span className='sr-only'>
                        {r.steps[s]
                          ? t('platform.w.done')
                          : t('platform.w.todo')}
                      </span>
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ul>
        )
      }
    </Widget>
  )
}

function HealthWidget() {
  const { t } = useTranslation()
  const query = useQuery(w<Health>('health'))
  return (
    <Widget title={t('platform.w.health')} query={query}>
      {(h) => (
        <div className='grid gap-3'>
          {h.warnings.length > 0 && (
            <div
              role='alert'
              className='flex items-start gap-2 rounded-md border border-destructive p-3 text-sm'
            >
              <AlertTriangle
                className='mt-0.5 size-4 shrink-0 text-destructive'
                aria-hidden
              />
              <ul className='grid gap-1'>
                {h.warnings.map((k) => (
                  <li key={k}>{t(`platform.w.warn.${k}`)}</li>
                ))}
              </ul>
            </div>
          )}
          <dl className='grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4'>
            {[
              [
                t('platform.w.disk_free'),
                h.disk_free_percent === null
                  ? '–'
                  : `${h.disk_free_percent}% · ${gb(h.disk_free)}`,
              ],
              [
                t('platform.w.photos'),
                `${h.photos_count.toLocaleString()} · ${gb(h.photos_bytes)}`,
              ],
              [
                t('platform.w.cron'),
                h.cron_minutes_ago === null
                  ? t('platform.w.never')
                  : t('platform.w.minutes_ago', { count: h.cron_minutes_ago }),
              ],
              [
                t('platform.w.emails'),
                t('platform.w.queued_failed', {
                  queued: h.queued,
                  failed: h.failed,
                }),
              ],
              [t('platform.w.errors_today'), String(h.errors_today)],
              ['PHP', h.php],
              ['Laravel', h.laravel],
            ].map(([k, v]) => (
              <div key={k}>
                <dt className='text-muted-foreground'>{k}</dt>
                <dd className='m-0 font-medium tabular-nums'>{v}</dd>
              </div>
            ))}
          </dl>
        </div>
      )}
    </Widget>
  )
}

const DONUT = [
  'var(--primary)',
  'var(--chart-2, #0d7a5f)',
  'var(--chart-3, #8a6420)',
  'var(--chart-4, #5b6b64)',
  'var(--chart-5, #b4432a)',
]

function PlanMixWidget() {
  const { t } = useTranslation()
  const query = useQuery(w<PlanMix>('plan-mix'))
  return (
    <Widget title={t('platform.w.plan_mix')} query={query}>
      {(d) => {
        const total = d.plans.reduce((a, p) => a + p.total, 0)
        let at = 0
        const label = (p: PlanMix['plans'][number]) =>
          p.id === null ? t('platform.w.no_plan') : nameOf(p.name)
        return total === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('platform.w.no_data')}
          </p>
        ) : (
          <div className='flex flex-wrap items-center gap-4'>
            <svg
              viewBox='0 0 42 42'
              className='size-28 shrink-0'
              role='img'
              aria-label={`${t('platform.w.plan_mix')}: ${d.plans.map((p) => `${label(p)} ${p.total}`).join(', ')}`}
            >
              {d.plans.map((p, i) => {
                const share = (p.total * 100) / total
                const circle = (
                  <circle
                    key={p.id ?? 'none'}
                    cx='21'
                    cy='21'
                    r='15.915'
                    fill='none'
                    stroke={DONUT[i % DONUT.length]}
                    strokeWidth='6'
                    strokeDasharray={`${share} ${100 - share}`}
                    strokeDashoffset={25 - at}
                  />
                )
                at += share
                return circle
              })}
            </svg>
            <div className='grid gap-1 text-sm'>
              {d.plans.map((p, i) => (
                <div key={p.id ?? 'none'} className='flex items-center gap-2'>
                  <span
                    className='size-3 shrink-0 rounded-full'
                    style={{ background: DONUT[i % DONUT.length] }}
                    aria-hidden
                  />
                  <span>{label(p)}</span>
                  <span className='text-muted-foreground tabular-nums'>
                    {p.total}
                  </span>
                </div>
              ))}
              <p className='mt-1 text-muted-foreground'>
                {t('platform.w.changes', {
                  up: d.upgrades,
                  down: d.downgrades,
                })}
              </p>
            </div>
          </div>
        )
      }}
    </Widget>
  )
}

function Toggle<T extends number>({
  value,
  options,
  label,
  onChange,
}: {
  value: T
  options: T[]
  label: (v: T) => string
  onChange: (v: T) => void
}) {
  return (
    <div className='flex gap-2'>
      {options.map((o) => (
        <Button
          key={o}
          type='button'
          size='sm'
          variant={o === value ? 'default' : 'outline'}
          aria-pressed={o === value}
          className='min-h-11 sm:min-h-9'
          onClick={() => onChange(o)}
        >
          {label(o)}
        </Button>
      ))}
    </div>
  )
}

/** "Remind" / "Email owner": a short, editable message sent through the platform's SMTP settings. */
function EmailDialog({
  target,
  onClose,
}: {
  target: {
    id: number
    name: string
    reason: 'expiring' | 'inactive'
    days?: number | null
  }
  onClose: () => void
}) {
  const { t } = useTranslation()
  const vars = { name: target.name, count: target.days ?? 0 }
  const [subject, setSubject] = useState(
    t(`platform.w.mail.${target.reason}_subject`, vars)
  )
  const [message, setMessage] = useState(
    t(`platform.w.mail.${target.reason}_body`, vars)
  )
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(
    null
  )
  const send = useMutation({
    mutationFn: () =>
      api.post<{ message: string }>(`/platform/widgets/email/${target.id}`, {
        subject,
        message,
        reason: target.reason,
      }),
    meta: { inlineErrors: true },
    onSuccess: (res) => setResult({ ok: true, text: res.data.message }),
    onError: (err) =>
      setResult({
        ok: false,
        text:
          (isApiError(err)
            ? (err.response?.data as { message?: string } | undefined)?.message
            : undefined) ?? t('platform.w.mail.failed'),
      }),
  })
  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader className='text-start'>
          <DialogTitle>
            {t('platform.w.mail.title', { name: target.name })}
          </DialogTitle>
          <DialogDescription>{t('platform.w.mail.intro')}</DialogDescription>
        </DialogHeader>
        <div className='grid gap-3'>
          <div className='grid gap-1'>
            <Label htmlFor='mail-subject'>{t('platform.w.mail.subject')}</Label>
            <Input
              id='mail-subject'
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              maxLength={150}
            />
          </div>
          <div className='grid gap-1'>
            <Label htmlFor='mail-message'>{t('platform.w.mail.message')}</Label>
            <Textarea
              id='mail-message'
              rows={6}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              maxLength={3000}
            />
          </div>
          {result && (
            <p
              role={result.ok ? 'status' : 'alert'}
              className={result.ok ? 'text-sm' : 'text-sm text-destructive'}
            >
              {result.text}
            </p>
          )}
        </div>
        <DialogFooter>
          <Button
            className='min-h-11 sm:min-h-9'
            disabled={
              send.isPending || !subject.trim() || !message.trim() || result?.ok
            }
            onClick={() => send.mutate()}
          >
            <Mail /> {t('platform.w.mail.send')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
