import { ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SecuritySettings } from './security'

/**
 * Shown instead of the restaurant admin when the Super Admin requires
 * two-factor for Owners and it isn't on yet (SECURITY_REPORT.md M17).
 */
export function TwoFactorGate() {
  const { t } = useTranslation()
  return (
    <main id='content' className='mx-auto grid max-w-xl gap-4 px-4 py-10'>
      <div role='alert' className='grid gap-2 text-center'>
        <ShieldCheck className='mx-auto size-8 text-primary' aria-hidden />
        <h1 className='text-xl font-semibold'>
          {t('settings.twofa_gate_title')}
        </h1>
        <p className='text-sm text-muted-foreground'>
          {t('settings.twofa_gate_text')}
        </p>
      </div>
      <SecuritySettings startSetup />
    </main>
  )
}
