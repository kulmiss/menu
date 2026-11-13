import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download, Loader2, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  FEATURES,
  LIMITS,
  type PlanRow,
  activityQ,
  announcementsQ,
  money,
  paymentsQ,
  plansQ,
  send,
  settingsQ,
  themesQ,
  usePlatformMutation,
} from './api'
import { EmailSettings } from './email-settings'
import { BillingSettings, type BillingSettingsData } from './invoices'
import { LandingSettings, type LandingSettingsData } from './landing-settings'
import { PageTitle } from './layout'
import { ShareImageCard } from './share-image'
import {
  EmailLogoCard,
  SignupSettings,
  type SignupSettingsData,
} from './signup-settings'

function useErrors() {
  const [errors, setErrors] = useState<Record<string, string>>({})
  const first = Object.values(errors)[0]
  return {
    errors,
    setErrors,
    first,
    catchErr: (err: unknown) => setErrors(validationErrors(err) ?? {}),
  }
}

// ---- Plans -------------------------------------------------------------

export function PlansPage() {
  const { t } = useTranslation()
  const plans = useQuery(plansQ())
  const [editing, setEditing] = useState<PlanRow | 'new' | null>(null)
  return (
    <>
      <PageTitle
        title={t('platform.nav.plans')}
        hint={t('platform.plans.hint')}
      >
        <Button onClick={() => setEditing('new')}>
          <Plus /> {t('platform.plans.new')}
        </Button>
      </PageTitle>
      {plans.isPending ? (
        <Skeleton className='h-40' />
      ) : (
        <div className='grid gap-3 md:grid-cols-2'>
          {plans.data?.map((p) => (
            <Card
              key={p.id}
              className='cursor-pointer py-4 hover:bg-accent/40'
              onClick={() => setEditing(p)}
            >
              <CardContent className='grid gap-1 text-sm'>
                <div className='flex items-center gap-2'>
                  <p className='text-base font-semibold'>
                    {p.name.en ?? p.slug}
                  </p>
                  {!p.is_active && (
                    <Badge variant='outline'>{t('platform.off')}</Badge>
                  )}
                  {!p.is_public && (
                    <Badge variant='secondary'>
                      {t('platform.plans.hidden')}
                    </Badge>
                  )}
                </div>
                <p>
                  {money(p.price_minor, p.currency)} /{' '}
                  {t(`platform.plans.${p.interval}`)} ·{' '}
                  {t('platform.plans.trial', { count: p.trial_days })}
                </p>
                <p className='text-muted-foreground'>
                  {LIMITS.filter((k) => p.limits[k] != null)
                    .map((k) => `${t(`platform.limits.${k}`)} ${p.limits[k]}`)
                    .join(' · ') || t('platform.plans.no_limits')}
                </p>
                <p className='text-muted-foreground'>
                  {t('platform.plans.restaurants', { count: p.restaurants })}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      {editing && (
        <PlanDialog
          plan={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  )
}

function PlanDialog({
  plan,
  onClose,
}: {
  plan: PlanRow | null
  onClose: () => void
}) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    plan ? send('patch', `plans/${plan.id}`, body) : send('post', 'plans', body)
  )
  const e = useErrors()
  const [v, setV] = useState({
    name: plan?.name.en ?? '',
    slug: plan?.slug ?? '',
    price: plan ? String(plan.price_minor / 100) : '',
    currency: plan?.currency ?? 'ETB',
    interval: plan?.interval ?? 'month',
    trial_days: String(plan?.trial_days ?? 14),
    limits: Object.fromEntries(
      LIMITS.map((k) => [
        k,
        plan?.limits[k] != null ? String(plan.limits[k]) : '',
      ])
    ),
    features: {
      ...Object.fromEntries(FEATURES.map((f) => [f, f !== 'remove_branding'])),
      ...(plan?.features ?? {}),
    } as Record<string, boolean>,
    is_public: plan?.is_public ?? true,
    is_active: plan?.is_active ?? true,
  })

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    try {
      await save.mutateAsync({
        name: { ...(plan?.name ?? {}), en: v.name },
        slug: v.slug,
        price_minor: Math.round(Number(v.price || 0) * 100),
        currency: v.currency.toUpperCase(),
        interval: v.interval,
        trial_days: Number(v.trial_days),
        limits: Object.fromEntries(
          Object.entries(v.limits).map(([k, n]) => [
            k,
            n === '' ? null : Number(n),
          ])
        ),
        features: v.features,
        is_public: v.is_public,
        is_active: v.is_active,
      })
      onClose()
    } catch (err) {
      e.catchErr(err)
    }
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-lg'>
        <form onSubmit={submit} className='grid gap-3'>
          <DialogHeader>
            <DialogTitle>
              {plan ? t('platform.plans.edit') : t('platform.plans.new')}
            </DialogTitle>
          </DialogHeader>
          <div className='grid gap-3 sm:grid-cols-2'>
            <div className='grid gap-1'>
              <Label htmlFor='pl-name'>{t('platform.plans.name')}</Label>
              <Input
                id='pl-name'
                value={v.name}
                onChange={(x) => setV({ ...v, name: x.target.value })}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='pl-slug'>{t('platform.plans.slug')}</Label>
              <Input
                id='pl-slug'
                className='font-mono'
                value={v.slug}
                onChange={(x) =>
                  setV({ ...v, slug: x.target.value.toLowerCase() })
                }
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='pl-price'>{t('platform.plans.price')}</Label>
              <Input
                id='pl-price'
                inputMode='decimal'
                value={v.price}
                onChange={(x) => setV({ ...v, price: x.target.value })}
              />
            </div>
            <div className='grid grid-cols-2 gap-2'>
              <div className='grid gap-1'>
                <Label htmlFor='pl-cur'>{t('platform.plans.currency')}</Label>
                <Input
                  id='pl-cur'
                  maxLength={3}
                  value={v.currency}
                  onChange={(x) => setV({ ...v, currency: x.target.value })}
                />
              </div>
              <div className='grid gap-1'>
                <Label>{t('platform.plans.per')}</Label>
                <Select
                  value={v.interval}
                  onValueChange={(x) =>
                    setV({ ...v, interval: x as 'month' | 'year' })
                  }
                >
                  <SelectTrigger aria-label={t('platform.plans.per')}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='month'>
                      {t('platform.plans.month')}
                    </SelectItem>
                    <SelectItem value='year'>
                      {t('platform.plans.year')}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='pl-trial'>{t('platform.plans.trial_days')}</Label>
              <Input
                id='pl-trial'
                inputMode='numeric'
                value={v.trial_days}
                onChange={(x) => setV({ ...v, trial_days: x.target.value })}
              />
            </div>
          </div>
          <fieldset className='grid gap-2'>
            <legend className='mb-1 text-sm font-medium'>
              {t('platform.plans.limits')}
            </legend>
            <div className='grid grid-cols-2 gap-2 sm:grid-cols-4'>
              {LIMITS.map((k) => (
                <div key={k} className='grid gap-1'>
                  <Label htmlFor={`lim-${k}`} className='text-xs'>
                    {t(`platform.limits.${k}`)}
                  </Label>
                  <Input
                    id={`lim-${k}`}
                    inputMode='numeric'
                    placeholder='∞'
                    value={v.limits[k]}
                    onChange={(x) =>
                      setV({
                        ...v,
                        limits: {
                          ...v.limits,
                          [k]: x.target.value.replace(/\D/g, ''),
                        },
                      })
                    }
                  />
                </div>
              ))}
            </div>
          </fieldset>
          <fieldset className='grid gap-2'>
            <legend className='mb-1 text-sm font-medium'>
              {t('platform.restaurants.features')}
            </legend>
            {FEATURES.map((f) => (
              <label key={f} className='flex items-center gap-2 text-sm'>
                <Checkbox
                  checked={v.features[f]}
                  onCheckedChange={(on) =>
                    setV({
                      ...v,
                      features: { ...v.features, [f]: on === true },
                    })
                  }
                />
                {t(`platform.features.${f}`)}
              </label>
            ))}
          </fieldset>
          <div className='flex flex-wrap gap-6'>
            <label className='flex items-center gap-2 text-sm'>
              <Switch
                checked={v.is_public}
                onCheckedChange={(x) => setV({ ...v, is_public: x })}
              />
              {t('platform.plans.public')}
            </label>
            <label className='flex items-center gap-2 text-sm'>
              <Switch
                checked={v.is_active}
                onCheckedChange={(x) => setV({ ...v, is_active: x })}
              />
              {t('platform.plans.active')}
            </label>
          </div>
          {e.first && <p className='text-sm text-destructive'>{e.first}</p>}
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type='submit' disabled={save.isPending}>
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

// ---- Payments ----------------------------------------------------------

export function PaymentsPage() {
  const { t } = useTranslation()
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params = new URLSearchParams()
  if (from) params.set('from', from)
  if (to) params.set('to', to)
  const list = useQuery(paymentsQ(params.toString()))
  const totals = (list.data ?? []).reduce<Record<string, number>>(
    (acc, p) => ({
      ...acc,
      [p.currency]: (acc[p.currency] ?? 0) + p.amount_minor,
    }),
    {}
  )

  return (
    <>
      <PageTitle
        title={t('platform.nav.payments')}
        hint={t('platform.payments.hint')}
      >
        <Button asChild variant='outline'>
          <a
            href={`${api.defaults.baseURL}/platform/payments.csv?${params}`}
            download
          >
            <Download /> CSV
          </a>
        </Button>
      </PageTitle>
      <div className='mb-3 flex flex-wrap items-end gap-2'>
        <div className='grid gap-1'>
          <Label htmlFor='pf' className='text-xs'>
            {t('platform.payments.from')}
          </Label>
          <Input
            id='pf'
            type='date'
            value={from}
            onChange={(e) => setFrom(e.target.value)}
          />
        </div>
        <div className='grid gap-1'>
          <Label htmlFor='pt' className='text-xs'>
            {t('platform.payments.to')}
          </Label>
          <Input
            id='pt'
            type='date'
            value={to}
            onChange={(e) => setTo(e.target.value)}
          />
        </div>
        <p className='ms-auto text-sm'>
          {t('platform.payments.total')}:{' '}
          <b>
            {Object.entries(totals)
              .map(([c, v]) => money(v, c))
              .join(' · ') || '—'}
          </b>
        </p>
      </div>
      <div className='overflow-x-auto rounded-md border'>
        <table className='w-full text-sm'>
          <thead className='bg-muted/50 text-xs text-muted-foreground'>
            <tr>
              {[
                'paid_at',
                'restaurant',
                'amount',
                'method',
                'reference',
                'period',
              ].map((h) => (
                <th key={h} className='p-2 text-start'>
                  {t(`platform.payments.col.${h}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {list.data?.map((p) => (
              <tr key={p.id} className='border-t'>
                <td className='p-2 tabular-nums'>{p.paid_at}</td>
                <td className='p-2'>{p.tenant?.name ?? '—'}</td>
                <td className='p-2 tabular-nums'>
                  {money(p.amount_minor, p.currency)}
                </td>
                <td className='p-2'>{t(`platform.methods.${p.method}`)}</td>
                <td className='p-2'>{p.reference ?? ''}</td>
                <td className='p-2 tabular-nums'>
                  {p.period_start} → {p.period_end}
                </td>
              </tr>
            ))}
            {list.data?.length === 0 && (
              <tr>
                <td
                  colSpan={6}
                  className='p-6 text-center text-muted-foreground'
                >
                  {t('platform.payments.none')}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}

// ---- Themes ------------------------------------------------------------

export function ThemesPage() {
  const { t } = useTranslation()
  const themes = useQuery(themesQ())
  const save = usePlatformMutation(
    ({ id, ...body }: { id?: number } & Record<string, unknown>) =>
      id ? send('patch', `themes/${id}`, body) : send('post', 'themes', body)
  )
  const e = useErrors()
  const [v, setV] = useState({
    name: '',
    primary: '#b4432a',
    font: 'system',
    style: 'grid',
  })

  return (
    <>
      <PageTitle
        title={t('platform.nav.themes')}
        hint={t('platform.themes.hint')}
      />
      <div className='grid gap-3 md:grid-cols-2'>
        {themes.data?.map((th) => (
          <Card key={th.id} className='py-3'>
            <CardContent className='flex items-center gap-3 text-sm'>
              <span
                className='size-8 rounded-full border'
                style={{ background: th.primary }}
              />
              <div className='flex-1'>
                <p className='font-medium'>{th.name.en}</p>
                <p className='text-muted-foreground'>
                  {t(`platform.themes.fonts.${th.font}`)} ·{' '}
                  {t(`platform.themes.styles.${th.style}`)}
                </p>
              </div>
              <Switch
                aria-label={t('platform.themes.offered', { name: th.name.en })}
                checked={th.is_active}
                onCheckedChange={(on) =>
                  save.mutate({ id: th.id, is_active: on })
                }
              />
            </CardContent>
          </Card>
        ))}
      </div>
      <Card className='mt-4'>
        <CardHeader>
          <CardTitle>{t('platform.themes.new')}</CardTitle>
        </CardHeader>
        <CardContent>
          <form
            className='flex flex-wrap items-end gap-2'
            onSubmit={async (ev) => {
              ev.preventDefault()
              try {
                await save.mutateAsync({ ...v, name: { en: v.name } })
                setV({ ...v, name: '' })
              } catch (err) {
                e.catchErr(err)
              }
            }}
          >
            <div className='grid gap-1'>
              <Label htmlFor='th-name'>{t('platform.themes.name')}</Label>
              <Input
                id='th-name'
                value={v.name}
                onChange={(x) => setV({ ...v, name: x.target.value })}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='th-col'>{t('platform.themes.colour')}</Label>
              <input
                id='th-col'
                type='color'
                className='h-9 w-14 rounded border'
                value={v.primary}
                onChange={(x) => setV({ ...v, primary: x.target.value })}
              />
            </div>
            {(['font', 'style'] as const).map((k) => (
              <div key={k} className='grid gap-1'>
                <Label>{t(`platform.themes.${k}`)}</Label>
                <Select
                  value={v[k]}
                  onValueChange={(x) => setV({ ...v, [k]: x })}
                >
                  <SelectTrigger
                    className='w-36'
                    aria-label={t(`platform.themes.${k}`)}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(k === 'font'
                      ? ['system', 'serif', 'rounded']
                      : ['editorial', 'grid', 'bistro', 'minimal']
                    ).map((o) => (
                      <SelectItem key={o} value={o}>
                        {t(
                          `platform.themes.${k === 'font' ? 'fonts' : 'styles'}.${o}`
                        )}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ))}
            <Button type='submit' disabled={!v.name.trim() || save.isPending}>
              <Plus /> {t('platform.add')}
            </Button>
          </form>
          {e.first && (
            <p className='mt-2 text-sm text-destructive'>{e.first}</p>
          )}
        </CardContent>
      </Card>
    </>
  )
}

// ---- Announcements -----------------------------------------------------

export function AnnouncementsPage() {
  const { t } = useTranslation()
  const list = useQuery(announcementsQ())
  const plans = useQuery(plansQ())
  const save = usePlatformMutation((body: object) =>
    send('post', 'announcements', body)
  )
  const remove = usePlatformMutation(
    (id: number) => send('delete', `announcements/${id}`),
    false
  )
  const e = useErrors()
  const [v, setV] = useState({
    title: '',
    body: '',
    level: 'info',
    plan: 'all',
    starts_at: '',
    ends_at: '',
  })

  return (
    <>
      <PageTitle
        title={t('platform.nav.announcements')}
        hint={t('platform.announcements.hint')}
      />
      <Card>
        <CardContent className='pt-6'>
          <form
            className='grid gap-3'
            onSubmit={async (ev) => {
              ev.preventDefault()
              try {
                await save.mutateAsync({
                  title: { en: v.title },
                  body: { en: v.body },
                  level: v.level,
                  plan_ids: v.plan === 'all' ? null : [Number(v.plan)],
                  starts_at: v.starts_at || null,
                  ends_at: v.ends_at || null,
                })
                setV({ ...v, title: '', body: '' })
                toast.success(t('platform.saved'))
              } catch (err) {
                e.catchErr(err)
              }
            }}
          >
            <div className='grid gap-1'>
              <Label htmlFor='an-title'>
                {t('platform.announcements.title')}
              </Label>
              <Input
                id='an-title'
                value={v.title}
                onChange={(x) => setV({ ...v, title: x.target.value })}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='an-body'>
                {t('platform.announcements.body')}
              </Label>
              <Textarea
                id='an-body'
                rows={3}
                value={v.body}
                onChange={(x) => setV({ ...v, body: x.target.value })}
              />
            </div>
            <div className='flex flex-wrap items-end gap-2'>
              <Select
                value={v.level}
                onValueChange={(x) => setV({ ...v, level: x })}
              >
                <SelectTrigger
                  className='w-32'
                  aria-label={t('platform.announcements.level')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='info'>
                    {t('platform.announcements.info')}
                  </SelectItem>
                  <SelectItem value='warning'>
                    {t('platform.announcements.warning')}
                  </SelectItem>
                </SelectContent>
              </Select>
              <Select
                value={v.plan}
                onValueChange={(x) => setV({ ...v, plan: x })}
              >
                <SelectTrigger
                  className='w-44'
                  aria-label={t('platform.announcements.audience')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('platform.announcements.everyone')}
                  </SelectItem>
                  {plans.data?.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name.en ?? p.slug}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <div className='grid gap-1'>
                <Label htmlFor='an-s' className='text-xs'>
                  {t('platform.announcements.starts')}
                </Label>
                <Input
                  id='an-s'
                  type='datetime-local'
                  value={v.starts_at}
                  onChange={(x) => setV({ ...v, starts_at: x.target.value })}
                />
              </div>
              <div className='grid gap-1'>
                <Label htmlFor='an-e' className='text-xs'>
                  {t('platform.announcements.ends')}
                </Label>
                <Input
                  id='an-e'
                  type='datetime-local'
                  value={v.ends_at}
                  onChange={(x) => setV({ ...v, ends_at: x.target.value })}
                />
              </div>
              <Button
                type='submit'
                disabled={!v.title.trim() || save.isPending}
              >
                {t('platform.announcements.send')}
              </Button>
            </div>
            {e.first && <p className='text-sm text-destructive'>{e.first}</p>}
          </form>
        </CardContent>
      </Card>
      <ul className='mt-4 grid gap-2'>
        {list.data?.map((a) => (
          <li
            key={a.id}
            className='flex items-start justify-between gap-2 rounded-md border p-3 text-sm'
          >
            <div>
              <p className='font-medium'>
                {a.title.en}{' '}
                {a.level === 'warning' && (
                  <Badge variant='outline'>
                    {t('platform.announcements.warning')}
                  </Badge>
                )}
              </p>
              {a.body.en && (
                <p className='text-muted-foreground'>{a.body.en}</p>
              )}
              <p className='text-xs text-muted-foreground'>
                {a.starts_at?.slice(0, 16) ?? '…'} →{' '}
                {a.ends_at?.slice(0, 16) ?? '…'}
              </p>
            </div>
            <Button
              variant='ghost'
              size='sm'
              onClick={() => remove.mutate(a.id)}
            >
              {t('platform.delete')}
            </Button>
          </li>
        ))}
      </ul>
    </>
  )
}

// ---- Activity log --------------------------------------------------------

export function ActivityPage() {
  const { t } = useTranslation()
  const [action, setAction] = useState('')
  const [platformOnly, setPlatformOnly] = useState(false)
  const params = new URLSearchParams()
  if (action.trim()) params.set('action', action.trim())
  if (platformOnly) params.set('platform_only', '1')
  const list = useQuery(activityQ(params.toString()))
  return (
    <>
      <PageTitle
        title={t('platform.nav.activity')}
        hint={t('platform.activity.hint')}
      />
      <div className='mb-3 flex flex-wrap items-center gap-3'>
        <Input
          className='w-56'
          placeholder={t('platform.activity.action')}
          aria-label={t('platform.activity.action')}
          value={action}
          onChange={(e) => setAction(e.target.value)}
        />
        <label className='flex items-center gap-2 text-sm'>
          <Checkbox
            checked={platformOnly}
            onCheckedChange={(on) => setPlatformOnly(on === true)}
          />
          {t('platform.activity.platform_only')}
        </label>
      </div>
      <div className='overflow-x-auto rounded-md border'>
        <table className='w-full text-sm'>
          <tbody>
            {list.data?.map((l) => (
              <tr key={l.id} className='border-t align-top'>
                <td className='p-2 whitespace-nowrap text-muted-foreground tabular-nums'>
                  {new Date(l.at).toLocaleString()}
                </td>
                <td className='p-2'>
                  {l.tenant?.name ?? t('platform.activity.platform')}
                </td>
                <td className='p-2'>
                  {l.actor ?? '—'}
                  {l.impersonator && (
                    <span className='text-destructive'>
                      {' '}
                      ({t('platform.activity.as', { name: l.impersonator })})
                    </span>
                  )}
                </td>
                <td className='p-2 font-mono text-xs'>{l.action}</td>
                <td className='p-2 text-xs text-muted-foreground'>
                  {l.subject}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}

// ---- Settings -----------------------------------------------------------

export function SettingsPage() {
  const { t } = useTranslation()
  const settings = useQuery(settingsQ())
  if (!settings.data) return <Skeleton className='h-64' />
  return <SettingsForm initial={settings.data} t={t} />
}

function SettingsForm({
  initial,
  t,
}: {
  initial: Record<string, string | null>
  t: (k: string) => string
}) {
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings', body)
  )
  const e = useErrors()
  const [v, setV] = useState({
    name: initial.name ?? '',
    support_email: initial.support_email ?? '',
    domain: initial.domain ?? '',
    default_currency: initial.default_currency ?? 'ETB',
    terms: initial.terms ?? '',
    privacy: initial.privacy ?? '',
  })
  const input = (
    k: keyof typeof v,
    props: React.ComponentProps<typeof Input> = {}
  ) => (
    <div className='grid gap-1'>
      <Label htmlFor={`st-${k}`}>{t(`platform.settings.${k}`)}</Label>
      <Input
        id={`st-${k}`}
        value={v[k]}
        onChange={(x) => setV({ ...v, [k]: x.target.value })}
        {...props}
      />
      {e.errors[k] && <p className='text-sm text-destructive'>{e.errors[k]}</p>}
    </div>
  )
  return (
    <>
      <PageTitle title={t('platform.nav.settings')} />
      <form
        className='grid max-w-2xl gap-4'
        onSubmit={async (ev) => {
          ev.preventDefault()
          try {
            await save.mutateAsync({
              ...v,
              support_email: v.support_email || null,
              domain: v.domain || null,
            })
            toast.success(t('platform.saved'))
          } catch (err) {
            e.catchErr(err)
          }
        }}
      >
        <div className='grid gap-4 sm:grid-cols-2'>
          {input('name')}
          {input('support_email', { type: 'email' })}
          {input('domain', { placeholder: 'menu.example.com' })}
          {input('default_currency', { maxLength: 3 })}
        </div>
        {(['terms', 'privacy'] as const).map((k) => (
          <div key={k} className='grid gap-1'>
            <Label htmlFor={`st-${k}`}>{t(`platform.settings.${k}`)}</Label>
            <Textarea
              id={`st-${k}`}
              rows={5}
              value={v[k]}
              onChange={(x) => setV({ ...v, [k]: x.target.value })}
            />
          </div>
        ))}
        <Button
          type='submit'
          className='justify-self-start'
          disabled={save.isPending}
        >
          {t('common.save')}
        </Button>
      </form>
      <SignupSettings initial={initial as unknown as SignupSettingsData} />
      <LandingSettings initial={initial as unknown as LandingSettingsData} />
      <BillingSettings initial={initial as unknown as BillingSettingsData} />
      <ShareImageCard
        url={initial.share_image_url ?? ''}
        custom={!!initial.share_image}
      />
      <EmailLogoCard url={initial.logo_url} />
      <EmailSettings />
    </>
  )
}
