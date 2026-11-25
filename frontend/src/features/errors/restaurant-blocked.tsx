import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Ban } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { signInUrl } from '@/lib/area'
import { Button } from '@/components/ui/button'
import { signOut } from '@/features/auth/api'
import { AuthLayout } from '@/features/auth/auth-layout'

/**
 * Blocked by the Super Admin (owner, 2026-11-25): the owner can still sign in,
 * but the whole admin is replaced by this card, which shows the reason.
 */
export function RestaurantBlocked({ reason }: { reason?: string | null }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)

  return (
    <AuthLayout>
      <div className='mx-auto flex max-w-md flex-col items-center gap-3 text-center'>
        <Ban className='size-10 text-destructive' aria-hidden />
        <h2 className='text-lg font-semibold'>
          {t('restaurant_blocked.title')}
        </h2>
        <p className='text-sm text-muted-foreground'>
          {t('restaurant_blocked.text')}
        </p>
        {reason && (
          <div className='mt-1 w-full rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-start text-sm text-destructive'>
            <span className='font-medium'>
              {t('restaurant_blocked.reason')}
            </span>{' '}
            {reason}
          </div>
        )}
        <Button
          variant='outline'
          className='mt-2 min-h-11 sm:min-h-9'
          disabled={pending}
          onClick={async () => {
            setPending(true)
            try {
              await signOut(queryClient)
            } catch {
              // Leave anyway: the session is gone on this device.
            }
            window.location.assign(signInUrl())
          }}
        >
          {t('auth.sign_out')}
        </Button>
      </div>
    </AuthLayout>
  )
}
