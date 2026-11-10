import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { MailWarning } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { resendVerificationEmail } from './api'

/** Banner in the admin while the email address is not confirmed yet. */
export function VerifyEmailBanner({ email }: { email: string }) {
  const { t } = useTranslation()
  const [sent, setSent] = useState(false)
  const resend = useMutation({
    mutationFn: resendVerificationEmail,
    onSuccess: () => {
      setSent(true)
      toast.success(t('verify.sent', { email }))
    },
  })

  return (
    <div
      role='status'
      className='flex flex-wrap items-center gap-x-3 gap-y-1 border-b bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-50'
    >
      <MailWarning className='size-4 shrink-0' aria-hidden />
      <span className='flex-1'>{t('verify.banner', { email })}</span>
      <Button
        size='sm'
        variant='outline'
        className='min-h-11 bg-transparent sm:min-h-8'
        disabled={resend.isPending || sent}
        onClick={() => resend.mutate()}
      >
        {sent ? t('verify.sent_short') : t('verify.resend')}
      </Button>
    </div>
  )
}
