import { use, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { Loader2, UserPlus } from 'lucide-react'
import { Trans, useTranslation } from 'react-i18next'
import { api, ensureCsrfCookie, errorStatus, validationErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { previewSlug } from '@/lib/slug'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { ME_KEY } from './api'
import { AuthCard } from './auth-layout'
import { GoogleButton } from './google-button'

const texts = loadPageTexts('signup')
const linkClass = 'underline underline-offset-4 hover:text-primary'

type Pending = { email: string; name: string; terms: boolean; privacy: boolean }

/**
 * "Continue with Google" with a new e-mail (owner, 2026-11-17): only the
 * restaurant name and the Terms are left. The Google profile waits on the
 * server for 15 minutes.
 */
export function GoogleFinishPage() {
  use(texts)
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const pending = useQuery({
    queryKey: ['google', 'pending'],
    queryFn: async () =>
      (await api.get<{ data: Pending }>('/auth/google/pending')).data.data,
    retry: false,
  })
  const [restaurant, setRestaurant] = useState('')
  const [terms, setTerms] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [expired, setExpired] = useState(false)
  const [busy, setBusy] = useState(false)

  if (pending.isPending) {
    return (
      <AuthCard title={t('auth.google.finish.title')}>
        <Skeleton className='h-48 w-full' />
      </AuthCard>
    )
  }
  if (!pending.data || expired) {
    return (
      <AuthCard title={t('auth.google.finish.title')}>
        <div className='grid gap-4'>
          <Alert role='alert'>
            <AlertDescription>
              {t('auth.google.finish.expired')}
            </AlertDescription>
          </Alert>
          <GoogleButton />
        </div>
      </AuthCard>
    )
  }

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    const next: Record<string, string> = {}
    if (restaurant.trim().length < 2)
      next.restaurant_name = t('validation.restaurant_required')
    if (!terms) next.terms = t('signup.terms_required')
    setErrors(next)
    if (Object.keys(next).length) return
    setBusy(true)
    try {
      await ensureCsrfCookie()
      await api.post('/auth/google/finish', {
        restaurant_name: restaurant.trim(),
        terms: true,
        locale: i18n.language,
      })
      await qc.invalidateQueries({ queryKey: ME_KEY })
      await navigate({ to: '/admin', replace: true })
    } catch (error) {
      if (errorStatus(error) === 410) setExpired(true)
      else {
        const found = validationErrors(error)
        if (found) setErrors(found)
        else handleServerError(error)
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthCard
      title={t('auth.google.finish.title')}
      description={t('auth.google.finish.intro', { email: pending.data.email })}
    >
      <form className='grid gap-4' onSubmit={submit} noValidate>
        <div className='grid gap-1'>
          <Label htmlFor='g-restaurant'>
            {t('auth.google.finish.restaurant')}
          </Label>
          <Input
            id='g-restaurant'
            autoComplete='organization'
            autoFocus
            maxLength={120}
            value={restaurant}
            aria-invalid={!!errors.restaurant_name}
            onChange={(e) => setRestaurant(e.target.value)}
          />
          {restaurant.trim() !== '' && (
            <p className='text-sm text-muted-foreground'>
              {t('signup.address_preview', {
                url: `${window.location.host}/${previewSlug(restaurant)}`,
              })}
            </p>
          )}
          {errors.restaurant_name && (
            <p className='text-sm text-destructive'>{errors.restaurant_name}</p>
          )}
        </div>
        <div className='grid gap-1'>
          <div className='flex items-start gap-3'>
            <Checkbox
              id='g-terms'
              checked={terms}
              onCheckedChange={(v) => setTerms(v === true)}
              aria-label={t('signup.terms_plain')}
              className='mt-0.5 size-5'
            />
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
          {errors.terms && (
            <p className='text-sm text-destructive'>{errors.terms}</p>
          )}
        </div>
        <Button className='min-h-11 sm:min-h-9' disabled={busy}>
          {busy ? <Loader2 className='animate-spin' /> : <UserPlus />}
          {t('auth.google.finish.submit')}
        </Button>
      </form>
    </AuthCard>
  )
}
