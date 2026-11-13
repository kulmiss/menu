import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Loader2, Mail, XCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { isApiError, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { meQueryOptions } from '@/features/auth/api'
import { type MailSettings, mailQ, send, usePlatformMutation } from './api'

const PORTS = { none: 25, ssl: 465, tls: 587 } as const

/** Settings → Email: the SMTP server every platform e-mail goes through. */
export function EmailSettings() {
  const mail = useQuery(mailQ())
  if (!mail.data) return null
  return <EmailForm initial={mail.data} />
}

function EmailForm({ initial }: { initial: MailSettings }) {
  const { t } = useTranslation()
  const [v, setV] = useState({
    host: initial.host,
    port: String(initial.port),
    encryption: initial.encryption,
    username: initial.username,
    password: '',
    from_address: initial.from_address,
    from_name: initial.from_name,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(
    null
  )
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings/mail', body)
  )
  const reset = usePlatformMutation(() => send('delete', 'settings/mail'))
  const test = usePlatformMutation((to: string) =>
    send('post', 'settings/mail/test', { to })
  )
  // Only for the test; never saved. Starts with the signed-in admin's address.
  const me = useQuery(meQueryOptions)
  const [testTo, setTestTo] = useState<string | null>(null)
  const to = testTo ?? me.data?.email ?? ''

  const field = (
    k: Exclude<keyof typeof v, 'encryption'>,
    props: React.ComponentProps<typeof Input> = {}
  ) => (
    <div className='grid gap-1'>
      <Label htmlFor={`mail-${k}`}>{t(`platform.mail.${k}`)}</Label>
      <Input
        id={`mail-${k}`}
        value={v[k]}
        onChange={(x) => setV({ ...v, [k]: x.target.value })}
        aria-invalid={errors[k] ? true : undefined}
        {...props}
      />
      {errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>}
    </div>
  )

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        ...v,
        port: Number(v.port),
        // Empty keeps the saved password.
        password: v.password === '' ? null : v.password,
      })
      setV((x) => ({ ...x, password: '' }))
      toast.success(t('platform.saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  async function sendTest() {
    setResult(null)
    const address = to.trim()
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address)) {
      setResult({ ok: false, text: t('platform.mail.test_invalid') })
      return
    }
    try {
      const res = (await test.mutateAsync(address)) as {
        data: { message: string }
      }
      setResult({ ok: true, text: res.data.message })
    } catch (err) {
      const status = isApiError(err) ? err.response?.status : undefined
      const fields = validationErrors(err)
      const message = isApiError(err)
        ? (err.response?.data as { message?: string } | undefined)?.message
        : undefined
      setResult({
        ok: false,
        text:
          status === 429
            ? t('platform.mail.test_limit')
            : (fields?.to ??
              message ??
              t('platform.mail.test_failed', { email: address })),
      })
    }
  }

  return (
    <Card className='mt-6 max-w-2xl'>
      <CardHeader>
        <CardTitle className='flex items-center gap-2'>
          <Mail className='size-5' aria-hidden /> {t('platform.mail.title')}
        </CardTitle>
        <p className='text-sm text-muted-foreground'>
          {initial.source === 'platform'
            ? t('platform.mail.using_platform')
            : t('platform.mail.using_env', {
                host: initial.env_host || initial.env_mailer,
              })}
        </p>
      </CardHeader>
      <CardContent>
        <form className='grid gap-4' onSubmit={submit}>
          <div className='grid gap-4 sm:grid-cols-2'>
            {field('host', { placeholder: 'smtp.example.com', required: true })}
            <div className='grid gap-1'>
              <Label htmlFor='mail-encryption'>
                {t('platform.mail.encryption')}
              </Label>
              <Select
                value={v.encryption}
                onValueChange={(x: MailSettings['encryption']) =>
                  setV({ ...v, encryption: x, port: String(PORTS[x]) })
                }
              >
                <SelectTrigger
                  id='mail-encryption'
                  className='min-h-11 sm:min-h-9'
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(['tls', 'ssl', 'none'] as const).map((o) => (
                    <SelectItem key={o} value={o}>
                      {t(`platform.mail.encryptions.${o}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {field('port', {
              type: 'number',
              min: 1,
              max: 65535,
              required: true,
            })}
            {field('username', { autoComplete: 'off' })}
            {field('password', {
              type: 'password',
              autoComplete: 'new-password',
              placeholder: initial.has_password ? '••••••••' : '',
              'aria-describedby': 'mail-password-hint',
            })}
            {field('from_address', {
              type: 'email',
              placeholder: 'hello@example.com',
            })}
            {field('from_name')}
          </div>
          <p id='mail-password-hint' className='text-sm text-muted-foreground'>
            {initial.has_password
              ? t('platform.mail.password_saved')
              : t('platform.mail.password_hint')}
          </p>
          <div className='flex flex-wrap items-end gap-2'>
            <div className='grid min-w-56 flex-1 gap-1'>
              <Label htmlFor='mail-test-to'>{t('platform.mail.test_to')}</Label>
              <Input
                id='mail-test-to'
                type='email'
                inputMode='email'
                autoComplete='off'
                maxLength={254}
                value={to}
                className='min-h-11 sm:min-h-9'
                onChange={(x) => setTestTo(x.target.value)}
                onKeyDown={(x) => {
                  // Enter here sends the test, it doesn't save the settings.
                  if (x.key === 'Enter') {
                    x.preventDefault()
                    void sendTest()
                  }
                }}
              />
            </div>
            <Button
              type='button'
              variant='outline'
              className='min-h-11 sm:min-h-9'
              disabled={test.isPending || to.trim() === ''}
              onClick={sendTest}
            >
              {test.isPending && <Loader2 className='animate-spin' />}
              {t('platform.mail.send_test')}
            </Button>
          </div>
          <div className='flex flex-wrap gap-2'>
            <Button
              type='submit'
              className='min-h-11 sm:min-h-9'
              disabled={save.isPending}
            >
              {t('common.save')}
            </Button>
            {initial.source === 'platform' && (
              <Button
                type='button'
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                disabled={reset.isPending}
                onClick={async () => {
                  await reset.mutateAsync(undefined)
                  toast.success(t('platform.mail.reset_done'))
                }}
              >
                {t('platform.mail.reset')}
              </Button>
            )}
          </div>
          {result && (
            <p
              role={result.ok ? 'status' : 'alert'}
              className={
                result.ok
                  ? 'flex items-start gap-2 text-sm text-emerald-900 dark:text-emerald-100'
                  : 'flex items-start gap-2 text-sm text-destructive'
              }
            >
              {result.ok ? (
                <CheckCircle2 className='mt-0.5 size-4 shrink-0' aria-hidden />
              ) : (
                <XCircle className='mt-0.5 size-4 shrink-0' aria-hidden />
              )}
              <span className='break-words'>{result.text}</span>
            </p>
          )}
        </form>
      </CardContent>
    </Card>
  )
}
