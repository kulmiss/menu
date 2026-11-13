import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
  ArrowLeft,
  Bold,
  Eye,
  Link2,
  List,
  Loader2,
  Plus,
  RotateCcw,
  Send,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { DataTable } from '@/components/data-table'
import {
  campaignQ,
  type CampaignRow,
  campaignsQ,
  plansQ,
  send,
  settingsQ,
  tagsQ,
  usePlatformMutation,
} from './api'
import { PageTitle } from './layout'

const STATUSES = ['active', 'trial', 'expiring', 'expired', 'suspended']
const PLACEHOLDERS = ['restaurant_name', 'owner_name', 'plan', 'menu_link']

type Filters = {
  plan_id?: number
  status?: string
  tag_id?: number
  inactive_days?: number
  signed_up_from?: string
  signed_up_to?: string
  /** Restaurants picked in the restaurants table (QUESTIONS #185). */
  restaurant_ids?: number[]
}

/** ?restaurants=1,2,3 from the restaurants table's "E-mail owners". */
const pickedRestaurants = (): number[] =>
  (new URLSearchParams(window.location.search).get('restaurants') ?? '')
    .split(',')
    .map(Number)
    .filter((n) => Number.isInteger(n) && n > 0)

/** Super Admin → Messages: e-mails to restaurant owners (owner, 2026-11-13). */
export function MessagesPage() {
  const [picked] = useState(pickedRestaurants)
  const [composing, setComposing] = useState(picked.length > 0)
  const [open, setOpen] = useState<number | null>(null)

  if (composing)
    return (
      <Composer
        picked={picked}
        onDone={(id) => {
          setComposing(false)
          setOpen(id)
        }}
        onCancel={() => setComposing(false)}
      />
    )
  if (open !== null)
    return <CampaignDetail id={open} onBack={() => setOpen(null)} />
  return (
    <CampaignList
      onNew={() => setComposing(true)}
      onOpen={(id) => setOpen(id)}
    />
  )
}

function CampaignList({
  onNew,
  onOpen,
}: {
  onNew: () => void
  onOpen: (id: number) => void
}) {
  const { t, i18n } = useTranslation()
  const list = useQuery({ ...campaignsQ(), refetchInterval: 15_000 })
  const date = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))

  return (
    <>
      <PageTitle
        title={t('platform.nav.messages')}
        hint={t('platform.messages.hint')}
      >
        <Button onClick={onNew}>
          <Plus /> {t('platform.messages.new')}
        </Button>
      </PageTitle>
      <RateSetting />
      <DataTable<CampaignRow>
        id='platform-campaigns'
        rows={list.data}
        loading={list.isPending}
        error={list.isError}
        onRetry={() => list.refetch()}
        csvName='campaigns'
        rowId={(c) => c.id}
        onRowClick={(c) => onOpen(c.id)}
        searchPlaceholder={t('platform.messages.search')}
        dateLabel={t('platform.messages.col.created')}
        dateOf={(c) => c.created_at}
        filters={[
          {
            id: 'status',
            label: t('platform.messages.col.status'),
            options: ['sending', 'done'].map((s) => ({
              value: s,
              label: t(`platform.messages.status.${s}`),
            })),
            match: (c, v) => v.includes(c.status),
          },
          {
            id: 'failures',
            label: t('platform.messages.with_failures'),
            options: [
              { value: '1', label: t('platform.messages.with_failures') },
            ],
            match: (c) => c.failed > 0,
          },
        ]}
        empty={t('platform.messages.none')}
        columns={[
          {
            id: 'subject',
            label: t('platform.messages.col.subject'),
            primary: true,
            text: (c) => c.subject,
            sortValue: (c) => c.subject.toLowerCase(),
            cell: (c) => <span className='font-medium'>{c.subject}</span>,
          },
          {
            id: 'status',
            label: t('platform.messages.col.status'),
            text: (c) => t(`platform.messages.status.${c.status}`),
            cell: (c) => (
              <Badge variant={c.status === 'done' ? 'secondary' : 'outline'}>
                {t(`platform.messages.status.${c.status}`)}
              </Badge>
            ),
          },
          {
            id: 'created',
            label: t('platform.messages.col.created'),
            text: (c) => c.created_at,
            sortValue: (c) => c.created_at,
            cell: (c) => date(c.created_at),
          },
          {
            id: 'recipients',
            label: t('platform.messages.col.recipients'),
            align: 'end',
            text: (c) => c.recipients,
            sortValue: (c) => c.recipients,
            cell: (c) => c.recipients,
          },
          {
            id: 'sent',
            label: t('platform.messages.col.sent'),
            align: 'end',
            text: (c) => c.sent,
            sortValue: (c) => c.sent,
            cell: (c) => c.sent,
          },
          {
            id: 'failed',
            label: t('platform.messages.col.failed'),
            align: 'end',
            text: (c) => c.failed,
            sortValue: (c) => c.failed,
            cell: (c) => (
              <span className={c.failed ? 'font-medium text-destructive' : ''}>
                {c.failed}
              </span>
            ),
          },
        ]}
      />
    </>
  )
}

function RateSetting() {
  const { t } = useTranslation()
  const settings = useQuery(settingsQ())
  const [rate, setRate] = useState<string | null>(null)
  const save = usePlatformMutation((value: number) =>
    send('put', 'campaigns/rate', { campaign_rate: value })
  )
  const current = rate ?? String(settings.data?.campaign_rate ?? 30)

  return (
    <form
      className='mb-4 flex flex-wrap items-end gap-2'
      onSubmit={async (e) => {
        e.preventDefault()
        await save.mutateAsync(Number(current))
        toast.success(t('platform.saved'))
      }}
    >
      <div className='grid gap-1'>
        <Label htmlFor='msg-rate'>{t('platform.messages.rate')}</Label>
        <Input
          id='msg-rate'
          type='number'
          min={1}
          max={500}
          className='w-32'
          value={current}
          onChange={(e) => setRate(e.target.value)}
        />
      </div>
      <Button
        type='submit'
        variant='outline'
        className='min-h-11 sm:min-h-9'
        disabled={save.isPending}
      >
        {t('common.save')}
      </Button>
      <p className='basis-full text-sm text-muted-foreground'>
        {t('platform.messages.rate_hint')}
      </p>
    </form>
  )
}

function CampaignDetail({ id, onBack }: { id: number; onBack: () => void }) {
  const { t } = useTranslation()
  const c = useQuery({ ...campaignQ(id), refetchInterval: 10_000 })
  const retry = usePlatformMutation(
    () => send('post', `campaigns/${id}/retry`),
    false
  )
  const d = c.data

  return (
    <>
      <Button
        variant='ghost'
        className='mb-2 min-h-11 sm:min-h-9'
        onClick={onBack}
      >
        <ArrowLeft /> {t('platform.messages.back')}
      </Button>
      {!d ? (
        <Skeleton className='h-64' />
      ) : (
        <div className='grid gap-4'>
          <div>
            <h2 className='text-xl font-semibold'>{d.subject}</h2>
            <p className='text-sm text-muted-foreground'>
              {t(`platform.messages.status.${d.status}`)} ·{' '}
              {t('platform.messages.counts', {
                sent: d.sent,
                total: d.recipients,
                failed: d.failed,
              })}
              {d.pending > 0 &&
                ` · ${t('platform.messages.pending', { count: d.pending })}`}
            </p>
          </div>
          {d.failed > 0 && (
            <Button
              className='min-h-11 justify-self-start sm:min-h-9'
              disabled={retry.isPending}
              onClick={async () => {
                await retry.mutateAsync(undefined)
                toast.success(t('platform.messages.requeued'))
              }}
            >
              <RotateCcw /> {t('platform.messages.retry', { count: d.failed })}
            </Button>
          )}
          <div className='overflow-x-auto rounded-md border'>
            <table className='w-full text-sm'>
              <thead className='bg-muted/50 text-xs text-muted-foreground'>
                <tr>
                  {['email', 'restaurant', 'status'].map((h) => (
                    <th key={h} className='p-2 text-start'>
                      {t(`platform.messages.col.${h}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {d.list.map((r) => (
                  <tr key={r.id} className='border-t align-top'>
                    <td className='p-2'>{r.email}</td>
                    <td className='p-2'>{r.restaurant}</td>
                    <td className='p-2'>
                      <Badge
                        variant={
                          r.status === 'failed'
                            ? 'destructive'
                            : r.status === 'sent'
                              ? 'secondary'
                              : 'outline'
                        }
                      >
                        {t(`platform.messages.recipient.${r.status}`)}
                      </Badge>
                      {r.error && (
                        <p className='mt-1 text-xs text-muted-foreground'>
                          {r.error}
                        </p>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  )
}

function Composer({
  picked = [],
  onDone,
  onCancel,
}: {
  picked?: number[]
  onDone: (id: number) => void
  onCancel: () => void
}) {
  const { t } = useTranslation()
  const plans = useQuery(plansQ())
  const tags = useQuery(tagsQ())
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [filters, setFilters] = useState<Filters>(
    picked.length ? { restaurant_ids: picked } : {}
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [preview, setPreview] = useState<{
    subject: string
    html: string
    sample: string | null
  } | null>(null)
  const [confirming, setConfirming] = useState(false)
  const area = useRef<HTMLTextAreaElement>(null)
  const payload = () => ({ subject, body, filters })

  // Recipient count, refreshed when the filters change.
  const count = useMutation({
    mutationFn: async (f: Filters) =>
      (
        await api.post<{ data: { count: number } }>(
          '/platform/campaigns/count',
          {
            subject: 'x',
            body: 'x',
            filters: f,
          }
        )
      ).data.data.count,
  })
  const countMutate = count.mutate
  useEffect(() => {
    countMutate(filters)
  }, [filters, countMutate])

  const showPreview = useMutation({
    mutationFn: async () =>
      (
        await api.post<{
          data: { subject: string; html: string; sample: string | null }
        }>('/platform/campaigns/preview', payload())
      ).data.data,
    onSuccess: setPreview,
    onError: (err) => setErrors(validationErrors(err) ?? {}),
  })
  const test = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ data: { sent_to: string } }>(
          '/platform/campaigns/test',
          payload()
        )
      ).data.data.sent_to,
    onSuccess: (to) =>
      toast.success(t('platform.messages.test_sent', { email: to })),
    onError: (err) => setErrors(validationErrors(err) ?? {}),
  })
  const create = usePlatformMutation((body: object) =>
    send('post', 'campaigns', body)
  )

  // Toolbar: wraps the selection in Markdown, or inserts a placeholder.
  function insert(before: string, after = '', fallback = '') {
    const el = area.current
    if (!el) return
    const { selectionStart: s, selectionEnd: e } = el
    const picked = body.slice(s, e) || fallback
    const next = body.slice(0, s) + before + picked + after + body.slice(e)
    setBody(next)
    requestAnimationFrame(() => {
      el.focus()
      el.setSelectionRange(s + before.length, s + before.length + picked.length)
    })
  }

  const set = (k: keyof Filters, v: string | number | undefined) =>
    setFilters((f) => {
      const next = { ...f }
      if (v === undefined || v === '' || v === 'all') delete next[k]
      else (next as Record<string, unknown>)[k] = v
      return next
    })
  const recipients = count.data ?? 0
  const ready = subject.trim() !== '' && body.trim() !== ''

  return (
    <>
      <Button
        variant='ghost'
        className='mb-2 min-h-11 sm:min-h-9'
        onClick={onCancel}
      >
        <ArrowLeft /> {t('platform.messages.back')}
      </Button>
      <PageTitle
        title={t('platform.messages.new')}
        hint={t('platform.messages.compose_hint')}
      />
      <div className='grid max-w-3xl gap-5'>
        <div className='grid gap-1'>
          <Label htmlFor='msg-subject'>{t('platform.messages.subject')}</Label>
          <Input
            id='msg-subject'
            maxLength={150}
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
          />
          {errors.subject && (
            <p className='text-sm text-destructive'>{errors.subject}</p>
          )}
        </div>
        <div className='grid gap-1'>
          <Label htmlFor='msg-body'>{t('platform.messages.body')}</Label>
          <div
            className='flex flex-wrap gap-1'
            role='toolbar'
            aria-label={t('platform.messages.toolbar')}
          >
            <Button
              type='button'
              size='sm'
              variant='outline'
              className='min-h-11 sm:min-h-8'
              onClick={() =>
                insert('**', '**', t('platform.messages.bold_text'))
              }
            >
              <Bold className='size-4' /> {t('platform.messages.bold')}
            </Button>
            <Button
              type='button'
              size='sm'
              variant='outline'
              className='min-h-11 sm:min-h-8'
              onClick={() =>
                insert('[', '](https://)', t('platform.messages.link_text'))
              }
            >
              <Link2 className='size-4' /> {t('platform.messages.link')}
            </Button>
            <Button
              type='button'
              size='sm'
              variant='outline'
              className='min-h-11 sm:min-h-8'
              onClick={() =>
                insert('\n- ', '', t('platform.messages.list_text'))
              }
            >
              <List className='size-4' /> {t('platform.messages.list')}
            </Button>
            {PLACEHOLDERS.map((p) => (
              <Button
                key={p}
                type='button'
                size='sm'
                variant='ghost'
                className='min-h-11 font-mono text-xs sm:min-h-8'
                onClick={() => insert(`{${p}}`)}
              >
                {`{${p}}`}
              </Button>
            ))}
          </div>
          <Textarea
            id='msg-body'
            ref={area}
            rows={10}
            maxLength={20000}
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
          <p className='text-sm text-muted-foreground'>
            {t('platform.messages.body_hint')}
          </p>
          {errors.body && (
            <p className='text-sm text-destructive'>{errors.body}</p>
          )}
        </div>

        <fieldset className='grid gap-3 rounded-md border p-3'>
          <legend className='px-1 text-sm font-medium'>
            {t('platform.messages.recipients')}
          </legend>
          {filters.restaurant_ids && (
            <div className='mb-3 flex flex-wrap items-center gap-2 rounded-md bg-muted/60 p-2 text-sm'>
              {t('platform.messages.picked', {
                count: filters.restaurant_ids.length,
              })}
              <Button
                type='button'
                variant='ghost'
                size='sm'
                onClick={() =>
                  setFilters(({ restaurant_ids: _, ...rest }) => rest)
                }
              >
                {t('platform.messages.picked_clear')}
              </Button>
            </div>
          )}
          <div className='grid gap-3 sm:grid-cols-3'>
            <div className='grid gap-1'>
              <span className='text-sm font-medium'>
                {t('platform.restaurants.plan')}
              </span>
              <Select
                value={filters.plan_id ? String(filters.plan_id) : 'all'}
                onValueChange={(v) =>
                  set('plan_id', v === 'all' ? undefined : Number(v))
                }
              >
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('platform.restaurants.plan')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('platform.messages.any')}
                  </SelectItem>
                  {plans.data?.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name.en ?? p.slug}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='grid gap-1'>
              <span className='text-sm font-medium'>
                {t('platform.messages.status_filter')}
              </span>
              <Select
                value={filters.status ?? 'all'}
                onValueChange={(v) => set('status', v)}
              >
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('platform.messages.status_filter')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('platform.messages.any')}
                  </SelectItem>
                  {STATUSES.map((s) => (
                    <SelectItem key={s} value={s}>
                      {t(`platform.messages.statuses.${s}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='grid gap-1'>
              <span className='text-sm font-medium'>
                {t('platform.crm.tag')}
              </span>
              <Select
                value={filters.tag_id ? String(filters.tag_id) : 'all'}
                onValueChange={(v) =>
                  set('tag_id', v === 'all' ? undefined : Number(v))
                }
              >
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('platform.crm.tag')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('platform.messages.any')}
                  </SelectItem>
                  {tags.data?.map((g) => (
                    <SelectItem key={g.id} value={String(g.id)}>
                      {g.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='msg-inactive'>
                {t('platform.messages.inactive')}
              </Label>
              <Input
                id='msg-inactive'
                type='number'
                min={1}
                placeholder='30'
                value={filters.inactive_days ?? ''}
                onChange={(e) =>
                  set(
                    'inactive_days',
                    e.target.value ? Number(e.target.value) : undefined
                  )
                }
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='msg-from'>
                {t('platform.messages.signed_up_from')}
              </Label>
              <Input
                id='msg-from'
                type='date'
                value={filters.signed_up_from ?? ''}
                onChange={(e) => set('signed_up_from', e.target.value)}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='msg-to'>
                {t('platform.messages.signed_up_to')}
              </Label>
              <Input
                id='msg-to'
                type='date'
                value={filters.signed_up_to ?? ''}
                onChange={(e) => set('signed_up_to', e.target.value)}
              />
            </div>
          </div>
          <p className='text-sm font-medium' aria-live='polite'>
            {count.isPending ? (
              <Loader2 className='inline size-4 animate-spin' />
            ) : (
              t('platform.messages.will_reach', { count: recipients })
            )}
          </p>
          <p className='text-xs text-muted-foreground'>
            {t('platform.messages.unsubscribe_note')}
          </p>
        </fieldset>

        <div className='flex flex-wrap gap-2'>
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={!ready || showPreview.isPending}
            onClick={() => showPreview.mutate()}
          >
            <Eye /> {t('platform.messages.preview')}
          </Button>
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={!ready || test.isPending}
            onClick={() => test.mutate()}
          >
            {test.isPending ? <Loader2 className='animate-spin' /> : <Send />}
            {t('platform.messages.test')}
          </Button>
          <Button
            type='button'
            className='min-h-11 sm:min-h-9'
            disabled={!ready || recipients === 0}
            onClick={() => setConfirming(true)}
          >
            <Send /> {t('platform.messages.send', { count: recipients })}
          </Button>
        </div>
      </div>

      {preview && (
        <Dialog open onOpenChange={(o) => !o && setPreview(null)}>
          <DialogContent className='max-w-2xl'>
            <DialogHeader>
              <DialogTitle>{preview.subject}</DialogTitle>
              <DialogDescription>
                {preview.sample
                  ? t('platform.messages.preview_for', { name: preview.sample })
                  : t('platform.messages.preview_sample')}
              </DialogDescription>
            </DialogHeader>
            <iframe
              title={t('platform.messages.preview')}
              sandbox=''
              srcDoc={`<!doctype html><meta charset="utf-8"><body style="font:15px/1.6 system-ui,sans-serif;color:#1f2937;margin:16px">${preview.html}</body>`}
              className='h-80 w-full rounded-md border bg-white'
            />
          </DialogContent>
        </Dialog>
      )}

      {confirming && (
        <Dialog open onOpenChange={(o) => !o && setConfirming(false)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                {t('platform.messages.confirm_title', { count: recipients })}
              </DialogTitle>
              <DialogDescription>
                {t('platform.messages.confirm_text')}
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant='outline' onClick={() => setConfirming(false)}>
                {t('common.cancel')}
              </Button>
              <Button
                disabled={create.isPending}
                onClick={async () => {
                  try {
                    const res = (await create.mutateAsync(payload())) as {
                      data: { data: { id: number } }
                    }
                    toast.success(t('platform.messages.queued'))
                    onDone(res.data.data.id)
                  } catch (err) {
                    setErrors(validationErrors(err) ?? {})
                    setConfirming(false)
                  }
                }}
              >
                {create.isPending && <Loader2 className='animate-spin' />}
                {t('platform.messages.send', { count: recipients })}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </>
  )
}
