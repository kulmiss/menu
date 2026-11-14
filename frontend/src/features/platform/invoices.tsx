import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileText, Loader2, Mail } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { type Col, DataTable } from '@/components/data-table'
import {
  type InvoiceRef,
  type InvoiceRow,
  fetchPage,
  invoicePdfUrl,
  pkeys,
  money,
  plansQ,
  send,
  usePlatformMutation,
} from './api'
import { PageTitle } from './layout'

/** "INV-2026-0001" with view / resend / void (owner, 2026-11-13). */
export function InvoiceActions({ invoice }: { invoice: InvoiceRef }) {
  const { t } = useTranslation()
  const [voiding, setVoiding] = useState(false)
  const resend = usePlatformMutation(
    () => send('post', `invoices/${invoice.id}/resend`),
    false
  )
  const isVoid = invoice.status === 'void'

  return (
    <span className='inline-flex flex-wrap items-center gap-1'>
      <a
        href={invoicePdfUrl(invoice.id)}
        target='_blank'
        rel='noopener'
        className='inline-flex min-h-11 items-center gap-1 rounded-md px-2 font-mono text-sm underline-offset-4 hover:underline sm:min-h-8'
        aria-label={t('platform.invoices.view', { number: invoice.number })}
      >
        <FileText className='size-4' aria-hidden /> {invoice.number}
      </a>
      {isVoid ? (
        <Badge variant='destructive'>
          {t('platform.invoices.status.void')}
        </Badge>
      ) : (
        <>
          <Button
            type='button'
            size='sm'
            variant='ghost'
            className='min-h-11 sm:min-h-8'
            disabled={resend.isPending}
            onClick={async () => {
              try {
                await resend.mutateAsync(undefined)
                toast.success(t('platform.invoices.sent'))
              } catch {
                // the global handler shows the server's message
              }
            }}
          >
            <Mail className='size-4' /> {t('platform.invoices.resend')}
          </Button>
          <Button
            type='button'
            size='sm'
            variant='ghost'
            className='min-h-11 text-destructive sm:min-h-8'
            onClick={() => setVoiding(true)}
          >
            {t('platform.invoices.void')}
          </Button>
        </>
      )}
      {voiding && (
        <VoidDialog invoice={invoice} onClose={() => setVoiding(false)} />
      )}
    </span>
  )
}

function VoidDialog({
  invoice,
  onClose,
}: {
  invoice: InvoiceRef
  onClose: () => void
}) {
  const { t } = useTranslation()
  const [reason, setReason] = useState('')
  const [reissue, setReissue] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const act = usePlatformMutation((body: object) =>
    send('post', `invoices/${invoice.id}/void`, body)
  )

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {t('platform.invoices.void_title', { number: invoice.number })}
          </DialogTitle>
          <DialogDescription>
            {t('platform.invoices.void_hint')}
          </DialogDescription>
        </DialogHeader>
        <form
          className='grid gap-3'
          onSubmit={async (e) => {
            e.preventDefault()
            setError(null)
            try {
              await act.mutateAsync({ reason: reason.trim(), reissue })
              toast.success(
                t(
                  reissue
                    ? 'platform.invoices.voided_reissued'
                    : 'platform.invoices.voided'
                )
              )
              onClose()
            } catch (err) {
              setError(validationErrors(err)?.reason ?? null)
            }
          }}
        >
          <Label htmlFor='void-reason'>{t('platform.invoices.reason')}</Label>
          <Textarea
            id='void-reason'
            rows={2}
            maxLength={300}
            value={reason}
            aria-invalid={!!error}
            onChange={(e) => setReason(e.target.value)}
          />
          {error && <p className='text-sm text-destructive'>{error}</p>}
          <label className='flex min-h-11 items-center gap-3 text-sm'>
            <Checkbox
              checked={reissue}
              onCheckedChange={(v) => setReissue(v === true)}
            />
            {t('platform.invoices.reissue')}
          </label>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button
              type='submit'
              variant='destructive'
              disabled={reason.trim().length < 3 || act.isPending}
            >
              {act.isPending && <Loader2 className='animate-spin' />}
              {t('platform.invoices.void')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Super Admin → Invoices: search, filters, CSV. */
export function InvoicesPage() {
  const { t, i18n } = useTranslation()
  const plans = useQuery(plansQ())
  const date = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
      new Date(iso)
    )
  const columns: Col<InvoiceRow>[] = [
    {
      id: 'number',
      label: t('platform.invoices.col.number'),
      primary: true,
      sort: 'number',
      text: (i) => i.number,
      cell: (i) => (
        <div onClick={(e) => e.stopPropagation()}>
          <InvoiceActions invoice={i} />
          {i.void_reason && (
            <p className='px-2 text-xs text-muted-foreground'>
              {i.void_reason}
            </p>
          )}
        </div>
      ),
    },
    {
      id: 'issued',
      label: t('platform.invoices.col.issued'),
      sort: 'issued_at',
      text: (i) => i.issued_at.slice(0, 10),
      cell: (i) => (
        <span className='whitespace-nowrap'>{date(i.issued_at)}</span>
      ),
    },
    {
      id: 'restaurant',
      label: t('platform.invoices.col.restaurant'),
      text: (i) => i.tenant.name,
      cell: (i) => i.tenant.name,
    },
    {
      id: 'plan',
      label: t('platform.invoices.col.plan'),
      text: (i) => i.plan ?? '',
      cell: (i) => i.plan ?? '—',
    },
    {
      id: 'period',
      label: t('platform.invoices.col.period'),
      text: (i) => `${i.period_start} – ${i.period_end}`,
      cell: (i) => (
        <span className='whitespace-nowrap tabular-nums'>
          {i.period_start} → {i.period_end}
        </span>
      ),
    },
    {
      id: 'total',
      label: t('platform.invoices.col.total'),
      sort: 'total',
      align: 'end',
      text: (i) => i.total_minor / 100,
      cell: (i) => money(i.total_minor, i.currency),
    },
  ]

  return (
    <>
      <PageTitle
        title={t('platform.nav.invoices')}
        hint={t('platform.invoices.hint')}
      />
      <DataTable<InvoiceRow>
        id='platform-invoices'
        queryKey={[...pkeys.all, 'invoices', 'table']}
        fetch={(params) => fetchPage<InvoiceRow>('invoices', params)}
        csvUrl={(params) => `/api/v1/platform/invoices?format=csv&${params}`}
        columns={columns}
        rowId={(i) => i.id}
        searchPlaceholder={t('platform.invoices.search_hint')}
        dateLabel={t('platform.invoices.col.issued')}
        filters={[
          {
            id: 'status',
            label: t('platform.invoices.status_label'),
            options: ['paid', 'void'].map((s) => ({
              value: s,
              label: t(`platform.invoices.status.${s}`),
            })),
          },
          {
            id: 'plan',
            label: t('platform.restaurants.plan'),
            options: (plans.data ?? []).map((p) => ({
              value: String(p.id),
              label: p.name.en ?? p.slug,
            })),
          },
        ]}
        empty={t('platform.invoices.none')}
      />
    </>
  )
}

export type BillingSettingsData = {
  billing_company: string
  billing_address: string
  billing_tax_id: string
  invoice_prefix: string
  invoice_footer: string
  invoice_tax_rate: number
  invoice_email: boolean
}

/** Settings → Billing: what every new invoice shows. */
export function BillingSettings({ initial }: { initial: BillingSettingsData }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings', body)
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [v, setV] = useState({
    billing_company: initial.billing_company ?? '',
    billing_address: initial.billing_address ?? '',
    billing_tax_id: initial.billing_tax_id ?? '',
    invoice_prefix: initial.invoice_prefix || 'INV',
    invoice_footer: initial.invoice_footer ?? '',
    invoice_tax_rate: String(initial.invoice_tax_rate ?? 0),
    invoice_email: initial.invoice_email ?? true,
  })
  const field = (
    k:
      | 'billing_company'
      | 'billing_tax_id'
      | 'invoice_prefix'
      | 'invoice_tax_rate',
    props: React.ComponentProps<typeof Input> = {}
  ) => (
    <div className='grid gap-1'>
      <Label htmlFor={`bill-${k}`}>{t(`platform.billing.${k}`)}</Label>
      <Input
        id={`bill-${k}`}
        value={v[k]}
        onChange={(e) => setV({ ...v, [k]: e.target.value })}
        {...props}
      />
      {errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>}
    </div>
  )

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.billing.title')}</CardTitle>
        <CardDescription>{t('platform.billing.hint')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form
          className='grid gap-4'
          onSubmit={async (e) => {
            e.preventDefault()
            setErrors({})
            try {
              await save.mutateAsync({
                ...v,
                invoice_prefix: v.invoice_prefix.trim(),
                invoice_tax_rate: Number(v.invoice_tax_rate || 0),
              })
              toast.success(t('platform.saved'))
            } catch (err) {
              setErrors(validationErrors(err) ?? {})
            }
          }}
        >
          <div className='grid gap-4 sm:grid-cols-2'>
            {field('billing_company', { maxLength: 120 })}
            {field('billing_tax_id', { maxLength: 60 })}
          </div>
          <div className='grid gap-1'>
            <Label htmlFor='bill-address'>
              {t('platform.billing.billing_address')}
            </Label>
            <Textarea
              id='bill-address'
              rows={2}
              maxLength={300}
              value={v.billing_address}
              onChange={(e) => setV({ ...v, billing_address: e.target.value })}
            />
          </div>
          <div className='grid gap-4 sm:grid-cols-2'>
            {field('invoice_prefix', { maxLength: 10, dir: 'ltr' })}
            {field('invoice_tax_rate', {
              type: 'number',
              min: 0,
              max: 100,
              step: '0.01',
              inputMode: 'decimal',
            })}
          </div>
          <p className='-mt-2 text-sm text-muted-foreground'>
            {t('platform.billing.prefix_hint', {
              example: `${(v.invoice_prefix || 'INV').toUpperCase()}-${new Date().getFullYear()}-0001`,
            })}{' '}
            {t('platform.billing.tax_hint')}
          </p>
          <div className='grid gap-1'>
            <Label htmlFor='bill-footer'>
              {t('platform.billing.invoice_footer')}
            </Label>
            <Textarea
              id='bill-footer'
              rows={2}
              maxLength={300}
              value={v.invoice_footer}
              onChange={(e) => setV({ ...v, invoice_footer: e.target.value })}
            />
          </div>
          <div className='flex items-start justify-between gap-4'>
            <div className='grid gap-0.5'>
              <Label htmlFor='bill-email'>
                {t('platform.billing.invoice_email')}
              </Label>
              <p className='text-sm text-muted-foreground'>
                {t('platform.billing.invoice_email_hint')}
              </p>
            </div>
            <Switch
              id='bill-email'
              checked={v.invoice_email}
              onCheckedChange={(on) => setV({ ...v, invoice_email: on })}
            />
          </div>
          <Button
            type='submit'
            className='min-h-11 justify-self-start sm:min-h-9'
            disabled={save.isPending}
          >
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('common.save')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
