import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Loader2, ShieldCheck, ShieldOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { toast } from '@/lib/toast'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'
import { ME_KEY } from '@/features/auth/api'
import { useMe } from '@/features/auth/use-me'
import {
  confirmPassword,
  confirmTwoFactor,
  disableTwoFactor,
  enableTwoFactor,
  recoveryCodes,
  regenerateRecoveryCodes,
  twoFactorQr,
  twoFactorSecret,
  updatePassword,
} from './api'
import { SettingsSection } from './section'

export function SecuritySettings({
  startSetup = false,
}: {
  startSetup?: boolean
}) {
  return (
    <>
      <PasswordSection />
      <TwoFactorSection startSetup={startSetup} />
    </>
  )
}

function PasswordSection() {
  const { t } = useTranslation()
  const [values, setValues] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)
  const set =
    (k: keyof typeof values) => (e: React.ChangeEvent<HTMLInputElement>) =>
      setValues((v) => ({ ...v, [k]: e.target.value }))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setPending(true)
    setErrors({})
    try {
      await updatePassword(values)
      setValues({
        current_password: '',
        password: '',
        password_confirmation: '',
      })
      toast.success(t('settings.password_saved'))
    } catch (error) {
      const fields = validationErrors(error)
      if (fields) setErrors(fields)
      else handleServerError(error)
    } finally {
      setPending(false)
    }
  }

  const field = (
    k: keyof typeof values,
    label: string,
    autoComplete: string
  ) => (
    <div className='grid gap-2'>
      <Label htmlFor={`pw-${k}`}>{label}</Label>
      <PasswordInput
        id={`pw-${k}`}
        autoComplete={autoComplete}
        value={values[k]}
        onChange={set(k)}
      />
      {errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>}
    </div>
  )

  return (
    <SettingsSection
      title={t('settings.password_title')}
      intro={t('settings.password_intro')}
    >
      <form onSubmit={submit} className='grid max-w-md gap-4'>
        {field(
          'current_password',
          t('settings.current_password'),
          'current-password'
        )}
        {field('password', t('auth.new_password'), 'new-password')}
        {field(
          'password_confirmation',
          t('auth.password_confirm'),
          'new-password'
        )}
        <Button className='min-h-11 w-fit sm:min-h-9' disabled={pending}>
          {pending && <Loader2 className='animate-spin' />}
          {t('auth.save_password')}
        </Button>
      </form>
    </SettingsSection>
  )
}

type Step = 'idle' | 'setup' | 'codes'

/** Two-factor sign-in: optional for restaurants, required for Super Admins (QUESTIONS #28). */
function TwoFactorSection({ startSetup }: { startSetup: boolean }) {
  const { t } = useTranslation()
  const me = useMe()
  const qc = useQueryClient()
  const [step, setStep] = useState<Step>('idle')
  const [asking, setAsking] = useState<null | (() => Promise<void>)>(null)
  const [code, setCode] = useState('')
  const [codeError, setCodeError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const qr = useQuery({
    queryKey: ['2fa', 'qr'],
    queryFn: twoFactorQr,
    enabled: step === 'setup',
    gcTime: 0,
  })
  const secret = useQuery({
    queryKey: ['2fa', 'secret'],
    queryFn: twoFactorSecret,
    enabled: step === 'setup',
    gcTime: 0,
  })
  const codes = useQuery({
    queryKey: ['2fa', 'codes'],
    queryFn: recoveryCodes,
    enabled: step === 'codes',
    gcTime: 0,
  })

  const refreshMe = () => qc.invalidateQueries({ queryKey: ME_KEY })

  // One tap from the dashboard card or the reminder e-mail: ask for the password, then show the QR code.
  const started = useRef(false)
  useEffect(() => {
    if (!startSetup || started.current || me.two_factor_enabled) return
    started.current = true
    setAsking(() => async () => {
      await enableTwoFactor()
      setStep('setup')
    })
  }, [startSetup, me.two_factor_enabled])

  async function run(action: () => Promise<void>) {
    setBusy(true)
    try {
      await action()
    } catch (error) {
      handleServerError(error)
    } finally {
      setBusy(false)
    }
  }

  // Every change needs the password again: ask, then do it.
  const withPassword = (action: () => Promise<void>) => setAsking(() => action)

  async function confirmCode(e: React.FormEvent) {
    e.preventDefault()
    setCodeError(null)
    setBusy(true)
    try {
      await confirmTwoFactor(code.trim())
      await refreshMe()
      setCode('')
      setStep('codes')
      toast.success(t('settings.twofa_on'))
    } catch (error) {
      const fields = validationErrors(error)
      setCodeError(fields?.code ?? t('validation.code'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <SettingsSection
      title={t('settings.twofa_title')}
      intro={t('settings.twofa_intro')}
    >
      <div className='flex flex-wrap items-center gap-3'>
        {me.two_factor_enabled ? (
          <Badge className='gap-1'>
            <ShieldCheck className='size-3' aria-hidden />{' '}
            {t('settings.twofa_status_on')}
          </Badge>
        ) : (
          <Badge variant='outline' className='gap-1'>
            <ShieldOff className='size-3' aria-hidden />{' '}
            {t('settings.twofa_status_off')}
          </Badge>
        )}
        {me.is_super_admin && !me.two_factor_enabled && (
          <span className='text-sm text-destructive'>
            {t('settings.twofa_required')}
          </span>
        )}
      </div>

      {step === 'idle' && (
        <div className='flex flex-wrap gap-2'>
          {me.two_factor_enabled ? (
            <>
              <Button
                variant='outline'
                className='min-h-11 sm:min-h-9'
                disabled={busy}
                onClick={() =>
                  // Only hashes are stored (SECURITY_REPORT.md M5): new codes are the only way to see some.
                  withPassword(async () => {
                    await regenerateRecoveryCodes()
                    setStep('codes')
                  })
                }
              >
                {t('settings.twofa_show_codes')}
              </Button>
              <Button
                variant='destructive'
                className='min-h-11 sm:min-h-9'
                disabled={busy}
                onClick={() =>
                  withPassword(async () => {
                    await disableTwoFactor()
                    await refreshMe()
                    toast.success(t('settings.twofa_off'))
                  })
                }
              >
                {t('settings.twofa_disable')}
              </Button>
            </>
          ) : (
            <Button
              className='min-h-11 sm:min-h-9'
              disabled={busy}
              onClick={() =>
                withPassword(async () => {
                  await enableTwoFactor()
                  setStep('setup')
                })
              }
            >
              {t('settings.twofa_enable')}
            </Button>
          )}
        </div>
      )}

      {step === 'setup' && (
        <form onSubmit={confirmCode} className='grid max-w-md gap-4'>
          <p className='text-sm'>{t('settings.twofa_scan')}</p>
          {qr.data ? (
            // SVG made by our own server (Fortify), not user content.
            <div
              className='w-fit rounded-md bg-white p-3'
              role='img'
              aria-label={t('settings.twofa_qr')}
              dangerouslySetInnerHTML={{ __html: qr.data.svg }}
            />
          ) : (
            <div className='size-48 animate-pulse rounded-md bg-muted' />
          )}
          {secret.data && (
            <p className='text-sm text-muted-foreground'>
              {t('settings.twofa_manual')}{' '}
              <code
                className='rounded bg-muted px-1 font-mono break-all'
                dir='ltr'
              >
                {secret.data}
              </code>
            </p>
          )}
          <div className='grid gap-2'>
            <Label htmlFor='twofa-code'>{t('auth.auth_code')}</Label>
            <Input
              id='twofa-code'
              inputMode='numeric'
              autoComplete='one-time-code'
              dir='ltr'
              value={code}
              onChange={(e) => setCode(e.target.value)}
              className='min-h-11 w-40 sm:min-h-9'
            />
            {codeError && (
              <p className='text-sm text-destructive'>{codeError}</p>
            )}
          </div>
          <div className='flex gap-2'>
            <Button
              className='min-h-11 sm:min-h-9'
              disabled={busy || code.trim().length < 6}
            >
              {busy && <Loader2 className='animate-spin' />}
              {t('auth.verify')}
            </Button>
            <Button
              type='button'
              variant='ghost'
              className='min-h-11 sm:min-h-9'
              onClick={() =>
                run(async () => {
                  await disableTwoFactor()
                  setStep('idle')
                })
              }
            >
              {t('common.cancel')}
            </Button>
          </div>
        </form>
      )}

      {step === 'codes' && (
        <div className='grid max-w-md gap-3'>
          <Alert>
            <AlertDescription>
              {t('settings.twofa_codes_intro')}
            </AlertDescription>
          </Alert>
          <ul
            className='grid grid-cols-2 gap-2 rounded-md bg-muted p-3 font-mono text-sm'
            dir='ltr'
          >
            {(codes.data ?? []).map((c) => (
              <li key={c}>{c}</li>
            ))}
          </ul>
          {codes.isSuccess && codes.data.length === 0 && (
            <p className='text-sm text-muted-foreground'>
              {t('settings.twofa_codes_gone')}
            </p>
          )}
          <div className='flex flex-wrap gap-2'>
            <Button
              variant='outline'
              className='min-h-11 sm:min-h-9'
              disabled={busy}
              onClick={() =>
                run(async () => {
                  await regenerateRecoveryCodes()
                  await codes.refetch()
                })
              }
            >
              {t('settings.twofa_new_codes')}
            </Button>
            <Button
              className='min-h-11 sm:min-h-9'
              onClick={() => setStep('idle')}
            >
              {t('settings.done')}
            </Button>
          </div>
        </div>
      )}

      <PasswordPrompt
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        onConfirmed={async () => {
          const action = asking
          setAsking(null)
          if (action) await run(action)
        }}
      />
    </SettingsSection>
  )
}

function PasswordPrompt({
  open,
  onOpenChange,
  onConfirmed,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onConfirmed: () => Promise<void>
}) {
  const { t } = useTranslation()
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await confirmPassword(password)
      setPassword('')
      await onConfirmed()
    } catch (err) {
      setError(validationErrors(err)?.password ?? t('settings.wrong_password'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-sm'>
        <form onSubmit={submit} className='grid gap-4'>
          <DialogHeader className='text-start'>
            <DialogTitle>{t('settings.confirm_password')}</DialogTitle>
            <DialogDescription>
              {t('settings.confirm_password_intro')}
            </DialogDescription>
          </DialogHeader>
          <div className='grid gap-2'>
            <Label htmlFor='confirm-pw'>{t('auth.password')}</Label>
            <PasswordInput
              id='confirm-pw'
              autoComplete='current-password'
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoFocus
            />
            {error && <p className='text-sm text-destructive'>{error}</p>}
          </div>
          <DialogFooter>
            <Button
              className='min-h-11 sm:min-h-9'
              disabled={busy || password === ''}
            >
              {busy && <Loader2 className='animate-spin' />}
              {t('common.continue')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
