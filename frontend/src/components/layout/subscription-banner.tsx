import { AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { type Tenant } from '@/features/auth/api'

/**
 * Grace / read-only / unavailable notice (PLAN.md §4.2). The server decides
 * the state; this only explains it.
 */
export function SubscriptionBanner({ tenant }: { tenant: Tenant }) {
  const { t, i18n } = useTranslation()
  const { state, shows_banner, stage_ends_at } = tenant.subscription
  if (!shows_banner) return null

  const date = stage_ends_at
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
        new Date(stage_ends_at)
      )
    : ''

  return (
    <div
      role='status'
      className='flex items-start gap-2 border-b bg-red-50 px-4 py-2 text-sm text-red-950 dark:bg-red-950 dark:text-red-50'
    >
      <AlertTriangle className='mt-0.5 size-4 shrink-0' aria-hidden />
      <span>{t(`subscription.${state}`, { date })}</span>
    </div>
  )
}
