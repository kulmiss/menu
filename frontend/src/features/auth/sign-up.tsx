import { useState } from 'react'
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
import { isValidSlug, slugify } from '@/lib/slug'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
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
import { invitationQueryOptions, ME_KEY, signUp } from './api'
import { AuthCard, authLinkClass, LegalNote } from './auth-layout'

const baseFields = {
  name: z.string().trim().min(1, 'validation.name_required').max(120),
  email: z.email('validation.email'),
  password: z.string().min(10, 'validation.password_min'),
  password_confirmation: z.string(),
}

const passwordsMatch = {
  message: 'validation.password_match',
  path: ['password_confirmation'],
}

const ownerSchema = z
  .object({
    ...baseFields,
    restaurant_name: z
      .string()
      .trim()
      .min(1, 'validation.restaurant_required')
      .max(120),
    slug: z.string().refine(isValidSlug, 'validation.slug'),
  })
  .refine((v) => v.password === v.password_confirmation, passwordsMatch)

// Signing up from an invitation joins that restaurant: no new one.
const invitedSchema = z
  .object({
    ...baseFields,
    restaurant_name: z.string(),
    slug: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, passwordsMatch)

type Values = z.infer<typeof ownerSchema>

const FIELDS = [
  'restaurant_name',
  'slug',
  'name',
  'email',
  'password',
  'password_confirmation',
] as const

type SignUpFormProps = {
  redirectTo?: string
  /** Invitation token and the invited address, when joining a team. */
  invite?: { token: string; email: string }
}

export function SignUpForm({ redirectTo, invite }: SignUpFormProps) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)
  // The slug follows the restaurant name until the owner edits it.
  const [slugEdited, setSlugEdited] = useState(false)

  const form = useForm<Values>({
    resolver: zodResolver(invite ? invitedSchema : ownerSchema),
    defaultValues: {
      restaurant_name: '',
      slug: '',
      name: '',
      email: invite?.email ?? '',
      password: '',
      password_confirmation: '',
    },
  })

  async function onSubmit(values: Values) {
    setPending(true)
    try {
      const { restaurant_name, slug, ...person } = values
      await signUp(
        invite
          ? { ...person, invitation_token: invite.token, locale: i18n.language }
          : { ...person, restaurant_name, slug, locale: i18n.language }
      )
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
        {!invite && (
          <>
            <FormField
              control={form.control}
              name='restaurant_name'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('auth.restaurant_name')}</FormLabel>
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
                  <FormLabel>{t('auth.slug')}</FormLabel>
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
                  <FormDescription>
                    {t('auth.slug_hint', {
                      url: `${window.location.host}/${field.value || '…'}`,
                    })}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </>
        )}
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
                  readOnly={!!invite}
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
          {invite ? t('invite.join') : t('auth.create_account')}
        </Button>
      </form>
    </Form>
  )
}

type SignUpPageProps = { redirect?: string; invite?: string }

export function SignUpPage({ redirect, invite }: SignUpPageProps) {
  const { t } = useTranslation()
  const invitation = useQuery({
    ...invitationQueryOptions(invite ?? ''),
    enabled: !!invite,
  })

  if (invite && invitation.isPending) {
    return (
      <AuthCard title={t('auth.sign_up_title')}>
        <Skeleton className='h-40 w-full' />
      </AuthCard>
    )
  }

  const joining = invite && invitation.data
  return (
    <AuthCard
      title={
        joining
          ? t('invite.title', { restaurant: invitation.data.restaurant })
          : t('auth.sign_up_title')
      }
      description={
        <>
          {joining ? t('invite.sign_up_intro') : t('auth.sign_up_intro')}{' '}
          <Link
            to='/auth/sign-in'
            search={{ redirect: joining ? `/auth/invite/${invite}` : redirect }}
            className={authLinkClass}
          >
            {t('auth.sign_in')}
          </Link>
        </>
      }
      footer={<LegalNote />}
    >
      {invite && !invitation.data && (
        <Alert variant='destructive' role='alert' className='mb-4'>
          <AlertDescription>{t('invite.invalid')}</AlertDescription>
        </Alert>
      )}
      <SignUpForm
        redirectTo={joining ? '/admin' : redirect}
        invite={
          joining ? { token: invite, email: invitation.data.email } : undefined
        }
      />
    </AuthCard>
  )
}
