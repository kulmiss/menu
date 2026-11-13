import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ExternalLink, Loader2, Mail, Plus, Tag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { slugify } from '@/lib/slug'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import {
  FEATURES,
  type Restaurant,
  STATES,
  money,
  plansQ,
  restaurantQ,
  restaurantsQ,
  tagsQ,
  send,
  usePlatformMutation,
} from './api'
import { CrmSection, ManageTagsDialog, TagChip } from './crm'
import { InvoiceActions } from './invoices'
import { PageTitle } from './layout'

type Props = { state?: string; open?: number; onState: (s?: string) => void }

export function RestaurantsPage({ state, open, onState }: Props) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [deleted, setDeleted] = useState(false)
  const [selected, setSelected] = useState<number | null>(open ?? null)
  const [creating, setCreating] = useState(false)
  const [tag, setTag] = useState('all')
  const [due, setDue] = useState(false)
  const [managing, setManaging] = useState(false)
  const tags = useQuery(tagsQ())
  const params = new URLSearchParams()
  if (search.trim()) params.set('search', search.trim())
  if (state) params.set('state', state)
  if (deleted) params.set('deleted', '1')
  if (tag !== 'all') params.set('tag_id', tag)
  if (due) params.set('follow_up', 'due')
  const list = useQuery(restaurantsQ(params.toString()))

  return (
    <>
      <PageTitle
        title={t('platform.nav.restaurants')}
        hint={t('platform.restaurants.hint')}
      >
        <Button onClick={() => setCreating(true)}>
          <Plus /> {t('platform.restaurants.new')}
        </Button>
      </PageTitle>
      <div className='mb-3 flex flex-wrap gap-2'>
        <Input
          className='w-64'
          placeholder={t('platform.restaurants.search')}
          aria-label={t('platform.restaurants.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Select
          value={state ?? 'all'}
          onValueChange={(v) => onState(v === 'all' ? undefined : v)}
        >
          <SelectTrigger
            className='w-44'
            aria-label={t('platform.restaurants.state')}
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>
              {t('platform.restaurants.all_states')}
            </SelectItem>
            {STATES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`platform.states.${s}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={tag} onValueChange={setTag}>
          <SelectTrigger className='w-40' aria-label={t('platform.crm.tag')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>{t('platform.crm.all_tags')}</SelectItem>
            {tags.data?.map((g) => (
              <SelectItem key={g.id} value={String(g.id)}>
                {g.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button
          variant={due ? 'secondary' : 'ghost'}
          onClick={() => setDue((d) => !d)}
          aria-pressed={due}
        >
          {t('platform.crm.due_filter')}
        </Button>
        <Button
          variant={deleted ? 'secondary' : 'ghost'}
          onClick={() => setDeleted((d) => !d)}
          aria-pressed={deleted}
        >
          {t('platform.restaurants.deleted')}
        </Button>
        <Button variant='ghost' onClick={() => setManaging(true)}>
          <Tag /> {t('platform.crm.manage_tags')}
        </Button>
      </div>
      {list.isPending ? (
        <Skeleton className='h-48' />
      ) : (
        <div className='overflow-x-auto rounded-md border'>
          <table className='w-full text-sm'>
            <thead className='bg-muted/50 text-start text-xs text-muted-foreground'>
              <tr>
                <th className='p-2 text-start'>
                  {t('platform.restaurants.name')}
                </th>
                <th className='p-2 text-start'>
                  {t('platform.restaurants.owner')}
                </th>
                <th className='p-2 text-start'>
                  {t('platform.restaurants.plan')}
                </th>
                <th className='p-2 text-start'>
                  {t('platform.restaurants.state')}
                </th>
                <th className='p-2 text-start'>
                  {t('platform.restaurants.until')}
                </th>
              </tr>
            </thead>
            <tbody>
              {list.data?.map((r) => (
                <tr
                  key={r.id}
                  className='cursor-pointer border-t hover:bg-accent/50'
                  onClick={() => setSelected(r.id)}
                >
                  <td className='p-2'>
                    <button
                      type='button'
                      className='text-start font-medium hover:underline'
                      onClick={() => setSelected(r.id)}
                    >
                      {r.name}
                    </button>
                    <div className='text-xs text-muted-foreground'>
                      /{r.slug}
                    </div>
                    {(r.tags?.length ?? 0) > 0 && (
                      <div className='mt-1 flex flex-wrap gap-1'>
                        {r.tags?.map((g) => (
                          <TagChip key={g.id} tag={g} />
                        ))}
                      </div>
                    )}
                    {r.follow_up_on && (
                      <div className='mt-1 text-xs text-muted-foreground'>
                        {t('platform.crm.follow_up_on', {
                          date: r.follow_up_on,
                        })}
                      </div>
                    )}
                  </td>
                  <td className='p-2'>{r.owner?.email ?? '—'}</td>
                  <td className='p-2'>{r.plan?.name ?? '—'}</td>
                  <td className='p-2'>
                    <StateBadge state={r.state} />
                  </td>
                  <td className='p-2 tabular-nums'>
                    {r.deleted_at
                      ? t('platform.restaurants.deleted_on', {
                          date: r.deleted_at,
                        })
                      : (r.stage_ends_at ?? '—')}
                  </td>
                </tr>
              ))}
              {list.data?.length === 0 && (
                <tr>
                  <td
                    colSpan={5}
                    className='p-6 text-center text-muted-foreground'
                  >
                    {t('platform.restaurants.none')}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
      {selected !== null && (
        <RestaurantSheet id={selected} onClose={() => setSelected(null)} />
      )}
      {creating && <CreateDialog onClose={() => setCreating(false)} />}
      {managing && <ManageTagsDialog onClose={() => setManaging(false)} />}
    </>
  )
}

export function StateBadge({ state }: { state: string }) {
  const { t } = useTranslation()
  const tone =
    state === 'active' || state === 'trial'
      ? 'secondary'
      : state === 'grace' || state === 'pending'
        ? 'outline'
        : 'destructive'
  return <Badge variant={tone}>{t(`platform.states.${state}`)}</Badge>
}

function CreateDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const plans = useQuery(plansQ())
  const [v, setV] = useState({
    name: '',
    slug: '',
    owner_name: '',
    owner_email: '',
    plan_id: 'default',
    trial_days: '14',
  })
  const [slugTouched, setSlugTouched] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = usePlatformMutation((body: object) =>
    send('post', 'restaurants', body)
  )

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        ...v,
        plan_id: v.plan_id === 'default' ? null : Number(v.plan_id),
        trial_days: Number(v.trial_days),
      })
      toast.success(t('platform.restaurants.created'))
      onClose()
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }
  const field = (
    key: keyof typeof v,
    label: string,
    props: React.ComponentProps<typeof Input> = {}
  ) => (
    <div className='grid gap-1.5'>
      <Label htmlFor={`c-${key}`}>{label}</Label>
      <Input
        id={`c-${key}`}
        value={v[key]}
        onChange={(e) => {
          const value = e.target.value
          setV((s) => ({
            ...s,
            [key]: value,
            ...(key === 'name' && !slugTouched
              ? { slug: slugify(value).slice(0, 50) }
              : {}),
          }))
          if (key === 'slug') setSlugTouched(true)
        }}
        {...props}
      />
      {errors[key] && <p className='text-sm text-destructive'>{errors[key]}</p>}
    </div>
  )

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <form onSubmit={submit} className='grid gap-3'>
          <DialogHeader>
            <DialogTitle>{t('platform.restaurants.new')}</DialogTitle>
          </DialogHeader>
          {field('name', t('platform.restaurants.name'), { autoFocus: true })}
          {field('slug', t('platform.restaurants.slug'), {
            className: 'font-mono',
          })}
          {field('owner_name', t('platform.restaurants.owner_name'))}
          {field('owner_email', t('platform.restaurants.owner_email'), {
            type: 'email',
          })}
          <p className='text-xs text-muted-foreground'>
            {t('platform.restaurants.owner_hint')}
          </p>
          <div className='grid gap-3 sm:grid-cols-2'>
            <div className='grid gap-1.5'>
              <Label>{t('platform.restaurants.plan')}</Label>
              <Select
                value={v.plan_id}
                onValueChange={(p) => setV((s) => ({ ...s, plan_id: p }))}
              >
                <SelectTrigger aria-label={t('platform.restaurants.plan')}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='default'>
                    {t('platform.restaurants.trial_plan')}
                  </SelectItem>
                  {plans.data?.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name.en ?? p.slug}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {field('trial_days', t('platform.restaurants.trial_days'), {
              inputMode: 'numeric',
            })}
          </div>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type='submit' disabled={save.isPending}>
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('platform.restaurants.create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function RestaurantSheet({ id, onClose }: { id: number; onClose: () => void }) {
  const { t } = useTranslation()
  const detail = useQuery(restaurantQ(id))
  const plans = useQuery(plansQ())
  const r = detail.data
  const act = usePlatformMutation(
    ({
      m,
      path,
      body,
    }: {
      m: 'post' | 'patch' | 'put' | 'delete'
      path: string
      body?: object
    }) => send(m, path, body)
  )
  const [reason, setReason] = useState<{
    kind: 'suspend' | 'reject'
    text: string
  } | null>(null)
  const [pay, setPay] = useState({
    amount: '',
    method: 'mobile_money',
    reference: '',
    months: '1',
    paid_at: new Date().toISOString().slice(0, 10),
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  async function run(
    m: 'post' | 'patch' | 'put' | 'delete',
    path: string,
    body?: object,
    done?: string
  ) {
    setErrors({})
    try {
      await act.mutateAsync({ m, path, body })
      if (done) toast.success(done)
      return true
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
      return false
    }
  }

  // One click, no reason (owner, 2026-11-13).
  async function impersonate() {
    if (await run('post', `restaurants/${id}/impersonate`))
      window.location.assign('/admin')
  }

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className='w-full overflow-y-auto sm:max-w-xl'>
        <SheetHeader>
          <SheetTitle>{r?.name ?? '…'}</SheetTitle>
        </SheetHeader>
        {!r ? (
          <Skeleton className='mx-4 h-64' />
        ) : (
          <div className='grid gap-5 px-4 pb-8 text-sm'>
            <div className='flex flex-wrap items-center gap-2'>
              <StateBadge state={r.state} />
              <a
                href={r.url}
                target='_blank'
                rel='noreferrer'
                className='inline-flex items-center gap-1 text-muted-foreground hover:underline'
              >
                /{r.slug} <ExternalLink className='size-3' />
              </a>
              {r.suspended_reason && (
                <span className='text-destructive'>{r.suspended_reason}</span>
              )}
              {r.signup_state && (
                <span className='text-muted-foreground'>
                  {t(`platform.restaurants.signup_${r.signup_state}`)}
                </span>
              )}
            </div>
            {r.signup_state === 'pending_approval' && !r.deleted_at && (
              <section className='grid gap-2 rounded-md border border-amber-500/50 p-3'>
                <h3 className='font-semibold'>
                  {t('platform.restaurants.approval')}
                </h3>
                <p className='text-muted-foreground'>
                  {t('platform.restaurants.approval_hint')}
                </p>
                <div className='flex flex-wrap gap-2'>
                  <Button
                    className='min-h-11 sm:min-h-9'
                    disabled={act.isPending}
                    onClick={() =>
                      run(
                        'post',
                        `restaurants/${id}/approve`,
                        {},
                        t('platform.restaurants.approved')
                      )
                    }
                  >
                    {t('platform.restaurants.approve')}
                  </Button>
                  <Button
                    variant='outline'
                    className='min-h-11 sm:min-h-9'
                    disabled={act.isPending}
                    onClick={() => setReason({ kind: 'reject', text: '' })}
                  >
                    {t('platform.restaurants.reject')}
                  </Button>
                </div>
              </section>
            )}
            <p className='text-muted-foreground'>
              {t('platform.restaurants.counts', r.counts)}
            </p>

            <section className='grid gap-2'>
              <h3 className='font-semibold'>
                {t('platform.restaurants.subscription')}
              </h3>
              <div className='grid gap-2 sm:grid-cols-3'>
                <div className='grid gap-1'>
                  <Label className='text-xs'>
                    {t('platform.restaurants.plan')}
                  </Label>
                  <Select
                    value={r.plan ? String(r.plan.id) : 'none'}
                    onValueChange={(p) =>
                      run(
                        'patch',
                        `restaurants/${id}`,
                        { plan_id: p === 'none' ? null : Number(p) },
                        t('platform.saved')
                      )
                    }
                  >
                    <SelectTrigger aria-label={t('platform.restaurants.plan')}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='none'>—</SelectItem>
                      {plans.data?.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name.en ?? p.slug}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className='grid gap-1'>
                  <Label htmlFor='r-trial' className='text-xs'>
                    {t('platform.restaurants.trial_until')}
                  </Label>
                  <Input
                    id='r-trial'
                    type='date'
                    defaultValue={r.trial_ends_at ?? ''}
                    onBlur={(e) =>
                      e.target.value !== (r.trial_ends_at ?? '') &&
                      run(
                        'patch',
                        `restaurants/${id}`,
                        { trial_ends_at: e.target.value || null },
                        t('platform.saved')
                      )
                    }
                  />
                </div>
                <div className='grid gap-1'>
                  <Label className='text-xs'>
                    {t('platform.restaurants.paid_until')}
                  </Label>
                  <p className='py-2 tabular-nums'>{r.paid_until ?? '—'}</p>
                </div>
              </div>
            </section>

            <section className='grid gap-2'>
              <h3 className='font-semibold'>
                {t('platform.restaurants.record_payment')}
              </h3>
              <form
                className='grid gap-2 sm:grid-cols-[1fr_1fr_5rem]'
                onSubmit={async (e) => {
                  e.preventDefault()
                  const ok = await run(
                    'post',
                    `restaurants/${id}/payments`,
                    {
                      amount_minor: Math.round(
                        Number(pay.amount.replace(',', '.')) * 100
                      ),
                      method: pay.method,
                      reference: pay.reference || null,
                      months: Number(pay.months),
                      paid_at: pay.paid_at,
                    },
                    t('platform.restaurants.payment_saved')
                  )
                  if (ok) setPay((p) => ({ ...p, amount: '', reference: '' }))
                }}
              >
                <Input
                  aria-label={t('platform.payments.amount', {
                    currency: r.currency_code,
                  })}
                  placeholder={t('platform.payments.amount', {
                    currency: r.currency_code,
                  })}
                  inputMode='decimal'
                  value={pay.amount}
                  onChange={(e) => setPay({ ...pay, amount: e.target.value })}
                />
                <Select
                  value={pay.method}
                  onValueChange={(m) => setPay({ ...pay, method: m })}
                >
                  <SelectTrigger aria-label={t('platform.payments.method')}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {['cash', 'bank', 'mobile_money', 'other'].map((m) => (
                      <SelectItem key={m} value={m}>
                        {t(`platform.methods.${m}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Input
                  aria-label={t('platform.payments.months')}
                  title={t('platform.payments.months')}
                  inputMode='numeric'
                  value={pay.months}
                  onChange={(e) =>
                    setPay({
                      ...pay,
                      months: e.target.value.replace(/\D/g, ''),
                    })
                  }
                />
                <Input
                  aria-label={t('platform.payments.reference')}
                  placeholder={t('platform.payments.reference')}
                  value={pay.reference}
                  onChange={(e) =>
                    setPay({ ...pay, reference: e.target.value })
                  }
                />
                <Input
                  aria-label={t('platform.payments.paid_at')}
                  type='date'
                  value={pay.paid_at}
                  onChange={(e) => setPay({ ...pay, paid_at: e.target.value })}
                />
                <Button type='submit' disabled={!pay.amount || act.isPending}>
                  {t('platform.restaurants.add')}
                </Button>
              </form>
              {(errors.amount_minor || errors.months) && (
                <p className='text-destructive'>
                  {errors.amount_minor ?? errors.months}
                </p>
              )}
              <ul className='divide-y rounded-md border'>
                {r.payments.map((p) => (
                  <li key={p.id} className='grid gap-1 px-3 py-1.5'>
                    <div className='flex justify-between gap-2'>
                      <span>
                        {p.paid_at} · {t(`platform.methods.${p.method}`)}
                        {p.reference ? ` · ${p.reference}` : ''}
                      </span>
                      <span className='tabular-nums'>
                        {money(p.amount_minor, p.currency)} → {p.period_end}
                      </span>
                    </div>
                    {p.invoices?.map((inv) => (
                      <InvoiceActions key={inv.id} invoice={inv} />
                    ))}
                  </li>
                ))}
                {r.payments.length === 0 && (
                  <li className='px-3 py-2 text-muted-foreground'>
                    {t('platform.restaurants.no_payments')}
                  </li>
                )}
              </ul>
            </section>

            <CrmSection
              key={`${r.id}-${r.follow_up_on ?? ''}`}
              id={r.id}
              tags={r.tags ?? []}
              followUpOn={r.follow_up_on ?? null}
              followUpReason={r.follow_up_reason ?? null}
            />

            <section className='grid gap-2'>
              <h3 className='font-semibold'>
                {t('platform.restaurants.features')}
              </h3>
              {FEATURES.map((f) => (
                <div
                  key={f}
                  className='flex items-center justify-between gap-2'
                >
                  <span>
                    {t(`platform.features.${f}`)}{' '}
                    <span className='text-muted-foreground'>
                      ({r.features[f] ? t('platform.on') : t('platform.off')})
                    </span>
                  </span>
                  <Select
                    value={
                      f in r.overrides
                        ? r.overrides[f]
                          ? 'on'
                          : 'off'
                        : 'plan'
                    }
                    onValueChange={(v) =>
                      run(
                        'put',
                        `restaurants/${id}/features`,
                        { features: { [f]: v === 'plan' ? null : v === 'on' } },
                        t('platform.saved')
                      )
                    }
                  >
                    <SelectTrigger
                      className='w-36'
                      aria-label={t(`platform.features.${f}`)}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='plan'>
                        {t('platform.restaurants.as_plan')}
                      </SelectItem>
                      <SelectItem value='on'>{t('platform.on')}</SelectItem>
                      <SelectItem value='off'>{t('platform.off')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              ))}
            </section>

            <section className='grid gap-2'>
              <h3 className='font-semibold'>
                {t('platform.restaurants.team')}
              </h3>
              {r.members.map((m) => (
                <p key={m.id}>
                  {m.name} · {m.email} · {m.role}
                </p>
              ))}
              {!r.deleted_at && r.members.some((m) => m.role === 'owner') && (
                <div className='grid justify-items-start gap-1'>
                  <Button
                    variant='outline'
                    size='sm'
                    disabled={act.isPending}
                    onClick={() =>
                      run(
                        'post',
                        `restaurants/${id}/resend-welcome`,
                        undefined,
                        t('platform.restaurants.welcome_sent')
                      )
                    }
                  >
                    <Mail /> {t('platform.restaurants.resend_welcome')}
                  </Button>
                  <p className='text-xs text-muted-foreground'>
                    {t('platform.restaurants.resend_welcome_hint')}
                  </p>
                </div>
              )}
            </section>

            <section className='flex flex-wrap gap-2 border-t pt-4'>
              {!r.deleted_at && (
                <Button disabled={act.isPending} onClick={impersonate}>
                  {t('platform.restaurants.log_in_as')}
                </Button>
              )}
              {!r.deleted_at &&
                (r.status === 'suspended' ? (
                  <Button
                    variant='outline'
                    onClick={() =>
                      run(
                        'post',
                        `restaurants/${id}/unsuspend`,
                        {},
                        t('platform.saved')
                      )
                    }
                  >
                    {t('platform.restaurants.unsuspend')}
                  </Button>
                ) : (
                  <Button
                    variant='outline'
                    onClick={() => setReason({ kind: 'suspend', text: '' })}
                  >
                    {t('platform.restaurants.suspend')}
                  </Button>
                ))}
              {r.deleted_at ? (
                <Button
                  variant='outline'
                  onClick={() =>
                    run(
                      'post',
                      `restaurants/${id}/restore`,
                      {},
                      t('platform.saved')
                    )
                  }
                >
                  {t('platform.restaurants.restore')}
                </Button>
              ) : (
                <Button
                  variant='destructive'
                  onClick={async () => {
                    if (
                      window.confirm(
                        t('platform.restaurants.delete_confirm', {
                          name: r.name,
                        })
                      )
                    ) {
                      if (await run('delete', `restaurants/${id}`)) onClose()
                    }
                  }}
                >
                  {t('platform.restaurants.delete')}
                </Button>
              )}
            </section>
          </div>
        )}
        {reason && (
          <Dialog open onOpenChange={(o) => !o && setReason(null)}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>
                  {t(`platform.restaurants.${reason.kind}`)}
                </DialogTitle>
              </DialogHeader>
              <p className='text-sm text-muted-foreground'>
                {t(`platform.restaurants.${reason.kind}_hint`)}
              </p>
              <Label htmlFor='reason'>{t('platform.restaurants.reason')}</Label>
              <Textarea
                id='reason'
                rows={2}
                value={reason.text}
                onChange={(e) => setReason({ ...reason, text: e.target.value })}
              />
              {errors.reason && (
                <p className='text-sm text-destructive'>{errors.reason}</p>
              )}
              <DialogFooter>
                <Button variant='outline' onClick={() => setReason(null)}>
                  {t('common.cancel')}
                </Button>
                <Button
                  disabled={reason.text.trim().length < 5 || act.isPending}
                  onClick={async () => {
                    const reject = reason.kind === 'reject'
                    if (
                      await run(
                        'post',
                        `restaurants/${id}/${reason.kind}`,
                        { reason: reason.text.trim() },
                        t(
                          reject
                            ? 'platform.restaurants.rejected'
                            : 'platform.saved'
                        )
                      )
                    ) {
                      setReason(null)
                      // A rejected sign-up is removed (account and restaurant).
                      if (reject) onClose()
                    }
                  }}
                >
                  {t('common.continue')}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        )}
      </SheetContent>
    </Sheet>
  )
}

export type { Restaurant }
