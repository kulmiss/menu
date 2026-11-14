import { lazy, Suspense, useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { Loader2, UserPlus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { applyFieldErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { safeRedirect } from '@/lib/safe-redirect'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { PasswordInput } from '@/components/password-input'
import { invitationQueryOptions, ME_KEY, signUp } from './api'
import { AuthCard, authLinkClass, LegalNote } from './auth-layout'

// Signing up from an invitation joins that restaurant: no new one. New
// restaurants sign up on their own page (features/signup).
const schema = z
  .object({
    name: z.string().trim().min(1, 'validation.name_required').max(120),
    email: z.email('validation.email'),
    password: z.string().min(10, 'validation.password_min'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    message: 'validation.password_match',
    path: ['password_confirmation'],
  })

type Values = z.infer<typeof schema>

const FIELDS = ['name', 'email', 'password', 'password_confirmation'] as const

type SignUpFormProps = {
  redirectTo?: string
  /** Invitation token and the invited address. */
  invite: { token: string; email: string }
}

export function SignUpForm({ redirectTo, invite }: SignUpFormProps) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      email: invite.email,
      password: '',
      password_confirmation: '',
    },
  })

  async function onSubmit(values: Values) {
    setPending(true)
    try {
      await signUp({
        ...values,
        invitation_token: invite.token,
        locale: i18n.language,
      })
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
      await navigate({ href: safeRedirect(redirectTo), replace: true })
    } catch (error) {
      if (!applyFieldErrors(error, form.setError, FIELDS)) {
        handleServerError(error)
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='grid gap-3'>
        <FormField
          control={form.control}
          name='name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.your_name')}</FormLabel>
              <FormControl>
                <Input autoComplete='name' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='email'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.email')}</FormLabel>
              <FormControl>
                <Input
                  type='email'
                  autoComplete='email'
                  placeholder={t('auth.email_placeholder')}
                  readOnly
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.password')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete='new-password' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password_confirmation'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.password_confirm')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete='new-password' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2 min-h-11 sm:min-h-9' disabled={pending}>
          {pending ? <Loader2 className='animate-spin' /> : <UserPlus />}
          {t('invite.join')}
        </Button>
      </form>
    </Form>
  )
}

// New restaurants: own page and texts, downloaded only when opened.
const OwnerSignUpPage = lazy(() =>
  import('@/features/signup/owner-sign-up').then((m) => ({
    default: m.OwnerSignUpPage,
  }))
)

type SignUpPageProps = { redirect?: string; invite?: string }

export function SignUpPage({ redirect, invite }: SignUpPageProps) {
  if (!invite) {
    return (
      <Suspense fallback={null}>
        <OwnerSignUpPage redirect={redirect} />
      </Suspense>
    )
  }
  return <InvitedSignUpPage invite={invite} />
}

function InvitedSignUpPage({ invite }: { invite: string }) {
  const { t } = useTranslation()
  const invitation = useQuery(invitationQueryOptions(invite))

  if (invitation.isPending) {
    return (
      <AuthCard title={t('auth.sign_up_title')}>
        <Skeleton className='h-40 w-full' />
      </AuthCard>
    )
  }

  if (!invitation.data) {
    return (
      <AuthCard title={t('invite.title_plain')}>
        <Alert variant='destructive' role='alert' className='mb-4'>
          <AlertDescription>{t('invite.invalid')}</AlertDescription>
        </Alert>
        <Link to='/auth/sign-in' className={authLinkClass}>
          {t('auth.sign_in')}
        </Link>
      </AuthCard>
    )
  }

  return (
    <AuthCard
      title={t('invite.title', { restaurant: invitation.data.restaurant })}
      description={
        <>
          {t('invite.sign_up_intro')}{' '}
          <Link
            to='/auth/sign-in'
            search={{ redirect: `/auth/invite/${invite}` }}
            className={authLinkClass}
          >
            {t('auth.sign_in')}
          </Link>
        </>
      }
      footer={<LegalNote />}
    >
      <SignUpForm
        redirectTo='/admin'
        invite={{ token: invite, email: invitation.data.email }}
      />
    </AuthCard>
  )
}
