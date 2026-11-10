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
import { validationErrors } from '@/lib/api'
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
        email !== me.email ? t('settings.email_changed') : t('settings.saved')
      )
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
    await updateProfile({ name, email, locale })
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
        </div>
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
