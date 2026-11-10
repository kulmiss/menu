import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Loader2, Plus, Trash2 } from 'lucide-react'
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
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { branchesQuery } from '@/features/branches/api'
import { tr, withText } from '@/features/menu/text'
import { useMenuContext } from '@/features/menu/use-menu-context'
import {
  type Profile,
  SOCIALS,
  closuresQuery,
  profileQuery,
  useAddClosure,
  useDeleteClosure,
  useSaveProfile,
} from './api'
import { HoursEditor } from './hours-editor'

/** Restaurant info (PLAN.md screen 10): about, contact, Wi-Fi, hours, holidays. */
export function InfoPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const profile = useQuery(profileQuery(ctx.tenantId))
  const branches = useQuery(branchesQuery(ctx.tenantId))
  const [branch, setBranch] = useState<string>('all')
  const disabled = !ctx.canManage || !ctx.canWrite
  const several = (branches.data?.length ?? 0) > 1

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('info.title')}
          </h1>
          <p className='text-muted-foreground'>{t('info.description')}</p>
        </div>
        <div className='grid items-start gap-4 xl:grid-cols-2'>
          {profile.data ? (
            <ProfileForm initial={profile.data} disabled={disabled} />
          ) : (
            <Skeleton className='h-96' />
          )}
          <div className='grid gap-4'>
            <Card>
              <CardHeader>
                <CardTitle>{t('info.hours')}</CardTitle>
                <CardDescription>{t('info.hours_hint')}</CardDescription>
              </CardHeader>
              <CardContent className='grid gap-4'>
                {several && (
                  <Select value={branch} onValueChange={setBranch}>
                    <SelectTrigger
                      className='w-64'
                      aria-label={t('info.hours_for')}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='all'>
                        {t('info.all_branches')}
                      </SelectItem>
                      {branches.data?.map((b) => (
                        <SelectItem key={b.id} value={String(b.id)}>
                          {tr(b.name, ctx.locale)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
                <HoursEditor
                  tenantId={ctx.tenantId}
                  branch={branch === 'all' ? null : Number(branch)}
                  disabled={disabled}
                />
              </CardContent>
            </Card>
            <Closures disabled={disabled} />
          </div>
        </div>
      </Main>
    </>
  )
}

function ProfileForm({
  initial,
  disabled,
}: {
  initial: Profile
  disabled: boolean
}) {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const save = useSaveProfile(ctx.tenantId)
  const [v, setV] = useState(() => ({
    description: tr(initial.description, ctx.locale),
    address: tr(initial.address, ctx.locale),
    maps_url: initial.maps_url ?? '',
    phone: initial.phone ?? '',
    whatsapp: initial.whatsapp ?? '',
    telegram: initial.telegram ?? '',
    email: initial.email ?? '',
    wifi_name: initial.wifi_name ?? '',
    wifi_password: initial.wifi_password ?? '',
    show_wifi: initial.show_wifi,
    socials: { ...initial.socials } as Record<string, string>,
  }))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = (key: keyof typeof v, value: string | boolean) =>
    setV((s) => ({ ...s, [key]: value }))
  const nil = (s: string) => s.trim() || null

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        description: withText(
          initial.description,
          ctx.locale,
          v.description.trim()
        ),
        address: withText(initial.address, ctx.locale, v.address.trim()),
        maps_url: nil(v.maps_url),
        phone: nil(v.phone),
        whatsapp: nil(v.whatsapp),
        telegram: nil(v.telegram),
        email: nil(v.email),
        wifi_name: nil(v.wifi_name),
        wifi_password: nil(v.wifi_password),
        show_wifi: v.show_wifi,
        socials: Object.fromEntries(
          Object.entries(v.socials)
            .filter(([, url]) => url.trim())
            .map(([k, url]) => [k, url.trim()])
        ),
      })
      toast.success(t('info.saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const field = (
    key: keyof typeof v,
    label: string,
    props: React.ComponentProps<typeof Input> = {}
  ) => (
    <div className='grid gap-1.5'>
      <Label htmlFor={`p-${key}`}>{label}</Label>
      <Input
        id={`p-${key}`}
        value={v[key] as string}
        disabled={disabled}
        aria-invalid={!!errors[key] || undefined}
        onChange={(e) => set(key, e.target.value)}
        {...props}
      />
      {errors[key] && <p className='text-sm text-destructive'>{errors[key]}</p>}
    </div>
  )

  return (
    <Card>
      <form onSubmit={submit}>
        <CardHeader>
          <CardTitle>{t('info.about')}</CardTitle>
          <CardDescription>{t('info.about_hint')}</CardDescription>
        </CardHeader>
        <CardContent className='grid gap-4'>
          <div className='grid gap-1.5'>
            <Label htmlFor='p-description'>{t('info.description_label')}</Label>
            <Textarea
              id='p-description'
              rows={3}
              maxLength={1000}
              value={v.description}
              disabled={disabled}
              onChange={(e) => set('description', e.target.value)}
            />
          </div>
          {field('address', t('info.address'), { maxLength: 300 })}
          {field('maps_url', t('info.maps'), {
            type: 'url',
            placeholder: 'https://maps.app.goo.gl/…',
          })}
          <div className='grid gap-4 sm:grid-cols-2'>
            {field('phone', t('info.phone'), { type: 'tel', maxLength: 30 })}
            {field('whatsapp', 'WhatsApp', {
              type: 'tel',
              maxLength: 30,
              placeholder: '+251 …',
            })}
            {field('telegram', 'Telegram', {
              maxLength: 40,
              placeholder: '@username',
            })}
            {field('email', t('info.email'), { type: 'email', maxLength: 120 })}
          </div>
          <fieldset className='grid gap-3'>
            <legend className='mb-2 text-sm font-medium'>
              {t('info.socials')}
            </legend>
            <div className='grid gap-3 sm:grid-cols-2'>
              {SOCIALS.map((key) => (
                <div key={key} className='grid gap-1.5'>
                  <Label htmlFor={`s-${key}`} className='text-xs'>
                    {t(`info.social.${key}`)}
                  </Label>
                  <Input
                    id={`s-${key}`}
                    type='url'
                    placeholder='https://'
                    value={v.socials[key] ?? ''}
                    disabled={disabled}
                    onChange={(e) =>
                      setV((s) => ({
                        ...s,
                        socials: { ...s.socials, [key]: e.target.value },
                      }))
                    }
                  />
                  {errors[`socials.${key}`] && (
                    <p className='text-sm text-destructive'>
                      {errors[`socials.${key}`]}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </fieldset>
          <fieldset className='grid gap-3 rounded-md border p-3'>
            <legend className='px-1 text-sm font-medium'>Wi-Fi</legend>
            <div className='grid gap-4 sm:grid-cols-2'>
              {field('wifi_name', t('info.wifi_name'), { maxLength: 64 })}
              {field('wifi_password', t('info.wifi_password'), {
                maxLength: 64,
              })}
            </div>
            <div className='flex items-center justify-between gap-4'>
              <Label htmlFor='p-show-wifi'>{t('info.show_wifi')}</Label>
              <Switch
                id='p-show-wifi'
                checked={v.show_wifi}
                disabled={disabled}
                onCheckedChange={(on) => set('show_wifi', on)}
              />
            </div>
          </fieldset>
          {!disabled && (
            <Button
              type='submit'
              className='justify-self-start'
              disabled={save.isPending}
            >
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('common.save')}
            </Button>
          )}
        </CardContent>
      </form>
    </Card>
  )
}

function Closures({ disabled }: { disabled: boolean }) {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const closures = useQuery(closuresQuery(ctx.tenantId))
  const add = useAddClosure(ctx.tenantId)
  const remove = useDeleteClosure(ctx.tenantId)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await add.mutateAsync({
        starts_on: from,
        ends_on: to || from,
        note: note.trim() ? { [ctx.locale]: note.trim() } : {},
      })
      setFrom('')
      setTo('')
      setNote('')
    } catch (err) {
      const fields = validationErrors(err)
      setError(fields ? Object.values(fields)[0] : null)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('info.closures')}</CardTitle>
        <CardDescription>{t('info.closures_hint')}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        <ul className='divide-y rounded-md border'>
          {closures.data?.map((c) => (
            <li
              key={c.id}
              className='flex items-center gap-2 px-3 py-2 text-sm'
            >
              <span className='flex-1'>
                <span className='font-medium'>
                  {c.starts_on === c.ends_on
                    ? c.starts_on
                    : `${c.starts_on} → ${c.ends_on}`}
                </span>
                {tr(c.note, ctx.locale) && (
                  <span className='text-muted-foreground'>
                    {' '}
                    · {tr(c.note, ctx.locale)}
                  </span>
                )}
              </span>
              {!disabled && (
                <Button
                  variant='ghost'
                  size='icon'
                  className='size-9'
                  aria-label={t('info.remove_closure', { date: c.starts_on })}
                  onClick={() => remove.mutate(c.id)}
                >
                  <Trash2 />
                </Button>
              )}
            </li>
          ))}
          {closures.data?.length === 0 && (
            <li className='px-3 py-3 text-sm text-muted-foreground'>
              {t('info.no_closures')}
            </li>
          )}
        </ul>
        {!disabled && (
          <form onSubmit={submit} className='flex flex-wrap items-end gap-2'>
            <div className='grid gap-1'>
              <Label htmlFor='c-from' className='text-xs'>
                {t('info.from')}
              </Label>
              <Input
                id='c-from'
                type='date'
                value={from}
                onChange={(e) => setFrom(e.target.value)}
                className='w-40'
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='c-to' className='text-xs'>
                {t('info.to')}
              </Label>
              <Input
                id='c-to'
                type='date'
                value={to}
                min={from}
                onChange={(e) => setTo(e.target.value)}
                className='w-40'
              />
            </div>
            <div className='grid min-w-40 flex-1 gap-1'>
              <Label htmlFor='c-note' className='text-xs'>
                {t('info.note')}
              </Label>
              <Input
                id='c-note'
                value={note}
                maxLength={120}
                placeholder={t('info.note_placeholder')}
                onChange={(e) => setNote(e.target.value)}
              />
            </div>
            <Button
              type='submit'
              variant='secondary'
              disabled={!from || add.isPending}
            >
              {add.isPending ? <Loader2 className='animate-spin' /> : <Plus />}
              {t('info.add_closure')}
            </Button>
          </form>
        )}
        {error && <p className='text-sm text-destructive'>{error}</p>}
      </CardContent>
    </Card>
  )
}
