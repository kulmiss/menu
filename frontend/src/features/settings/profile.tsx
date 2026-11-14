import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  LANGUAGES,
  languageDir,
  type LanguageCode,
  persistLanguage,
  setLanguage,
} from '@/i18n'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { toast } from '@/lib/toast'
import { useDirection } from '@/context/direction-provider'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { ME_KEY } from '@/features/auth/api'
import { useMe } from '@/features/auth/use-me'
import { updateProfile } from './api'
import { SettingsSection } from './section'

export function ProfileSettings() {
  const { t } = useTranslation()
  const me = useMe()
  const qc = useQueryClient()
  const { setDir } = useDirection()
  const [name, setName] = useState(me.name)
  const [email, setEmail] = useState(me.email)
  const [password, setPassword] = useState('')
  const emailChanged = email.trim().toLowerCase() !== me.email.toLowerCase()
  const [locale, setLocale] = useState<LanguageCode>(
    (LANGUAGES.find((l) => l.code === me.locale)?.code ?? 'en') as LanguageCode
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setPending(true)
    setErrors({})
    try {
      await updateProfileAndLanguage()
      toast.success(
        emailChanged
          ? t('settings.email_change_sent', { email: email.trim() })
          : t('settings.saved')
      )
      setPassword('')
      // The address changes only after the link is clicked.
      setEmail(me.email)
      await qc.invalidateQueries({ queryKey: ME_KEY })
    } catch (error) {
      const fields = validationErrors(error)
      if (fields) setErrors(fields)
      else handleServerError(error)
    } finally {
      setPending(false)
    }
  }

  async function updateProfileAndLanguage() {
    await updateProfile({
      name,
      email: emailChanged ? email.trim() : me.email,
      locale,
      ...(emailChanged ? { current_password: password } : {}),
    })
    persistLanguage(locale)
    setDir(languageDir(locale))
    await setLanguage(locale)
  }

  return (
    <SettingsSection
      title={t('settings.profile_title')}
      intro={t('settings.profile_intro')}
    >
      <form onSubmit={submit} className='grid max-w-md gap-4'>
        <div className='grid gap-2'>
          <Label htmlFor='profile-name'>{t('auth.your_name')}</Label>
          <Input
            id='profile-name'
            value={name}
            onChange={(e) => setName(e.target.value)}
            maxLength={120}
            className='min-h-11 sm:min-h-9'
          />
          {errors.name && (
            <p className='text-sm text-destructive'>{errors.name}</p>
          )}
        </div>
        <div className='grid gap-2'>
          <Label htmlFor='profile-email'>{t('auth.email')}</Label>
          <Input
            id='profile-email'
            type='email'
            dir='ltr'
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className='min-h-11 sm:min-h-9'
          />
          {errors.email && (
            <p className='text-sm text-destructive'>{errors.email}</p>
          )}
          {me.pending_email && !emailChanged && (
            <p className='text-sm text-muted-foreground'>
              {t('settings.email_pending', { email: me.pending_email })}
            </p>
          )}
        </div>
        {emailChanged && (
          <div className='grid gap-2'>
            <Label htmlFor='profile-password'>
              {t('settings.current_password')}
            </Label>
            <Input
              id='profile-password'
              type='password'
              autoComplete='current-password'
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className='min-h-11 sm:min-h-9'
            />
            <p className='text-sm text-muted-foreground'>
              {t('settings.email_change_help')}
            </p>
            {errors.current_password && (
              <p className='text-sm text-destructive'>
                {errors.current_password}
              </p>
            )}
          </div>
        )}
        {LANGUAGES.length > 1 && (
          <div className='grid gap-2'>
            <Label>{t('settings.interface_language')}</Label>
            <Select
              value={locale}
              onValueChange={(v) => setLocale(v as LanguageCode)}
            >
              <SelectTrigger
                className='min-h-11 w-full sm:min-h-9'
                aria-label={t('settings.interface_language')}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {LANGUAGES.map((l) => (
                  <SelectItem key={l.code} value={l.code}>
                    {t(`language.${l.code}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
        <Button className='min-h-11 w-fit sm:min-h-9' disabled={pending}>
          {pending && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </form>
    </SettingsSection>
  )
}

/** News e-mails from the platform (owner, 2026-11-13); account e-mails are always sent. */
export function NewsSetting() {
  const { t } = useTranslation()
  const me = useMe()
  const qc = useQueryClient()
  const [on, setOn] = useState(me.news_subscribed !== false)
  const [busy, setBusy] = useState(false)

  return (
    <SettingsSection
      title={t('settings.news_title')}
      intro={t('settings.news_intro')}
    >
      <div className='flex items-center justify-between gap-4'>
        <Label htmlFor='news-switch'>{t('settings.news_label')}</Label>
        <Switch
          id='news-switch'
          checked={on}
          disabled={busy}
          onCheckedChange={async (next) => {
            setBusy(true)
            setOn(next)
            try {
              await api.put('/me/news', { subscribed: next })
              await qc.invalidateQueries({ queryKey: ME_KEY })
              toast.success(t('settings.saved'))
            } catch (err) {
              setOn(!next)
              handleServerError(err)
            } finally {
              setBusy(false)
            }
          }}
        />
      </div>
    </SettingsSection>
  )
}
