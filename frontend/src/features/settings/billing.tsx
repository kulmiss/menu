import { useQuery } from '@tanstack/react-query'
import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useCurrentTenant } from '@/features/auth/use-me'
import { SettingsSection as Section } from './section'

type Billing = {
  plan: {
    name: string
    price_minor: number
    currency: string
    interval: 'month' | 'year'
  } | null
  paid_until: string | null
  trial_ends_at: string | null
  state: string
  invoices: {
    id: number
    number: string
    status: 'paid' | 'void'
    issued_at: string
    period_start: string
    period_end: string
    total_minor: number
    currency: string
  }[]
}

const amount = (minor: number, currency: string) =>
  `${currency} ${(minor / 100).toLocaleString(undefined, {
    minimumFractionDigits: minor % 100 ? 2 : 0,
  })}`

/** Settings → Billing (owner only): plan, paid until and invoices to download. */
export function BillingSettings() {
  const { t, i18n } = useTranslation()
  const tenant = useCurrentTenant()
  const id = tenant?.id ?? 0
  const billing = useQuery({
    queryKey: ['tenant', id, 'billing'],
    queryFn: async () =>
      (await api.get<{ data: Billing }>(tenantUrl(id, 'billing'))).data.data,
    enabled: !!tenant,
  })
  const date = (d: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
      new Date(d)
    )
  const b = billing.data

  return (
    <>
      <Section
        title={t('settings.billing.plan_title')}
        intro={t('settings.billing.plan_intro')}
      >
        {!b ? (
          <Skeleton className='h-16' />
        ) : (
          <dl className='grid gap-3 text-sm sm:grid-cols-3'>
            <div>
              <dt className='text-muted-foreground'>
                {t('settings.billing.plan')}
              </dt>
              <dd className='text-base font-semibold'>
                {b.plan?.name ?? t('settings.billing.no_plan')}
              </dd>
            </div>
            <div>
              <dt className='text-muted-foreground'>
                {b.paid_until
                  ? t('settings.billing.paid_until')
                  : t('settings.billing.trial_until')}
              </dt>
              <dd className='text-base font-semibold'>
                {b.paid_until
                  ? date(b.paid_until)
                  : b.trial_ends_at
                    ? date(b.trial_ends_at)
                    : t('settings.billing.no_end')}
              </dd>
            </div>
            {b.plan && b.plan.price_minor > 0 && (
              <div>
                <dt className='text-muted-foreground'>
                  {t('settings.billing.price')}
                </dt>
                <dd className='text-base font-semibold'>
                  {amount(b.plan.price_minor, b.plan.currency)}{' '}
                  <span className='text-sm font-normal text-muted-foreground'>
                    {t(`settings.billing.per_${b.plan.interval}`)}
                  </span>
                </dd>
              </div>
            )}
          </dl>
        )}
      </Section>
      <Section
        title={t('settings.billing.invoices_title')}
        intro={t('settings.billing.invoices_intro')}
      >
        {!b ? (
          <Skeleton className='h-24' />
        ) : b.invoices.length === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('settings.billing.no_invoices')}
          </p>
        ) : (
          <ul className='divide-y rounded-md border text-sm'>
            {b.invoices.map((inv) => (
              <li
                key={inv.id}
                className='flex flex-wrap items-center justify-between gap-2 px-3 py-2'
              >
                <a
                  href={`${api.defaults.baseURL}${tenantUrl(id, `billing/invoices/${inv.id}/pdf`)}`}
                  target='_blank'
                  rel='noopener'
                  className='inline-flex min-h-11 items-center gap-2 font-medium underline-offset-4 hover:underline sm:min-h-9'
                >
                  <FileText className='size-4' aria-hidden />
                  <span className='font-mono'>{inv.number}</span>
                  <span className='sr-only'>{t('settings.billing.pdf')}</span>
                </a>
                <span className='text-muted-foreground'>
                  {date(inv.period_start)} – {date(inv.period_end)}
                </span>
                <span className='flex items-center gap-2'>
                  {inv.status === 'void' && (
                    <Badge variant='destructive'>
                      {t('settings.billing.void')}
                    </Badge>
                  )}
                  <span className='font-semibold tabular-nums'>
                    {amount(inv.total_minor, inv.currency)}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        )}
      </Section>
    </>
  )
}
