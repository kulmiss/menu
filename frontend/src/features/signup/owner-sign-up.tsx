import { use, useEffect, useState } from 'react'
import { z } from 'zod'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { Check, Loader2, MailCheck, UserPlus, X } from 'lucide-react'
import { Trans, useTranslation } from 'react-i18next'
import { applyFieldErrors, errorStatus } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { isValidSlug, slugify } from '@/lib/slug'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { PasswordInput } from '@/components/password-input'
import { meQueryOptions, ME_KEY } from '@/features/auth/api'
import { AuthCard, authLinkClass } from '@/features/auth/auth-layout'
import {
  passwordStrength,
  registerOwner,
  signUpConfigQuery,
  slugCheckQuery,
  type SignUpConfig,
} from './api'
import { Turnstile } from './turnstile'

const schema = z
  .object({
    restaurant_name: z
      .string()
      .trim()
      .min(2, 'validation.restaurant_required')
      .max(120),
    slug: z.string().refine(isValidSlug, 'signup.slug_invalid'),
    name: z.string().trim().min(2, 'validation.name_required').max(120),
    email: z.email('validation.email'),
    password: z.string().min(10, 'validation.password_min'),
    password_confirmation: z.string(),
    currency: z.string().length(3),
    terms: z.boolean().refine((v) => v, 'signup.terms_required'),
    website: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    message: 'validation.password_match',
    path: ['password_confirmation'],
  })

type Values = z.infer<typeof schema>

const FIELDS = [
  'restaurant_name',
  'slug',
  'name',
  'email',
  'password',
  'password_confirmation',
  'currency',
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
      <OwnerSignUpForm config={config.data} onCheckInbox={setSentTo} />
    </AuthCard>
  )
}

function currencyName(code: string, locale: string): string {
  try {
    const name = new Intl.DisplayNames([locale], { type: 'currency' }).of(code)
    return name && name !== code ? `${code} — ${name}` : code
  } catch {
    return code
  }
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
  // The slug follows the restaurant name until the owner edits it.
  const [slugEdited, setSlugEdited] = useState(false)
  const [token, setToken] = useState('')
  const [resetCaptcha, setResetCaptcha] = useState(0)
  const [captchaError, setCaptchaError] = useState<string | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      restaurant_name: '',
      slug: '',
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      currency: config.currencies.includes(config.default_currency)
        ? config.default_currency
        : (config.currencies[0] ?? 'ETB'),
      terms: false,
      website: '',
    },
  })

  const slug = useWatch({ control: form.control, name: 'slug' })
  const password = useWatch({ control: form.control, name: 'password' })
  const check = useSlugCheck(slug)

  async function onSubmit(values: Values) {
    if (check.data && !check.data.available) {
      form.setError('slug', {
        type: 'server',
        message: t(`signup.slug_${check.data.reason ?? 'invalid'}`),
      })
      return
    }
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
          name='restaurant_name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('signup.restaurant_name')}</FormLabel>
              <FormControl>
                <Input
                  autoComplete='organization'
                  {...field}
                  onChange={(e) => {
                    field.onChange(e)
                    if (!slugEdited) {
                      form.setValue('slug', slugify(e.target.value))
                    }
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='slug'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('signup.slug')}</FormLabel>
              <FormControl>
                <Input
                  dir='ltr'
                  autoCapitalize='none'
                  spellCheck={false}
                  {...field}
                  onChange={(e) => {
                    setSlugEdited(true)
                    field.onChange(e.target.value.toLowerCase())
                  }}
                />
              </FormControl>
              <SlugStatus slug={field.value} check={check} />
              <FormDescription>
                {t('signup.slug_hint', {
                  url: `${window.location.host}/${field.value || '…'}`,
                })}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
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
        <FormField
          control={form.control}
          name='currency'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('signup.currency')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className='min-h-11 w-full sm:min-h-9'>
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {config.currencies.map((code) => (
                    <SelectItem key={code} value={code}>
                      {currencyName(code, i18n.language)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormDescription>{t('signup.currency_hint')}</FormDescription>
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

/** Asks the server about the address once typing pauses. */
function useSlugCheck(slug: string) {
  const [debounced, setDebounced] = useState(slug)
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(slug), 400)
    return () => clearTimeout(timer)
  }, [slug])
  const valid = isValidSlug(debounced)
  const query = useQuery({ ...slugCheckQuery(debounced), enabled: valid })
  return {
    data: debounced === slug && valid ? query.data : undefined,
    checking: slug !== debounced || (valid && query.isFetching),
  }
}

function SlugStatus({
  slug,
  check,
}: {
  slug: string
  check: ReturnType<typeof useSlugCheck>
}) {
  const { t } = useTranslation()
  if (!isValidSlug(slug)) return null
  let content: React.ReactNode = null
  if (check.checking) {
    content = (
      <>
        <Loader2 className='size-4 animate-spin' aria-hidden />
        {t('signup.slug_checking')}
      </>
    )
  } else if (check.data?.available) {
    content = (
      <>
        <Check className='size-4' aria-hidden />
        {t('signup.slug_available')}
      </>
    )
  } else if (check.data) {
    content = (
      <>
        <X className='size-4' aria-hidden />
        {t(`signup.slug_${check.data.reason ?? 'invalid'}`)}
      </>
    )
  }
  return (
    <p
      aria-live='polite'
      className={cn(
        'flex min-h-5 items-center gap-1 text-sm',
        check.data?.available === true &&
          !check.checking &&
          'text-emerald-700 dark:text-emerald-400',
        check.data?.available === false && !check.checking && 'text-destructive'
      )}
    >
      {content}
    </p>
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
