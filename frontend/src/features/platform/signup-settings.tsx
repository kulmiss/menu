import { useRef, useState } from 'react'
import { Loader2, Trash2, Upload } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
import { Textarea } from '@/components/ui/textarea'
import { send, usePlatformMutation } from './api'

/** The sign-up part of GET /platform/settings (owner, 2026-11-13). */
export type SignupSettingsData = {
  signup_open: boolean
  signup_plan_id: number | null
  signup_approval: boolean
  signup_notify: boolean
  turnstile_enabled: boolean
  turnstile_site_key: string
  turnstile_secret_set: boolean
  disposable_domains: string[]
  plans: { id: number; name: string; slug: string }[]
}

const FREE = 'free'

/** Settings → Sign-up: who can register, which plan, approval, anti-spam. */
export function SignupSettings({ initial }: { initial: SignupSettingsData }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings', body)
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [v, setV] = useState({
    signup_open: initial.signup_open,
    signup_plan_id: initial.signup_plan_id
      ? String(initial.signup_plan_id)
      : FREE,
    signup_approval: initial.signup_approval,
    signup_notify: initial.signup_notify,
    turnstile_enabled: initial.turnstile_enabled,
    turnstile_site_key: initial.turnstile_site_key ?? '',
    turnstile_secret: '',
    clear_turnstile_secret: false,
    disposable_domains: (initial.disposable_domains ?? []).join('\n'),
  })

  const toggle = (
    k: 'signup_open' | 'signup_approval' | 'signup_notify' | 'turnstile_enabled'
  ) => (
    <div className='flex items-start justify-between gap-4'>
      <div className='grid gap-0.5'>
        <Label htmlFor={`su-${k}`}>{t(`platform.signup.${k}`)}</Label>
        <p className='text-sm text-muted-foreground'>
          {t(`platform.signup.${k}_hint`)}
        </p>
      </div>
      <Switch
        id={`su-${k}`}
        checked={v[k]}
        onCheckedChange={(on) => setV({ ...v, [k]: on })}
      />
    </div>
  )
  const error = (k: string) =>
    errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        signup_open: v.signup_open,
        signup_plan_id:
          v.signup_plan_id === FREE ? null : Number(v.signup_plan_id),
        signup_approval: v.signup_approval,
        signup_notify: v.signup_notify,
        turnstile_enabled: v.turnstile_enabled,
        turnstile_site_key: v.turnstile_site_key.trim(),
        // Empty keeps the stored secret.
        turnstile_secret: v.turnstile_secret.trim(),
        clear_turnstile_secret: v.clear_turnstile_secret,
        disposable_domains: v.disposable_domains
          .split(/[\s,]+/)
          .map((d) => d.trim().toLowerCase())
          .filter(Boolean),
      })
      setV({ ...v, turnstile_secret: '', clear_turnstile_secret: false })
      toast.success(t('platform.saved'))
    } catch (err) {
      const found = validationErrors(err) ?? {}
      // "disposable_domains.3" → one message under the list.
      const merged: Record<string, string> = {}
      for (const [k, m] of Object.entries(found)) merged[k.split('.')[0]] ??= m
      setErrors(merged)
    }
  }

  const free = initial.plans.find((p) => p.slug === FREE)

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.signup.title')}</CardTitle>
        <CardDescription>{t('platform.signup.hint')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className='grid gap-5' onSubmit={submit}>
          {toggle('signup_open')}
          <div className='grid gap-1'>
            <Label htmlFor='su-plan'>{t('platform.signup.plan')}</Label>
            <Select
              value={v.signup_plan_id}
              onValueChange={(id) => setV({ ...v, signup_plan_id: id })}
            >
              <SelectTrigger
                id='su-plan'
                className='min-h-11 w-full sm:min-h-9'
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={FREE}>
                  {t('platform.signup.plan_free', {
                    name: free?.name ?? 'Free',
                  })}
                </SelectItem>
                {initial.plans
                  .filter((p) => p.slug !== FREE)
                  .map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
            <p className='text-sm text-muted-foreground'>
              {t('platform.signup.plan_hint')}
            </p>
            {error('signup_plan_id')}
          </div>
          {toggle('signup_approval')}
          {toggle('signup_notify')}

          <fieldset className='grid gap-3 rounded-md border p-3'>
            <legend className='px-1 text-sm font-medium'>
              {t('platform.signup.spam')}
            </legend>
            {toggle('turnstile_enabled')}
            {error('turnstile_enabled')}
            <div className='grid gap-3 sm:grid-cols-2'>
              <div className='grid gap-1'>
                <Label htmlFor='su-site-key'>
                  {t('platform.signup.site_key')}
                </Label>
                <Input
                  id='su-site-key'
                  autoComplete='off'
                  value={v.turnstile_site_key}
                  onChange={(e) =>
                    setV({ ...v, turnstile_site_key: e.target.value })
                  }
                />
              </div>
              <div className='grid gap-1'>
                <Label htmlFor='su-secret'>{t('platform.signup.secret')}</Label>
                <Input
                  id='su-secret'
                  type='password'
                  autoComplete='new-password'
                  placeholder={
                    initial.turnstile_secret_set && !v.clear_turnstile_secret
                      ? t('platform.signup.secret_saved')
                      : ''
                  }
                  value={v.turnstile_secret}
                  onChange={(e) =>
                    setV({ ...v, turnstile_secret: e.target.value })
                  }
                />
              </div>
            </div>
            {initial.turnstile_secret_set && (
              <label className='flex min-h-11 items-center gap-2 text-sm sm:min-h-9'>
                <input
                  type='checkbox'
                  className='size-4'
                  checked={v.clear_turnstile_secret}
                  onChange={(e) =>
                    setV({ ...v, clear_turnstile_secret: e.target.checked })
                  }
                />
                {t('platform.signup.secret_clear')}
              </label>
            )}
            <p className='text-sm text-muted-foreground'>
              {t('platform.signup.turnstile_help')}
            </p>
            <div className='grid gap-1'>
              <Label htmlFor='su-domains'>{t('platform.signup.domains')}</Label>
              <Textarea
                id='su-domains'
                rows={5}
                dir='ltr'
                spellCheck={false}
                value={v.disposable_domains}
                onChange={(e) =>
                  setV({ ...v, disposable_domains: e.target.value })
                }
              />
              <p className='text-sm text-muted-foreground'>
                {t('platform.signup.domains_hint')}
              </p>
              {error('disposable_domains')}
            </div>
          </fieldset>

          <Button
            type='submit'
            className='min-h-11 justify-self-start sm:min-h-9'
            disabled={save.isPending}
          >
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('common.save')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

/** Settings → E-mail logo: shown at the top of every platform e-mail. */
export function EmailLogoCard({ url }: { url: string | null }) {
  const { t } = useTranslation()
  const file = useRef<HTMLInputElement>(null)
  const upload = usePlatformMutation((f: File) => {
    const form = new FormData()
    form.append('image', f)
    return send('post', 'settings/logo', form)
  }, false)
  const remove = usePlatformMutation(
    () => send('delete', 'settings/logo'),
    false
  )
  const busy = upload.isPending || remove.isPending

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.logo.title')}</CardTitle>
        <CardDescription>{t('platform.logo.hint')}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {url ? (
          <img
            src={url}
            alt={t('platform.logo.alt')}
            className='h-12 w-auto max-w-xs self-start rounded border bg-white object-contain p-1'
          />
        ) : (
          <p className='text-sm text-muted-foreground'>
            {t('platform.logo.none')}
          </p>
        )}
        <div className='flex flex-wrap gap-2'>
          <input
            ref={file}
            type='file'
            accept='image/jpeg,image/png,image/webp'
            className='sr-only'
            aria-label={t('platform.logo.choose')}
            onChange={async (e) => {
              const f = e.target.files?.[0]
              e.target.value = ''
              if (!f) return
              try {
                await upload.mutateAsync(f)
                toast.success(t('platform.logo.saved'))
              } catch {
                toast.error(t('platform.logo.failed'))
              }
            }}
          />
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={busy}
            onClick={() => file.current?.click()}
          >
            {upload.isPending ? (
              <Loader2 className='animate-spin' />
            ) : (
              <Upload />
            )}
            {t('platform.logo.choose')}
          </Button>
          {url && (
            <Button
              type='button'
              variant='ghost'
              className='min-h-11 sm:min-h-9'
              disabled={busy}
              onClick={() => remove.mutate(undefined)}
            >
              <Trash2 /> {t('platform.logo.remove')}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
