import { useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useNavigate } from '@tanstack/react-router'
import { KeyRound, Loader2, Mail } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyFieldErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
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
import { PasswordInput } from '@/components/password-input'
import { requestPasswordReset, resetPassword } from './api'
import { AuthCard, authLinkClass } from './auth-layout'

const forgotSchema = z.object({ email: z.email('validation.email') })

export function ForgotPasswordForm() {
  const { t } = useTranslation()
  const [pending, setPending] = useState(false)
  const [sentTo, setSentTo] = useState<string | null>(null)

  const form = useForm<z.infer<typeof forgotSchema>>({
    resolver: zodResolver(forgotSchema),
    defaultValues: { email: '' },
  })

  async function onSubmit({ email }: z.infer<typeof forgotSchema>) {
    setPending(true)
    try {
      await requestPasswordReset(email)
      setSentTo(email)
    } catch (error) {
      if (!applyFieldErrors(error, form.setError, ['email'])) {
        handleServerError(error)
      }
    } finally {
      setPending(false)
    }
  }

  if (sentTo) {
    return (
      <Alert role='status'>
        <Mail />
        <AlertDescription>
          {t('auth.reset_link_sent', { email: sentTo })}
        </AlertDescription>
      </Alert>
    )
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='grid gap-3'>
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
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2 min-h-11 sm:min-h-9' disabled={pending}>
          {pending ? <Loader2 className='animate-spin' /> : <Mail />}
          {t('auth.send_reset_link')}
        </Button>
      </form>
    </Form>
  )
}

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  return (
    <AuthCard
      title={t('auth.forgot_title')}
      description={t('auth.forgot_intro')}
      footer={
        <Link to='/auth/sign-in' className={`text-sm ${authLinkClass}`}>
          {t('auth.back_to_sign_in')}
        </Link>
      }
    >
      <ForgotPasswordForm />
    </AuthCard>
  )
}

const resetSchema = z
  .object({
    password: z.string().min(10, 'validation.password_min'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    message: 'validation.password_match',
    path: ['password_confirmation'],
  })

type ResetProps = { token: string; email: string }

export function ResetPasswordForm({ token, email }: ResetProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [pending, setPending] = useState(false)
  const [linkError, setLinkError] = useState<string | null>(null)

  const form = useForm<z.infer<typeof resetSchema>>({
    resolver: zodResolver(resetSchema),
    defaultValues: { password: '', password_confirmation: '' },
  })

  async function onSubmit(values: z.infer<typeof resetSchema>) {
    setPending(true)
    setLinkError(null)
    try {
      await resetPassword({ token, email, ...values })
      toast.success(t('auth.password_changed'))
      await navigate({ to: '/auth/sign-in', replace: true })
    } catch (error) {
      if (
        !applyFieldErrors(error, form.setError, [
          'password',
          'password_confirmation',
        ])
      ) {
        // Expired or used link: Laravel reports it on "email".
        const applied = applyFieldErrors(
          error,
          (_name: 'email', e) => setLinkError(e.message),
          ['email']
        )
        if (!applied) handleServerError(error)
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='grid gap-3'>
        <p className='text-sm text-muted-foreground' dir='ltr'>
          {email}
        </p>
        {linkError && (
          <Alert variant='destructive' role='alert'>
            <AlertDescription>
              {linkError}{' '}
              <Link to='/auth/forgot-password' className={authLinkClass}>
                {t('auth.request_new_link')}
              </Link>
            </AlertDescription>
          </Alert>
        )}
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.new_password')}</FormLabel>
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
          {pending ? <Loader2 className='animate-spin' /> : <KeyRound />}
          {t('auth.save_password')}
        </Button>
      </form>
    </Form>
  )
}

export function ResetPasswordPage({ token, email }: Partial<ResetProps>) {
  const { t } = useTranslation()
  return (
    <AuthCard title={t('auth.reset_title')}>
      {token && email ? (
        <ResetPasswordForm token={token} email={email} />
      ) : (
        <Alert variant='destructive' role='alert'>
          <AlertDescription>
            {t('auth.reset_link_invalid')}{' '}
            <Link to='/auth/forgot-password' className={authLinkClass}>
              {t('auth.request_new_link')}
            </Link>
          </AlertDescription>
        </Alert>
      )}
    </AuthCard>
  )
}
