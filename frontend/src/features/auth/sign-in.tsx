import { use, useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { Loader2, LogIn } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { applyFieldErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { safeRedirect } from '@/lib/safe-redirect'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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
import { ME_KEY, signIn } from './api'
import { AuthCard, authLinkClass, LegalNote } from './auth-layout'

const schema = z.object({
  email: z.email('validation.email'),
  password: z.string().min(1, 'validation.password_required'),
  remember: z.boolean(),
})

type Values = z.infer<typeof schema>

export function SignInForm({ redirectTo }: { redirectTo?: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '', remember: true },
  })

  async function onSubmit(values: Values) {
    setPending(true)
    try {
      const needsCode = await signIn(values)
      if (needsCode) {
        await navigate({
          to: '/auth/two-factor',
          search: { redirect: redirectTo },
        })
        return
      }
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
      await navigate({ href: safeRedirect(redirectTo), replace: true })
    } catch (error) {
      if (!applyFieldErrors(error, form.setError, ['email', 'password'])) {
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
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem className='relative'>
              <FormLabel>{t('auth.password')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete='current-password' {...field} />
              </FormControl>
              <FormMessage />
              <Link
                to='/auth/forgot-password'
                className='absolute inset-e-0 -top-0.5 text-sm font-medium text-muted-foreground hover:opacity-75'
              >
                {t('auth.forgot_link')}
              </Link>
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='remember'
          render={({ field }) => (
            <FormItem className='flex items-center gap-2'>
              <FormControl>
                <Checkbox
                  checked={field.value}
                  onCheckedChange={(v) => field.onChange(v === true)}
                />
              </FormControl>
              <FormLabel className='font-normal'>
                {t('auth.remember')}
              </FormLabel>
            </FormItem>
          )}
        />
        <Button className='mt-2 min-h-11 sm:min-h-9' disabled={pending}>
          {pending ? <Loader2 className='animate-spin' /> : <LogIn />}
          {t('auth.sign_in')}
        </Button>
      </form>
    </Form>
  )
}

// "Create a free account" and the e-mail confirmation messages.
const texts = loadPageTexts('signup')
/** Where the e-mail confirmation link sent the visitor (VerifyEmailController). */
const OUTCOMES = ['done', 'already', 'expired', 'invalid']

type SignInPageProps = { redirect?: string; verify?: string }

export function SignInPage({ redirect, verify: raw }: SignInPageProps) {
  use(texts)
  const { t } = useTranslation()
  const verify = raw && OUTCOMES.includes(raw) ? raw : undefined
  const good = verify === 'done' || verify === 'already'
  return (
    <AuthCard
      title={t('auth.sign_in')}
      description={
        <>
          {t('auth.sign_in_intro')}{' '}
          <Link
            to='/auth/sign-up'
            search={{ redirect }}
            className={authLinkClass}
          >
            {t('signup.create_link')}
          </Link>
        </>
      }
      footer={<LegalNote />}
    >
      {verify && (
        <Alert
          variant={good ? 'default' : 'destructive'}
          role={good ? 'status' : 'alert'}
          className='mb-4'
        >
          <AlertDescription>{t(`signup.verify.${verify}`)}</AlertDescription>
        </Alert>
      )}
      <SignInForm redirectTo={redirect} />
    </AuthCard>
  )
}
