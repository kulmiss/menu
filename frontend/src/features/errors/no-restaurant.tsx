import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { Store } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { signOut } from '@/features/auth/api'
import { AuthLayout } from '@/features/auth/auth-layout'

/** Signed in, but not (or no longer) a member of any restaurant. */
export function NoRestaurant() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)

  return (
    <AuthLayout>
      <div className='mx-auto flex max-w-sm flex-col items-center gap-3 text-center'>
        <Store className='size-10 text-muted-foreground' aria-hidden />
        <h2 className='text-lg font-semibold'>{t('no_restaurant.title')}</h2>
        <p className='text-sm text-muted-foreground'>
          {t('no_restaurant.text')}
        </p>
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
            await navigate({ to: '/auth/sign-in', replace: true })
          }}
        >
          {t('auth.sign_out')}
        </Button>
      </div>
    </AuthLayout>
  )
}
