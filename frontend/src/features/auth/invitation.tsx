import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { Check, Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useTenantStore } from '@/stores/tenant-store'
import { errorStatus } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  acceptInvitation,
  invitationQueryOptions,
  ME_KEY,
  meQueryOptions,
  signOut,
} from './api'
import { AuthCard, authLinkClass } from './auth-layout'

export function InvitationPage({ token }: { token: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const setCurrentTenantId = useTenantStore((s) => s.setCurrentTenantId)
  const [pending, setPending] = useState(false)

  const invitation = useQuery(invitationQueryOptions(token))
  // Signed out is a normal state here (meQueryOptions never redirects).
  const me = useQuery(meQueryOptions)
  const here = `/auth/invite/${token}`

  async function accept() {
    setPending(true)
    try {
      const tenant = await acceptInvitation(token)
      setCurrentTenantId(tenant.id)
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
      await navigate({ to: '/admin', replace: true })
    } catch (error) {
      handleServerError(error)
    } finally {
      setPending(false)
    }
  }

  async function switchAccount() {
    await signOut(queryClient)
    await navigate({ to: '/auth/sign-in', search: { redirect: here } })
  }

  const loading = invitation.isPending || me.isPending
  const data = invitation.data

  return (
    <AuthCard
      title={
        data
          ? t('invite.title', { restaurant: data.restaurant })
          : t('invite.title_plain')
      }
    >
      {loading ? (
        <div className='grid gap-3' aria-busy='true'>
          <Skeleton className='h-5 w-3/4' />
          <Skeleton className='h-11 w-full' />
        </div>
      ) : !data ? (
        <Alert variant='destructive' role='alert'>
          <AlertDescription>
            {errorStatus(invitation.error) === 404
              ? t('invite.invalid')
              : t('errors.generic')}
          </AlertDescription>
        </Alert>
      ) : me.data ? (
        me.data.email.toLowerCase() === data.email ? (
          <div className='grid gap-4'>
            <p className='text-sm text-muted-foreground'>
              {t('invite.as_role', { role: t(`roles.${data.role}`) })}
            </p>
            <Button
              className='min-h-11 sm:min-h-9'
              disabled={pending}
              onClick={accept}
            >
              {pending ? <Loader2 className='animate-spin' /> : <Check />}
              {t('invite.accept')}
            </Button>
          </div>
        ) : (
          <div className='grid gap-4'>
            <Alert role='alert'>
              <AlertDescription>
                {t('invite.wrong_account', {
                  invited: data.email,
                  current: me.data.email,
                })}
              </AlertDescription>
            </Alert>
            <Button
              variant='outline'
              className='min-h-11 sm:min-h-9'
              onClick={switchAccount}
            >
              {t('invite.switch_account')}
            </Button>
          </div>
        )
      ) : (
        <div className='grid gap-3'>
          <p className='text-sm text-muted-foreground'>
            {t('invite.signed_out', {
              email: data.email,
              role: t(`roles.${data.role}`),
            })}
          </p>
          <Button asChild className='min-h-11 sm:min-h-9'>
            <Link to='/auth/sign-up' search={{ invite: token }}>
              {t('invite.create_account')}
            </Link>
          </Button>
          <p className='text-center text-sm text-muted-foreground'>
            {t('invite.have_account')}{' '}
            <Link
              to='/auth/sign-in'
              search={{ redirect: here }}
              className={authLinkClass}
            >
              {t('auth.sign_in')}
            </Link>
          </p>
        </div>
      )}
    </AuthCard>
  )
}
