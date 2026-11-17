import { use, useState } from 'react'
import { z } from 'zod'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { Loader2, MailCheck, UserPlus } from 'lucide-react'
import { Trans, useTranslation } from 'react-i18next'
import { applyFieldErrors, errorStatus } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { previewSlug } from '@/lib/slug'
import { cn } from '@/lib/utils'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { PasswordInput } from '@/components/password-input'
import { meQueryOptions, ME_KEY } from '@/features/auth/api'
import { AuthCard, authLinkClass } from '@/features/auth/auth-layout'
import { GoogleOption } from '@/features/auth/google-button'
import {
  passwordStrength,
  registerOwner,
  signUpConfigQuery,
  type SignUpConfig,
} from './api'
import { Turnstile } from './turnstile'

// Shortest form (owner, 2026-11-17): name, restaurant, e-mail, password and
// the terms. The menu address comes from the restaurant name and the currency
// from Settings; both can be changed later. The password has "Show" instead
// of a second box.
const schema = z.object({
  name: z.string().trim().min(2, 'validation.name_required').max(120),
  restaurant_name: z
    .string()
    .trim()
    .min(2, 'validation.restaurant_required')
    .max(120),
  email: z.email('validation.email'),
  password: z.string().min(10, 'validation.password_min'),
  terms: z.boolean().refine((v) => v, 'signup.terms_required'),
  website: z.string(),
})

type Values = z.infer<typeof schema>

const FIELDS = [
  'name',
  'restaurant_name',
  'email',
  'password',
  'terms',
] as const

const linkClass = 'underline underline-offset-4 hover:text-primary'

/** Public "Create a free account" page (owner, 2026-11-13). */
const texts = loadPageTexts('signup')

export function OwnerSignUpPage({ redirect }: { redirect?: string }) {
  use(texts)
  const { t } = useTranslation()
  const config = useQuery(signUpConfigQuery)
  const [sentTo, setSentTo] = useState<string | null>(null)

  const signInLink = (
    <Link to='/auth/sign-in' search={{ redirect }} className={authLinkClass}>
      {t('auth.sign_in')}
    </Link>
  )

  if (sentTo) {
    return (
      <AuthCard title={t('signup.inbox.title')}>
        <div className='grid gap-3 text-sm' role='status'>
          <MailCheck className='size-8 text-primary' aria-hidden />
          <p>{t('signup.inbox.text_public')}</p>
          <p className='text-muted-foreground'>{t('signup.inbox.expires')}</p>
          <p>{signInLink}</p>
        </div>
      </AuthCard>
    )
  }

  if (!config.data) {
    return (
      <AuthCard title={t('signup.title')}>
        {config.isError ? (
          <Alert variant='destructive' role='alert'>
            <AlertDescription>{t('errors.generic')}</AlertDescription>
          </Alert>
        ) : (
          <Skeleton className='h-96 w-full' />
        )}
      </AuthCard>
    )
  }

  if (!config.data.open) {
    return (
      <AuthCard
        title={t('signup.closed_title')}
        description={
          <>
            {t('signup.closed_text')} {signInLink}
          </>
        }
      >
        {null}
      </AuthCard>
    )
  }

  return (
    <AuthCard
      title={t('signup.title')}
      description={
        <>
          {t('signup.intro')} {signInLink}
        </>
      }
    >
      <GoogleOption label={t('auth.google.or_email')} />
      <OwnerSignUpForm config={config.data} onCheckInbox={setSentTo} />
    </AuthCard>
  )
}

type FormProps = {
  config: SignUpConfig
  onCheckInbox: (email: string) => void
}

export function OwnerSignUpForm({ config, onCheckInbox }: FormProps) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)
  const [token, setToken] = useState('')
  const [resetCaptcha, setResetCaptcha] = useState(0)
  const [captchaError, setCaptchaError] = useState<string | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      restaurant_name: '',
      email: '',
      password: '',
      terms: false,
      website: '',
    },
  })

  const password = useWatch({ control: form.control, name: 'password' })

  async function onSubmit(values: Values) {
    if (config.turnstile_site_key && !token) {
      setCaptchaError(t('signup.captcha_loading'))
      return
    }
    setPending(true)
    setCaptchaError(null)
    try {
      await registerOwner({
        ...values,
        email: values.email.trim().toLowerCase(),
        locale: i18n.language,
        turnstile: token || undefined,
      })
      // A new account is signed in straight away; an address that already
      // has an account is not (the answer looks the same on purpose).
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
      const me = await queryClient.fetchQuery(meQueryOptions).catch(() => null)
      if (me) {
        await navigate({ to: '/admin', replace: true })
      } else {
        onCheckInbox(values.email)
      }
    } catch (error) {
      setResetCaptcha((n) => n + 1)
      const fieldErrors = applyFieldErrors(error, form.setError, FIELDS)
      const turnstile = (
        error as { response?: { data?: { errors?: { turnstile?: string[] } } } }
      ).response?.data?.errors?.turnstile?.[0]
      if (turnstile) setCaptchaError(turnstile)
      else if (!fieldErrors || errorStatus(error) !== 422)
        handleServerError(error)
    } finally {
      setPending(false)
    }
  }

  const strength = passwordStrength(password)
  const level = (['weak', 'weak', 'fair', 'good', 'strong'] as const)[strength]

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className='grid gap-3'
        noValidate
      >
        <FormField
          control={form.control}
          name='name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('signup.your_name')}</FormLabel>
              <FormControl>
                <Input autoComplete='name' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='restaurant_name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('signup.restaurant_name')}</FormLabel>
              <FormControl>
                <Input autoComplete='organization' {...field} />
              </FormControl>
              <FormDescription>
                {field.value.trim() !== '' &&
                  `${t('signup.address_preview', {
                    url: `${window.location.host}/${previewSlug(field.value)}`,
                  })} `}
                {t('signup.currency_note', {
                  currency: config.default_currency,
                })}
              </FormDescription>
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
              {password.length > 0 && (
                <StrengthMeter
                  value={strength}
                  label={t('signup.strength.label', {
                    level: t(`signup.strength.${level}`),
                  })}
                />
              )}
              <FormDescription>{t('signup.password_hint')}</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        {/* Honeypot: hidden from people and screen readers; bots fill it. */}
        <div
          aria-hidden='true'
          className='absolute -start-[9999px] h-px w-px overflow-hidden'
        >
          <label htmlFor='signup-website'>{t('signup.website')}</label>
          <input
            id='signup-website'
            tabIndex={-1}
            autoComplete='off'
            {...form.register('website')}
          />
        </div>
        <FormField
          control={form.control}
          name='terms'
          render={({ field }) => (
            <FormItem>
              <div className='flex items-start gap-3'>
                <FormControl>
                  <Checkbox
                    checked={field.value}
                    onCheckedChange={(v) => field.onChange(v === true)}
                    aria-label={t('signup.terms_plain')}
                    className='mt-0.5 size-5'
                  />
                </FormControl>
                <span className='text-sm leading-snug'>
                  <Trans
                    i18nKey='signup.terms'
                    components={{
                      terms: (
                        <a
                          href='/terms'
                          target='_blank'
                          rel='noreferrer'
                          className={linkClass}
                        />
                      ),
                      privacy: (
                        <a
                          href='/privacy'
                          target='_blank'
                          rel='noreferrer'
                          className={linkClass}
                        />
                      ),
                    }}
                  />
                </span>
              </div>
              <FormMessage />
            </FormItem>
          )}
        />
        {config.turnstile_site_key && (
          <div className='grid gap-1'>
            <Turnstile
              siteKey={config.turnstile_site_key}
              onToken={setToken}
              resetKey={resetCaptcha}
            />
            {captchaError && (
              <p className='text-sm text-destructive' role='alert'>
                {captchaError}
              </p>
            )}
          </div>
        )}
        <Button className='mt-2 min-h-11 sm:min-h-9' disabled={pending}>
          {pending ? <Loader2 className='animate-spin' /> : <UserPlus />}
          {t('signup.submit')}
        </Button>
      </form>
    </Form>
  )
}

function StrengthMeter({ value, label }: { value: number; label: string }) {
  return (
    <div className='grid gap-1'>
      <div className='flex gap-1' aria-hidden>
        {[1, 2, 3, 4].map((n) => (
          <span
            key={n}
            className={cn(
              'h-1.5 flex-1 rounded-full bg-muted',
              n <= value &&
                (value <= 1
                  ? 'bg-red-600'
                  : value === 2
                    ? 'bg-amber-500'
                    : 'bg-emerald-600')
            )}
          />
        ))}
      </div>
      <p className='text-xs text-muted-foreground' aria-live='polite'>
        {label}
      </p>
    </div>
  )
}
