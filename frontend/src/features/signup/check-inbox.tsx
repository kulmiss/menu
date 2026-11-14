import { use, useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { Loader2, LogOut, MailCheck, RefreshCw, Send } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  ME_KEY,
  meQueryOptions,
  resendVerificationEmail,
  signOut,
  type Me,
} from '@/features/auth/api'
import { AuthCard } from '@/features/auth/auth-layout'
import { changeSignUpEmail } from './api'

const texts = loadPageTexts('signup')
/** Seconds before "Resend email" works again (the server limits it too). */
export const RESEND_WAIT = 60

/**
 * Until the e-mail address is confirmed the admin shows only this screen
 * (owner, 2026-11-13): resend the link, correct the address, or sign out.
 */
export default function CheckInbox() {
  use(texts)
  const { t } = useTranslation()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const me = useQuery(meQueryOptions).data
  const email = me?.email ?? ''

  const [wait, setWait] = useState(0)
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (wait <= 0) return
    const timer = setTimeout(() => setWait((s) => s - 1), 1000)
    return () => clearTimeout(timer)
  }, [wait])

  const resend = useMutation({
    mutationFn: resendVerificationEmail,
    onSuccess: () => {
      setWait(RESEND_WAIT)
      toast.success(t('signup.inbox.sent', { email }))
    },
    onError: handleServerError,
  })

  const change = useMutation({
    mutationFn: changeSignUpEmail,
    onSuccess: (saved) => {
      qc.setQueryData<Me>(ME_KEY, (old) => old && { ...old, email: saved })
      setEditing(false)
      setWait(RESEND_WAIT)
      toast.success(t('signup.inbox.changed', { email: saved }))
    },
    onError: (err) => {
      const field = validationErrors(err)?.email
      if (field) setError(field)
      else handleServerError(err)
    },
  })

  const recheck = useMutation({
    mutationFn: () => qc.refetchQueries({ queryKey: ME_KEY }),
    onSuccess: () => {
      const fresh = qc.getQueryData<Me>(ME_KEY)
      if (!fresh?.email_verified)
        toast.message(t('signup.inbox.still_unverified'))
    },
  })

  const out = useMutation({
    mutationFn: () => signOut(qc),
    onSettled: () => navigate({ to: '/auth/sign-in', replace: true }),
  })

  return (
    <AuthCard title={t('signup.inbox.title')}>
      <div className='grid gap-4 text-sm'>
        <MailCheck className='size-8 text-primary' aria-hidden />
        <p role='status'>{t('signup.inbox.text', { email })}</p>
        <p className='text-muted-foreground'>{t('signup.inbox.expires')}</p>
        <p className='text-muted-foreground'>{t('signup.inbox.menu_off')}</p>

        <div className='grid gap-2 sm:grid-cols-2'>
          <Button
            className='min-h-11 sm:min-h-9'
            disabled={resend.isPending || wait > 0}
            onClick={() => resend.mutate()}
          >
            {resend.isPending ? <Loader2 className='animate-spin' /> : <Send />}
            {t('signup.inbox.resend')}
          </Button>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={recheck.isPending}
            onClick={() => recheck.mutate()}
          >
            <RefreshCw className={recheck.isPending ? 'animate-spin' : ''} />
            {t('signup.inbox.verified_check')}
          </Button>
        </div>
        {wait > 0 && (
          <p className='text-muted-foreground' aria-live='polite'>
            {t('signup.inbox.wait', { seconds: wait })}
          </p>
        )}

        {editing ? (
          <form
            className='grid gap-2'
            onSubmit={(e) => {
              e.preventDefault()
              setError(null)
              change.mutate(draft.trim())
            }}
          >
            <Label htmlFor='inbox-email'>{t('signup.inbox.new_email')}</Label>
            <Input
              id='inbox-email'
              type='email'
              autoComplete='email'
              required
              value={draft}
              aria-invalid={!!error}
              aria-describedby={error ? 'inbox-email-error' : undefined}
              onChange={(e) => setDraft(e.target.value)}
            />
            {error && (
              <p id='inbox-email-error' className='text-destructive'>
                {error}
              </p>
            )}
            <div className='flex flex-wrap gap-2'>
              <Button
                type='submit'
                className='min-h-11 sm:min-h-9'
                disabled={change.isPending}
              >
                {change.isPending && <Loader2 className='animate-spin' />}
                {t('signup.inbox.save_email')}
              </Button>
              <Button
                type='button'
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                onClick={() => setEditing(false)}
              >
                {t('signup.inbox.cancel')}
              </Button>
            </div>
          </form>
        ) : (
          <Button
            variant='link'
            className='min-h-11 justify-self-start px-0'
            onClick={() => {
              setDraft('')
              setError(null)
              setEditing(true)
            }}
          >
            {t('signup.inbox.change')}
          </Button>
        )}

        <Button
          variant='ghost'
          className='min-h-11 justify-self-start sm:min-h-9'
          disabled={out.isPending}
          onClick={() => out.mutate()}
        >
          <LogOut /> {t('auth.sign_out')}
        </Button>
      </div>
    </AuthCard>
  )
}
