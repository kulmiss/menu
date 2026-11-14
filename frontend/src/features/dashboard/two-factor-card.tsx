import { Link } from '@tanstack/react-router'
import { ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { useMe } from '@/features/auth/use-me'

/**
 * "Protect your restaurant": shown to Owners and Managers until two-factor is
 * on (owner, 2026-11-14; SECURITY_REPORT.md M17). One tap opens the setup.
 */
export function TwoFactorCard({ manager }: { manager: boolean }) {
  const { t } = useTranslation()
  const me = useMe()
  if (!manager || me.two_factor_enabled || me.impersonation) return null

  return (
    <div
      role='status'
      className='mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-50'
    >
      <ShieldCheck className='size-6 shrink-0' aria-hidden />
      <div className='grid min-w-0 flex-1 gap-0.5'>
        <p className='font-semibold'>{t('dashboard.twofa_title')}</p>
        <p className='text-sm'>{t('dashboard.twofa_text')}</p>
      </div>
      <Button asChild className='min-h-11 sm:min-h-9'>
        <Link to='/admin/settings/security' search={{ setup: 1 }}>
          {t('dashboard.twofa_action')}
        </Link>
      </Button>
    </div>
  )
}
