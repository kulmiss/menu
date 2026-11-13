import { useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { Loader2, ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { applyFieldErrors, errorStatus } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { safeRedirect } from '@/lib/safe-redirect'
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
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from '@/components/ui/input-otp'
import { ME_KEY, twoFactorChallenge } from './api'
import { AuthCard, authLinkClass } from './auth-layout'

const codeSchema = z.object({
  code: z.string().regex(/^\d{6}$/, 'validation.code'),
})
const recoverySchema = z.object({
  code: z.string().trim().min(8, 'validation.recovery_code'),
})

type Values = { code: string }

export function TwoFactorForm({ redirectTo }: { redirectTo?: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)
  const [recovery, setRecovery] = useState(false)

  const form = useForm<Values>({
    resolver: zodResolver(recovery ? recoverySchema : codeSchema),
    defaultValues: { code: '' },
  })

  async function onSubmit({ code }: Values) {
    setPending(true)
    try {
      await twoFactorChallenge(
        recovery ? { recovery_code: code.trim() } : { code }
      )
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
      await navigate({ href: safeRedirect(redirectTo), replace: true })
    } catch (error) {
      // The sign-in step expired: start again.
      if (errorStatus(error) === 401) {
        await navigate({
          to: '/auth/sign-in',
          search: { redirect: redirectTo },
        })
        return
      }
      const applied = applyFieldErrors(
        error,
        (_name: 'code' | 'recovery_code', e) => form.setError('code', e),
        ['code', 'recovery_code']
      )
      if (!applied) handleServerError(error)
    } finally {
      setPending(false)
    }
  }

  function toggleMode() {
    setRecovery((r) => !r)
    form.reset({ code: '' })
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='grid gap-3'>
        <FormField
          control={form.control}
          name='code'
          render={({ field }) => (
            <FormItem>
              <FormLabel className={recovery ? undefined : 'sr-only'}>
                {recovery ? t('auth.recovery_code') : t('auth.auth_code')}
              </FormLabel>
              <FormControl>
                {recovery ? (
                  <Input
                    dir='ltr'
                    autoComplete='one-time-code'
                    autoCapitalize='none'
                    {...field}
                  />
                ) : (
                  <InputOTP
                    maxLength={6}
                    inputMode='numeric'
                    autoComplete='one-time-code'
                    autoFocus
                    {...field}
                    containerClassName='justify-between sm:[&>[data-slot="input-otp-group"]>div]:w-12'
                  >
                    <InputOTPGroup>
                      <InputOTPSlot index={0} />
                      <InputOTPSlot index={1} />
                      <InputOTPSlot index={2} />
                    </InputOTPGroup>
                    <InputOTPSeparator />
                    <InputOTPGroup>
                      <InputOTPSlot index={3} />
                      <InputOTPSlot index={4} />
                      <InputOTPSlot index={5} />
                    </InputOTPGroup>
                  </InputOTP>
                )}
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2 min-h-11 sm:min-h-9' disabled={pending}>
          {pending ? <Loader2 className='animate-spin' /> : <ShieldCheck />}
          {t('auth.verify')}
        </Button>
        <Button
          type='button'
          variant='link'
          className='min-h-11 sm:min-h-9'
          onClick={toggleMode}
        >
          {recovery ? t('auth.use_auth_code') : t('auth.use_recovery_code')}
        </Button>
      </form>
    </Form>
  )
}

export function TwoFactorPage({ redirect }: { redirect?: string }) {
  const { t } = useTranslation()
  return (
    <AuthCard
      title={t('auth.two_factor_title')}
      description={t('auth.two_factor_intro')}
      footer={
        <Link to='/auth/sign-in' className={`text-sm ${authLinkClass}`}>
          {t('auth.back_to_sign_in')}
        </Link>
      }
    >
      <TwoFactorForm redirectTo={redirect} />
    </AuthCard>
  )
}
