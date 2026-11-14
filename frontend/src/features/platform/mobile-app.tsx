import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { LogOut, Send } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { type Col, DataTable } from '@/components/data-table'
import { fetchPage, pkeys, plansQ, send, usePlatformMutation } from './api'
import { PageTitle } from './layout'

/**
 * Super Admin → Mobile app (owner, 2026-11-14, QUESTIONS #193): what the app
 * reads from `mobile/config`, pushes, signed-in devices and numbers.
 */
type Settings = {
  restaurant_sign_in: boolean
  super_admin_sign_in: boolean
  sign_in_message: string
  maintenance: boolean
  maintenance_message: string
  latest_version: string
  release_notes: string
  min_version: string
  recommended_version: string
  banner: {
    text: string
    link: string
    starts_at: string | null
    ends_at: string | null
    audience: 'all' | 'restaurant' | 'super_admin'
  }
  app_features: Record<string, boolean>
  links: Record<string, string>
}
type PushRow = {
  id: number
  title: string
  body: string
  link: string | null
  audience: { to: string }
  recipients: number
  with_app: number
  sent_by: string | null
  created_at: string
}
type Device = {
  id: number
  device: string
  platform: string | null
  app_version: string | null
  user: { id: number; name: string; email: string; is_super_admin: boolean }
  restaurants: string | null
  push_on: boolean
  last_used_at: string | null
}
type Stats = {
  devices: number
  active_7d: number
  active_30d: number
  people: number
  platforms: { key: string | null; count: number }[]
  versions: { key: string | null; count: number }[]
  push_opt_in: number | null
  below_min: number
}

const TABS = [
  'access',
  'versions',
  'banner',
  'features',
  'links',
  'push',
  'devices',
  'stats',
] as const
type Tab = (typeof TABS)[number]
const APP_FEATURES = [
  'biometric_lock',
  'push',
  'analytics',
  'crash_reports',
] as const
const LINKS = [
  'play_store',
  'app_store',
  'privacy',
  'terms',
  'support_whatsapp',
] as const
const settingsKey = [...pkeys.all, 'mobile-app'] as const

const toLocalInput = (iso: string | null) =>
  iso
    ? new Date(
        new Date(iso).getTime() - new Date().getTimezoneOffset() * 60_000
      )
        .toISOString()
        .slice(0, 16)
    : ''
const fromLocalInput = (v: string) => (v ? new Date(v).toISOString() : null)

export function MobileAppPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState<Tab>(() => {
    const hash = window.location.hash.slice(1)
    return (TABS as readonly string[]).includes(hash) ? (hash as Tab) : 'access'
  })
  const s = useQuery({
    queryKey: settingsKey,
    queryFn: async () =>
      (await api.get<{ data: Settings }>('/platform/mobile-app')).data.data,
  })
  return (
    <>
      <PageTitle
        title={t('platform.nav.mobile_app')}
        hint={t('platform.mobile.hint')}
      />
      <Tabs
        value={tab}
        onValueChange={(v) => {
          setTab(v as Tab)
          window.history.replaceState(window.history.state, '', `#${v}`)
        }}
        className='gap-6'
      >
        <TabsList
          aria-label={t('platform.nav.mobile_app')}
          className='h-auto w-full justify-start overflow-x-auto'
        >
          {TABS.map((k) => (
            <TabsTrigger
              key={k}
              value={k}
              className='min-h-11 flex-none px-3 sm:min-h-9'
            >
              {t(`platform.mobile.tabs.${k}`)}
            </TabsTrigger>
          ))}
        </TabsList>
        {(['access', 'versions', 'banner', 'features', 'links'] as const).map(
          (k) => (
            <TabsContent key={k} value={k} className='min-w-0'>
              {s.data ? (
                <SettingsForm part={k} initial={s.data} />
              ) : (
                <Skeleton className='h-64 w-full' />
              )}
            </TabsContent>
          )
        )}
        <TabsContent value='push' className='min-w-0'>
          <PushPanel />
        </TabsContent>
        <TabsContent value='devices' className='min-w-0'>
          <DevicesPanel />
        </TabsContent>
        <TabsContent value='stats' className='min-w-0'>
          <StatsPanel />
        </TabsContent>
      </Tabs>
    </>
  )
}

function Field({
  id,
  label,
  hint,
  error,
  children,
}: {
  id: string
  label: string
  hint?: string
  error?: string
  children: React.ReactNode
}) {
  return (
    <div className='grid gap-1.5'>
      <Label htmlFor={id}>{label}</Label>
      {children}
      {hint && <p className='text-xs text-muted-foreground'>{hint}</p>}
      {error && <p className='text-sm text-destructive'>{error}</p>}
    </div>
  )
}

function Toggle({
  id,
  label,
  hint,
  checked,
  onChange,
}: {
  id: string
  label: string
  hint?: string
  checked: boolean
  onChange: (v: boolean) => void
}) {
  return (
    <label
      htmlFor={id}
      className='flex min-h-11 cursor-pointer items-start justify-between gap-4'
    >
      <span>
        <span className='block text-sm font-medium'>{label}</span>
        {hint && (
          <span className='block text-xs text-muted-foreground'>{hint}</span>
        )}
      </span>
      <Switch id={id} checked={checked} onCheckedChange={onChange} />
    </label>
  )
}

/** One tab of the settings; each saves only its own fields. */
function SettingsForm({
  part,
  initial,
}: {
  part: 'access' | 'versions' | 'banner' | 'features' | 'links'
  initial: Settings
}) {
  const { t } = useTranslation()
  const [v, setV] = useState<Settings>(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = usePlatformMutation((body: Partial<Settings>) =>
    send('put', 'mobile-app', body)
  )
  const fields: Record<typeof part, (keyof Settings)[]> = {
    access: [
      'restaurant_sign_in',
      'super_admin_sign_in',
      'sign_in_message',
      'maintenance',
      'maintenance_message',
    ],
    versions: [
      'latest_version',
      'release_notes',
      'min_version',
      'recommended_version',
    ],
    banner: ['banner'],
    features: ['app_features'],
    links: ['links'],
  }
  const set = <K extends keyof Settings>(k: K, value: Settings[K]) =>
    setV((x) => ({ ...x, [k]: value }))
  const err = (k: string) => errors[k]
  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync(
        Object.fromEntries(
          fields[part].map((k) => [k, v[k]])
        ) as Partial<Settings>
      )
      toast.success(t('platform.mobile.saved'))
    } catch (x) {
      const f = validationErrors(x)
      if (f)
        setErrors(
          Object.fromEntries(
            Object.entries(f).map(([k, m]) => [
              k,
              Array.isArray(m) ? m[0] : String(m),
            ])
          )
        )
    }
  }
  const title = t(`platform.mobile.${part}.title`)
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{t(`platform.mobile.${part}.hint`)}</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className='grid max-w-2xl gap-5'>
          {part === 'access' && (
            <>
              {v.maintenance && (
                <p
                  role='status'
                  className='rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm'
                >
                  {t('platform.mobile.access.maintenance_on')}
                </p>
              )}
              <Toggle
                id='m-rest'
                label={t('platform.mobile.access.restaurant')}
                checked={v.restaurant_sign_in}
                onChange={(x) => set('restaurant_sign_in', x)}
              />
              <Toggle
                id='m-admin'
                label={t('platform.mobile.access.super_admin')}
                checked={v.super_admin_sign_in}
                onChange={(x) => set('super_admin_sign_in', x)}
              />
              <Field
                id='m-off'
                label={t('platform.mobile.access.message')}
                hint={t('platform.mobile.access.message_hint')}
                error={err('sign_in_message')}
              >
                <Textarea
                  id='m-off'
                  rows={2}
                  maxLength={300}
                  value={v.sign_in_message}
                  onChange={(e) => set('sign_in_message', e.target.value)}
                />
              </Field>
              <Toggle
                id='m-maint'
                label={t('platform.mobile.access.maintenance')}
                hint={t('platform.mobile.access.maintenance_hint')}
                checked={v.maintenance}
                onChange={(x) => set('maintenance', x)}
              />
              <Field
                id='m-maint-msg'
                label={t('platform.mobile.access.maintenance_message')}
                error={err('maintenance_message')}
              >
                <Textarea
                  id='m-maint-msg'
                  rows={2}
                  maxLength={300}
                  value={v.maintenance_message}
                  onChange={(e) => set('maintenance_message', e.target.value)}
                />
              </Field>
            </>
          )}
          {part === 'versions' && (
            <>
              <div className='grid gap-4 sm:grid-cols-3'>
                <Field
                  id='m-latest'
                  label={t('platform.mobile.versions.latest')}
                  error={err('latest_version')}
                >
                  <Input
                    id='m-latest'
                    inputMode='decimal'
                    placeholder='1.2.0'
                    value={v.latest_version}
                    onChange={(e) =>
                      set('latest_version', e.target.value.trim())
                    }
                  />
                </Field>
                <Field
                  id='m-rec'
                  label={t('platform.mobile.versions.recommended')}
                  hint={t('platform.mobile.versions.recommended_hint')}
                  error={err('recommended_version')}
                >
                  <Input
                    id='m-rec'
                    inputMode='decimal'
                    placeholder='1.1.0'
                    value={v.recommended_version}
                    onChange={(e) =>
                      set('recommended_version', e.target.value.trim())
                    }
                  />
                </Field>
                <Field
                  id='m-min'
                  label={t('platform.mobile.versions.min')}
                  hint={t('platform.mobile.versions.min_hint')}
                  error={err('min_version')}
                >
                  <Input
                    id='m-min'
                    inputMode='decimal'
                    placeholder='1.0.0'
                    value={v.min_version}
                    onChange={(e) => set('min_version', e.target.value.trim())}
                  />
                </Field>
              </div>
              <Field
                id='m-notes'
                label={t('platform.mobile.versions.notes')}
                error={err('release_notes')}
              >
                <Textarea
                  id='m-notes'
                  rows={4}
                  maxLength={2000}
                  value={v.release_notes}
                  onChange={(e) => set('release_notes', e.target.value)}
                />
              </Field>
            </>
          )}
          {part === 'banner' && (
            <>
              <Field
                id='m-b-text'
                label={t('platform.mobile.banner.text')}
                hint={t('platform.mobile.banner.text_hint')}
                error={err('banner.text')}
              >
                <Input
                  id='m-b-text'
                  maxLength={200}
                  value={v.banner.text}
                  onChange={(e) =>
                    set('banner', { ...v.banner, text: e.target.value })
                  }
                />
              </Field>
              <Field
                id='m-b-link'
                label={t('platform.mobile.banner.link')}
                hint={t('platform.mobile.link_hint')}
                error={err('banner.link')}
              >
                <Input
                  id='m-b-link'
                  placeholder='https://… or /inbox'
                  value={v.banner.link}
                  onChange={(e) =>
                    set('banner', { ...v.banner, link: e.target.value.trim() })
                  }
                />
              </Field>
              <div className='grid gap-4 sm:grid-cols-3'>
                <Field
                  id='m-b-from'
                  label={t('platform.mobile.banner.starts')}
                  error={err('banner.starts_at')}
                >
                  <Input
                    id='m-b-from'
                    type='datetime-local'
                    value={toLocalInput(v.banner.starts_at)}
                    onChange={(e) =>
                      set('banner', {
                        ...v.banner,
                        starts_at: fromLocalInput(e.target.value),
                      })
                    }
                  />
                </Field>
                <Field
                  id='m-b-to'
                  label={t('platform.mobile.banner.ends')}
                  error={err('banner.ends_at')}
                >
                  <Input
                    id='m-b-to'
                    type='datetime-local'
                    value={toLocalInput(v.banner.ends_at)}
                    onChange={(e) =>
                      set('banner', {
                        ...v.banner,
                        ends_at: fromLocalInput(e.target.value),
                      })
                    }
                  />
                </Field>
                <Field id='m-b-who' label={t('platform.mobile.audience')}>
                  <Select
                    value={v.banner.audience}
                    onValueChange={(x) =>
                      set('banner', {
                        ...v.banner,
                        audience: x as Settings['banner']['audience'],
                      })
                    }
                  >
                    <SelectTrigger id='m-b-who' className='min-h-11 sm:min-h-9'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {(['all', 'restaurant', 'super_admin'] as const).map(
                        (a) => (
                          <SelectItem key={a} value={a}>
                            {t(`platform.mobile.audiences.${a}`)}
                          </SelectItem>
                        )
                      )}
                    </SelectContent>
                  </Select>
                </Field>
              </div>
              {v.banner.text.trim() !== '' && (
                <div
                  aria-label={t('platform.mobile.banner.preview')}
                  className='rounded-md bg-primary/10 p-3 text-sm'
                >
                  {v.banner.text}
                </div>
              )}
            </>
          )}
          {part === 'features' &&
            APP_FEATURES.map((k) => (
              <Toggle
                key={k}
                id={`m-f-${k}`}
                label={t(`platform.mobile.features.${k}`)}
                hint={t(`platform.mobile.features.${k}_hint`)}
                checked={v.app_features[k] !== false}
                onChange={(x) =>
                  set('app_features', { ...v.app_features, [k]: x })
                }
              />
            ))}
          {part === 'links' &&
            LINKS.map((k) => (
              <Field
                key={k}
                id={`m-l-${k}`}
                label={t(`platform.mobile.links.${k}`)}
                hint={
                  k === 'privacy' || k === 'terms'
                    ? t('platform.mobile.links.default_hint')
                    : undefined
                }
                error={err(`links.${k}`)}
              >
                <Input
                  id={`m-l-${k}`}
                  inputMode={k === 'support_whatsapp' ? 'tel' : 'url'}
                  placeholder={
                    k === 'support_whatsapp' ? '+251 9…' : 'https://'
                  }
                  value={v.links[k] ?? ''}
                  onChange={(e) =>
                    set('links', { ...v.links, [k]: e.target.value.trim() })
                  }
                />
              </Field>
            ))}
          {part === 'features' && (
            <p className='text-sm text-muted-foreground'>
              {t('platform.mobile.features.global_note')}
            </p>
          )}
          <Button
            type='submit'
            className='min-h-11 justify-self-start sm:min-h-9'
            disabled={save.isPending}
          >
            {t('common.save')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function PushPanel() {
  const { t, i18n } = useTranslation()
  const plans = useQuery(plansQ())
  const history = useQuery({
    queryKey: [...pkeys.all, 'mobile-pushes'],
    queryFn: async () =>
      (await api.get<{ data: PushRow[] }>('/platform/mobile-app/pushes')).data
        .data,
  })
  const [f, setF] = useState({
    title: '',
    body: '',
    link: '',
    to: 'all',
    role: 'any',
    plan: 'any',
    restaurants: '',
  })
  const [count, setCount] = useState<{
    recipients: number
    with_app: number
  } | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const sendIt = usePlatformMutation(
    async (body: object) =>
      (
        await api.post<{ data: { recipients: number; with_app: number } }>(
          '/platform/mobile-app/pushes',
          body
        )
      ).data.data
  )
  type Count = { recipients: number; with_app: number }
  const body = () => ({
    title: f.title,
    body: f.body,
    link: f.link || null,
    audience: {
      to: f.to,
      ...(f.to === 'restaurant' && f.role !== 'any' ? { roles: [f.role] } : {}),
      ...(f.to === 'restaurant' && f.plan !== 'any'
        ? { plan_ids: [Number(f.plan)] }
        : {}),
      ...(f.to === 'restaurant' && f.restaurants.trim()
        ? {
            tenant_ids: f.restaurants
              .split(/[\s,]+/)
              .filter(Boolean)
              .map(Number)
              .filter((n) => n > 0),
          }
        : {}),
    },
  })
  const fail = (x: unknown) => {
    const fe = validationErrors(x)
    if (fe)
      setErrors(
        Object.fromEntries(
          Object.entries(fe).map(([k, m]) => [
            k,
            Array.isArray(m) ? m[0] : String(m),
          ])
        )
      )
  }
  const preview = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrors({})
    try {
      setCount(
        (await sendIt.mutateAsync({ ...body(), dry_run: true })) as Count
      )
    } catch (x) {
      fail(x)
    }
  }
  const confirm = async () => {
    try {
      const r = (await sendIt.mutateAsync(body())) as Count
      toast.success(t('platform.mobile.push.sent', { count: r.recipients }))
      setCount(null)
      setF((x) => ({ ...x, title: '', body: '', link: '' }))
    } catch (x) {
      setCount(null)
      fail(x)
    }
  }
  const when = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))
  return (
    <div className='grid gap-6'>
      <Card>
        <CardHeader>
          <CardTitle>{t('platform.mobile.push.title')}</CardTitle>
          <CardDescription>{t('platform.mobile.push.hint')}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={preview} className='grid max-w-2xl gap-5'>
            <Field
              id='p-title'
              label={t('platform.mobile.push.heading')}
              error={errors.title}
            >
              <Input
                id='p-title'
                required
                maxLength={120}
                value={f.title}
                onChange={(e) => setF({ ...f, title: e.target.value })}
              />
            </Field>
            <Field
              id='p-body'
              label={t('platform.mobile.push.body')}
              error={errors.body}
            >
              <Textarea
                id='p-body'
                required
                rows={3}
                maxLength={500}
                value={f.body}
                onChange={(e) => setF({ ...f, body: e.target.value })}
              />
            </Field>
            <Field
              id='p-link'
              label={t('platform.mobile.push.link')}
              hint={t('platform.mobile.link_hint')}
              error={errors.link}
            >
              <Input
                id='p-link'
                placeholder='/inbox · https://…'
                value={f.link}
                onChange={(e) => setF({ ...f, link: e.target.value.trim() })}
              />
            </Field>
            <div className='grid gap-4 sm:grid-cols-3'>
              <Field id='p-to' label={t('platform.mobile.audience')}>
                <Select
                  value={f.to}
                  onValueChange={(x) => setF({ ...f, to: x })}
                >
                  <SelectTrigger id='p-to' className='min-h-11 sm:min-h-9'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(['all', 'restaurant', 'super_admin'] as const).map(
                      (a) => (
                        <SelectItem key={a} value={a}>
                          {t(`platform.mobile.audiences.${a}`)}
                        </SelectItem>
                      )
                    )}
                  </SelectContent>
                </Select>
              </Field>
              {f.to === 'restaurant' && (
                <>
                  <Field id='p-role' label={t('platform.mobile.push.role')}>
                    <Select
                      value={f.role}
                      onValueChange={(x) => setF({ ...f, role: x })}
                    >
                      <SelectTrigger
                        id='p-role'
                        className='min-h-11 sm:min-h-9'
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {['any', 'owner', 'manager', 'staff'].map((r) => (
                          <SelectItem key={r} value={r}>
                            {t(`platform.mobile.push.roles.${r}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field id='p-plan' label={t('platform.mobile.push.plan')}>
                    <Select
                      value={f.plan}
                      onValueChange={(x) => setF({ ...f, plan: x })}
                    >
                      <SelectTrigger
                        id='p-plan'
                        className='min-h-11 sm:min-h-9'
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='any'>
                          {t('platform.mobile.push.any_plan')}
                        </SelectItem>
                        {(plans.data ?? []).map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.name.en ?? Object.values(p.name)[0]}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                </>
              )}
            </div>
            {f.to === 'restaurant' && (
              <Field
                id='p-ids'
                label={t('platform.mobile.push.restaurants')}
                hint={t('platform.mobile.push.restaurants_hint')}
                error={errors['audience.tenant_ids']}
              >
                <Input
                  id='p-ids'
                  inputMode='numeric'
                  value={f.restaurants}
                  onChange={(e) => setF({ ...f, restaurants: e.target.value })}
                />
              </Field>
            )}
            {errors.audience && (
              <p className='text-sm text-destructive'>{errors.audience}</p>
            )}
            <Button
              type='submit'
              className='min-h-11 justify-self-start sm:min-h-9'
              disabled={sendIt.isPending}
            >
              <Send aria-hidden /> {t('platform.mobile.push.review')}
            </Button>
          </form>
        </CardContent>
      </Card>
      <Dialog open={count !== null} onOpenChange={(o) => !o && setCount(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('platform.mobile.push.confirm_title')}</DialogTitle>
            <DialogDescription>
              {count &&
                t('platform.mobile.push.confirm', {
                  count: count.recipients,
                  app: count.with_app,
                })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant='outline' onClick={() => setCount(null)}>
              {t('common.cancel')}
            </Button>
            <Button
              onClick={confirm}
              disabled={sendIt.isPending || !count?.recipients}
            >
              {t('platform.mobile.push.send')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Card>
        <CardHeader>
          <CardTitle>{t('platform.mobile.push.history')}</CardTitle>
        </CardHeader>
        <CardContent>
          {history.isPending ? (
            <Skeleton className='h-24 w-full' />
          ) : (history.data ?? []).length === 0 ? (
            <p className='text-sm text-muted-foreground'>
              {t('platform.mobile.push.none')}
            </p>
          ) : (
            <ul className='divide-y rounded-md border'>
              {history.data!.map((p) => (
                <li key={p.id} className='grid gap-0.5 px-3 py-2 text-sm'>
                  <span className='font-medium'>{p.title}</span>
                  <span className='text-muted-foreground'>{p.body}</span>
                  <span className='text-xs text-muted-foreground'>
                    {t('platform.mobile.push.row', {
                      when: when(p.created_at),
                      who: t(`platform.mobile.audiences.${p.audience.to}`),
                      count: p.recipients,
                      app: p.with_app,
                      by: p.sent_by ?? '—',
                    })}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function DevicesPanel() {
  const { t, i18n } = useTranslation()
  const [all, setAll] = useState(false)
  const out = usePlatformMutation(
    (id: number) => send('delete', `mobile-app/devices/${id}`),
    false
  )
  const outAll = usePlatformMutation(
    () => send('post', 'mobile-app/devices/sign-out-all', { confirm: true }),
    false
  )
  const when = (iso: string | null) =>
    iso
      ? new Intl.DateTimeFormat(i18n.language, {
          dateStyle: 'medium',
          timeStyle: 'short',
        }).format(new Date(iso))
      : '—'
  const columns: Col<Device>[] = [
    {
      id: 'user',
      label: t('platform.mobile.devices.user'),
      primary: true,
      sort: 'user',
      text: (d) => d.user.name,
      cell: (d) => (
        <span className='grid'>
          <span className='font-medium'>
            {d.user.name}{' '}
            {d.user.is_super_admin && (
              <Badge variant='secondary'>
                {t('platform.mobile.audiences.super_admin')}
              </Badge>
            )}
          </span>
          <span className='text-xs text-muted-foreground'>{d.user.email}</span>
        </span>
      ),
    },
    {
      id: 'restaurants',
      label: t('platform.mobile.devices.restaurants'),
      text: (d) => d.restaurants ?? '',
      cell: (d) => d.restaurants ?? '—',
    },
    {
      id: 'device',
      label: t('platform.mobile.devices.device'),
      text: (d) => d.device,
      cell: (d) => d.device,
    },
    {
      id: 'platform',
      label: t('platform.mobile.devices.platform'),
      text: (d) => d.platform ?? '',
      cell: (d) =>
        d.platform === 'ios'
          ? 'iOS'
          : d.platform === 'android'
            ? 'Android'
            : '—',
    },
    {
      id: 'app_version',
      label: t('platform.mobile.devices.version'),
      sort: 'app_version',
      text: (d) => d.app_version ?? '',
      cell: (d) => d.app_version ?? '—',
    },
    {
      id: 'push',
      label: t('platform.mobile.devices.push'),
      text: (d) => (d.push_on ? 'on' : 'off'),
      cell: (d) => (d.push_on ? t('platform.switches.on') : '—'),
    },
    {
      id: 'last_used_at',
      label: t('platform.mobile.devices.last_seen'),
      sort: 'last_used_at',
      text: (d) => d.last_used_at ?? '',
      cell: (d) => when(d.last_used_at),
    },
    {
      id: 'actions',
      label: t('platform.mobile.devices.actions'),
      cell: (d) => (
        <Button
          size='sm'
          variant='outline'
          className='min-h-11 sm:min-h-8'
          aria-label={t('platform.mobile.devices.sign_out_named', {
            name: d.user.name,
            device: d.device,
          })}
          onClick={async (e) => {
            e.stopPropagation()
            await out.mutateAsync(d.id)
            toast.success(t('platform.mobile.devices.signed_out'))
          }}
        >
          <LogOut aria-hidden /> {t('platform.mobile.devices.sign_out')}
        </Button>
      ),
    },
  ]
  return (
    <>
      <DataTable<Device>
        id='platform-mobile-devices'
        queryKey={[...pkeys.all, 'mobile-devices']}
        fetch={(params) => fetchPage<Device>('mobile-app/devices', params)}
        columns={columns}
        rowId={(d) => d.id}
        searchPlaceholder={t('platform.mobile.devices.search')}
        dateLabel={t('platform.mobile.devices.last_seen')}
        filters={[
          {
            id: 'platform',
            label: t('platform.mobile.devices.platform'),
            options: [
              { value: 'android', label: 'Android' },
              { value: 'ios', label: 'iOS' },
            ],
          },
          {
            id: 'kind',
            label: t('platform.mobile.audience'),
            options: (['restaurant', 'super_admin'] as const).map((k) => ({
              value: k,
              label: t(`platform.mobile.audiences.${k}`),
            })),
          },
        ]}
        actions={
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={() => setAll(true)}
          >
            <LogOut aria-hidden /> {t('platform.mobile.devices.sign_out_all')}
          </Button>
        }
        empty={t('platform.mobile.devices.empty')}
      />
      <Dialog open={all} onOpenChange={setAll}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t('platform.mobile.devices.sign_out_all')}
            </DialogTitle>
            <DialogDescription>
              {t('platform.mobile.devices.sign_out_all_hint')}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant='outline' onClick={() => setAll(false)}>
              {t('common.cancel')}
            </Button>
            <Button
              variant='destructive'
              disabled={outAll.isPending}
              onClick={async () => {
                const r = (await outAll.mutateAsync()) as {
                  data: { data: { signed_out: number } }
                }
                setAll(false)
                toast.success(
                  t('platform.mobile.devices.all_signed_out', {
                    count: r.data.data.signed_out,
                  })
                )
              }}
            >
              {t('platform.mobile.devices.sign_out_all')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

function StatsPanel() {
  const { t } = useTranslation()
  const s = useQuery({
    queryKey: [...pkeys.all, 'mobile-stats'],
    queryFn: async () =>
      (await api.get<{ data: Stats }>('/platform/mobile-app/stats')).data.data,
  })
  if (!s.data) return <Skeleton className='h-48 w-full' />
  const d = s.data
  const tiles: [string, string][] = [
    ['active_7d', String(d.active_7d)],
    ['active_30d', String(d.active_30d)],
    ['devices', String(d.devices)],
    ['people', String(d.people)],
    ['push_opt_in', d.push_opt_in === null ? '—' : `${d.push_opt_in}%`],
    ['below_min', String(d.below_min)],
  ]
  const total = (rows: { count: number }[]) =>
    Math.max(
      1,
      rows.reduce((a, r) => a + r.count, 0)
    )
  const bars = (
    rows: { key: string | null; count: number }[],
    label: (k: string | null) => string
  ) => (
    <ul className='grid gap-2'>
      {rows.map((r) => (
        <li key={r.key ?? '-'} className='grid gap-1 text-sm'>
          <span className='flex justify-between gap-2'>
            <span>{label(r.key)}</span>
            <span className='tabular-nums'>{r.count}</span>
          </span>
          <span className='h-2 rounded-full bg-muted' aria-hidden>
            <span
              className='block h-2 rounded-full bg-primary'
              style={{ width: `${(r.count * 100) / total(rows)}%` }}
            />
          </span>
        </li>
      ))}
    </ul>
  )
  return (
    <div className='grid gap-6'>
      <dl className='grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6'>
        {tiles.map(([k, value]) => (
          <div key={k} className='rounded-lg border p-3'>
            <dt className='text-xs text-muted-foreground'>
              {t(`platform.mobile.stats.${k}`)}
            </dt>
            <dd className='text-2xl font-bold tabular-nums'>{value}</dd>
          </div>
        ))}
      </dl>
      <div className='grid gap-6 md:grid-cols-2'>
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.mobile.stats.platforms')}</CardTitle>
            <CardDescription>
              {t('platform.mobile.stats.last_30')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {bars(d.platforms, (k) =>
              k === 'ios'
                ? 'iOS'
                : k === 'android'
                  ? 'Android'
                  : t('platform.mobile.stats.unknown')
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.mobile.stats.versions')}</CardTitle>
            <CardDescription>
              {t('platform.mobile.stats.last_30')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {bars(d.versions, (k) => k ?? t('platform.mobile.stats.unknown'))}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
