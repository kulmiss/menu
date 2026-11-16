import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, CircleAlert, ChevronLeft, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useSetupWizard } from '@/stores/setup-wizard-store'
import { api } from '@/lib/api'
import { toast } from '@/lib/toast'
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
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { meQueryOptions } from '@/features/auth/api'
import { useMe } from '@/features/auth/use-me'
import { type PlanRow, plansQ, send, settingsQ } from './api'
import { FeatureSwitches } from './feature-switches'
import { type SetupState, setupQ } from './setup-api'

const STEPS = ['welcome', 'platform', 'plans', 'email', 'switches'] as const

/**
 * Super Admin setup wizard (backend QUESTIONS #195): 5 short pages on the
 * first sign-in of a new install. "Finish" (or "Finish later") closes it for
 * good; what is not done shows as a checklist on the dashboard.
 */
export default function SetupWizard() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const setOpen = useSetupWizard((s) => s.setOpen)
  const [step, setStep] = useState(0)
  const state = useQuery(setupQ())
  const finish = async () => {
    await send('put', 'setup', { done: true })
    setOpen(false)
    await Promise.all([
      qc.invalidateQueries({ queryKey: meQueryOptions.queryKey }),
      qc.invalidateQueries({ queryKey: ['platform'] }),
    ])
  }
  const key = STEPS[step]
  return (
    <Dialog open onOpenChange={(o) => !o && void finish()}>
      <DialogContent className='max-h-[92svh] overflow-y-auto sm:max-w-2xl'>
        <DialogHeader>
          <p className='text-sm text-muted-foreground'>
            {t('platform.setup.step', { n: step + 1, total: STEPS.length })}
          </p>
          <DialogTitle>{t(`platform.setup.${key}.title`)}</DialogTitle>
          <DialogDescription>
            {t(`platform.setup.${key}.intro`)}
          </DialogDescription>
        </DialogHeader>
        <ol className='flex gap-1' aria-hidden>
          {STEPS.map((s, i) => (
            <li
              key={s}
              className={`h-1.5 flex-1 rounded-full ${i <= step ? 'bg-primary' : 'bg-muted'}`}
            />
          ))}
        </ol>
        {!state.data ? (
          <Skeleton className='h-48 w-full' />
        ) : (
          <div className='grid gap-4 py-2'>
            {key === 'welcome' && <Welcome state={state.data} />}
            {key === 'platform' && <PlatformStep />}
            {key === 'plans' && <PlansStep />}
            {key === 'email' && <EmailStep state={state.data} />}
            {key === 'switches' && <SwitchesStep state={state.data} />}
          </div>
        )}
        <DialogFooter className='gap-2 sm:justify-between'>
          <Button
            variant='ghost'
            className='min-h-11 sm:min-h-9'
            onClick={() => void finish()}
          >
            {t('platform.setup.later')}
          </Button>
          <div className='flex gap-2'>
            {step > 0 && (
              <Button
                variant='outline'
                className='min-h-11 sm:min-h-9'
                onClick={() => setStep(step - 1)}
              >
                <ChevronLeft />
                {t('platform.setup.back')}
              </Button>
            )}
            {step < STEPS.length - 1 ? (
              <Button
                className='min-h-11 sm:min-h-9'
                onClick={() => setStep(step + 1)}
              >
                {t('platform.setup.next')}
                <ChevronRight />
              </Button>
            ) : (
              <Button
                className='min-h-11 sm:min-h-9'
                onClick={async () => {
                  await finish()
                  toast.success(t('platform.setup.finished'))
                }}
              >
                <Check />
                {t('platform.setup.finish')}
              </Button>
            )}
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function Status({
  ok,
  label,
  hint,
}: {
  ok: boolean
  label: string
  hint?: string
}) {
  return (
    <div className='flex items-start gap-3 rounded-lg border p-3'>
      {ok ? (
        <Check
          className='mt-0.5 size-5 shrink-0 text-emerald-600'
          aria-hidden
        />
      ) : (
        <CircleAlert
          className='mt-0.5 size-5 shrink-0 text-amber-600'
          aria-hidden
        />
      )}
      <div className='min-w-0'>
        <p className='font-medium'>
          <span className='sr-only'>{ok ? '✓ ' : '! '}</span>
          {label}
        </p>
        {hint && (
          <p className='text-sm break-words text-muted-foreground'>{hint}</p>
        )}
      </div>
    </div>
  )
}

const check = (s: SetupState, key: string) =>
  s.checks.find((c) => c.key === key)?.done ?? false

function Welcome({ state }: { state: SetupState }) {
  const { t } = useTranslation()
  return (
    <>
      <Status
        ok={check(state, 'two_factor')}
        label={t('platform.setup.welcome.two_factor')}
      />
      <Status
        ok={check(state, 'backup_key')}
        label={t(
          check(state, 'backup_key')
            ? 'platform.setup.welcome.backup_on'
            : 'platform.setup.welcome.backup_off'
        )}
        hint={
          check(state, 'backup_key')
            ? t('platform.setup.welcome.backup_save')
            : t('platform.setup.welcome.backup_how')
        }
      />
    </>
  )
}

function PlatformStep() {
  const settings = useQuery(settingsQ())
  if (!settings.data) return <Skeleton className='h-48 w-full' />
  return <PlatformForm initial={settings.data} />
}

function PlatformForm({ initial }: { initial: Record<string, string | null> }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [v, setV] = useState({
    name: initial.name ?? '',
    support_email: initial.support_email ?? '',
    upgrade_whatsapp: initial.upgrade_whatsapp ?? '',
    brand_color: initial.brand_color ?? '#c2410c',
  })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  return (
    <form
      className='grid gap-3'
      onSubmit={async (e) => {
        e.preventDefault()
        setError('')
        setSaving(true)
        try {
          await send('put', 'settings', {
            ...v,
            support_email: v.support_email || null,
          })
          await qc.invalidateQueries({ queryKey: ['platform'] })
          toast.success(t('platform.setup.saved'))
        } catch {
          setError(t('platform.setup.save_error'))
        } finally {
          setSaving(false)
        }
      }}
    >
      <div className='grid gap-1.5'>
        <Label htmlFor='w-name'>{t('platform.settings.name')}</Label>
        <Input
          id='w-name'
          required
          minLength={2}
          maxLength={60}
          value={v.name}
          onChange={(e) => setV({ ...v, name: e.target.value })}
        />
      </div>
      <div className='grid gap-3 sm:grid-cols-2'>
        <div className='grid gap-1.5'>
          <Label htmlFor='w-email'>
            {t('platform.settings.support_email')}
          </Label>
          <Input
            id='w-email'
            type='email'
            value={v.support_email}
            onChange={(e) => setV({ ...v, support_email: e.target.value })}
          />
        </div>
        <div className='grid gap-1.5'>
          <Label htmlFor='w-wa'>{t('platform.setup.platform.whatsapp')}</Label>
          <Input
            id='w-wa'
            inputMode='tel'
            placeholder='+251 9…'
            value={v.upgrade_whatsapp}
            onChange={(e) => setV({ ...v, upgrade_whatsapp: e.target.value })}
          />
        </div>
      </div>
      <div className='flex items-center gap-3'>
        <Label htmlFor='w-colour'>{t('platform.setup.platform.colour')}</Label>
        <input
          id='w-colour'
          type='color'
          className='h-11 w-16 cursor-pointer rounded border bg-transparent'
          value={v.brand_color}
          onChange={(e) => setV({ ...v, brand_color: e.target.value })}
        />
      </div>
      <LogoUpload />
      {error && (
        <p role='alert' className='text-sm text-destructive'>
          {error}
        </p>
      )}
      <Button
        type='submit'
        className='min-h-11 justify-self-start sm:min-h-9'
        disabled={saving}
      >
        {t('common.save')}
      </Button>
    </form>
  )
}

function LogoUpload() {
  const { t } = useTranslation()
  const [done, setDone] = useState(false)
  return (
    <div className='grid gap-1.5'>
      <Label htmlFor='w-logo'>{t('platform.setup.platform.logo')}</Label>
      <Input
        id='w-logo'
        type='file'
        accept='image/png,image/jpeg,image/webp'
        className='min-h-11'
        onChange={async (e) => {
          const file = e.target.files?.[0]
          if (!file) return
          const body = new FormData()
          body.append('image', file)
          try {
            await api.post('/platform/settings/logo', body)
            setDone(true)
          } catch {
            toast.error(t('platform.setup.save_error'))
          }
        }}
      />
      <p className='text-xs text-muted-foreground'>
        {done
          ? t('platform.setup.platform.logo_done')
          : t('platform.setup.platform.logo_hint')}
      </p>
    </div>
  )
}

function PlansStep() {
  const plans = useQuery(plansQ())
  if (!plans.data) return <Skeleton className='h-48 w-full' />
  return <PlansForm plans={plans.data.filter((p) => p.is_active)} />
}

function PlansForm({ plans }: { plans: PlanRow[] }) {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const [rows, setRows] = useState(() =>
    plans.map((p) => ({
      p,
      price: String(p.price_minor / 100),
      photos: p.limits.photos == null ? '' : String(p.limits.photos),
      branches: p.limits.branches == null ? '' : String(p.limits.branches),
    }))
  )
  const [saving, setSaving] = useState(false)
  const n = (s: string) =>
    s.trim() === '' ? null : Math.max(0, Math.round(Number(s)))
  return (
    <form
      className='grid gap-3'
      onSubmit={async (e) => {
        e.preventDefault()
        setSaving(true)
        try {
          for (const r of rows) {
            const price = Math.round(Number(r.price) * 100)
            const limits = {
              ...r.p.limits,
              photos: n(r.photos),
              branches: n(r.branches),
            }
            if (
              price !== r.p.price_minor ||
              limits.photos !== (r.p.limits.photos ?? null) ||
              limits.branches !== (r.p.limits.branches ?? null)
            ) {
              await send('patch', `plans/${r.p.id}`, {
                price_minor: price,
                limits,
              })
            }
          }
          await send('put', 'setup', { checked: 'plans' })
          await qc.invalidateQueries({ queryKey: ['platform'] })
          toast.success(t('platform.setup.saved'))
        } catch {
          toast.error(t('platform.setup.save_error'))
        } finally {
          setSaving(false)
        }
      }}
    >
      <div className='overflow-x-auto'>
        <table className='w-full text-sm'>
          <thead>
            <tr className='text-start text-muted-foreground'>
              <th className='py-1 pe-2 text-start font-medium'>
                {t('platform.plans.name')}
              </th>
              <th className='py-1 pe-2 text-start font-medium'>
                {t('platform.setup.plans.price')}
              </th>
              <th className='py-1 pe-2 text-start font-medium'>
                {t('platform.setup.plans.photos')}
              </th>
              <th className='py-1 text-start font-medium'>
                {t('platform.setup.plans.branches')}
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => {
              const name = r.p.name[i18n.language] ?? r.p.name.en ?? r.p.slug
              const set = (k: 'price' | 'photos' | 'branches', value: string) =>
                setRows(
                  rows.map((x, j) => (j === i ? { ...x, [k]: value } : x))
                )
              return (
                <tr key={r.p.id}>
                  <td className='py-1 pe-2 font-medium'>{name}</td>
                  <td className='py-1 pe-2'>
                    <Input
                      aria-label={t('platform.setup.plans.price_of', {
                        name,
                        currency: r.p.currency,
                      })}
                      inputMode='decimal'
                      className='min-h-11 w-24 sm:min-h-9'
                      value={r.price}
                      onChange={(e) => set('price', e.target.value)}
                    />
                  </td>
                  <td className='py-1 pe-2'>
                    <Input
                      aria-label={t('platform.setup.plans.photos_of', { name })}
                      inputMode='numeric'
                      placeholder='∞'
                      className='min-h-11 w-20 sm:min-h-9'
                      value={r.photos}
                      onChange={(e) => set('photos', e.target.value)}
                    />
                  </td>
                  <td className='py-1'>
                    <Input
                      aria-label={t('platform.setup.plans.branches_of', {
                        name,
                      })}
                      inputMode='numeric'
                      placeholder='∞'
                      className='min-h-11 w-16 sm:min-h-9'
                      value={r.branches}
                      onChange={(e) => set('branches', e.target.value)}
                    />
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
      <p className='text-xs text-muted-foreground'>
        {t('platform.setup.plans.hint')}
      </p>
      <Button
        type='submit'
        className='min-h-11 justify-self-start sm:min-h-9'
        disabled={saving}
      >
        {t('platform.setup.plans.save')}
      </Button>
    </form>
  )
}

function EmailStep({ state }: { state: SetupState }) {
  const { t } = useTranslation()
  const me = useMe()
  const qc = useQueryClient()
  const settings = useQuery(settingsQ())
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(
    null
  )
  const signup = settings.data as unknown as
    { signup_open?: boolean; signup_approval?: boolean } | undefined
  const toggle = async (k: 'signup_open' | 'signup_approval', v: boolean) => {
    await send('put', 'settings', { [k]: v })
    await qc.invalidateQueries({ queryKey: ['platform'] })
  }
  return (
    <>
      <Status
        ok={check(state, 'email')}
        label={t(
          check(state, 'email')
            ? 'platform.setup.email.smtp_on'
            : 'platform.setup.email.smtp_off'
        )}
        hint={t('platform.setup.email.smtp_hint')}
      />
      <div className='flex flex-wrap items-center gap-2'>
        <Button
          variant='outline'
          className='min-h-11 sm:min-h-9'
          onClick={async () => {
            setResult(null)
            try {
              await send('post', 'settings/mail/test', { to: me.email })
              setResult({
                ok: true,
                text: t('platform.setup.email.test_sent', {
                  email: me.email,
                }),
              })
            } catch (x) {
              const msg = (x as { response?: { data?: { message?: string } } })
                .response?.data?.message
              setResult({
                ok: false,
                text: msg ?? t('platform.setup.save_error'),
              })
            }
          }}
        >
          {t('platform.setup.email.test')}
        </Button>
        {result && (
          <p
            role='status'
            className={`text-sm ${result.ok ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive'}`}
          >
            {result.text}
          </p>
        )}
      </div>
      <Status
        ok={check(state, 'firebase')}
        label={t(
          check(state, 'firebase')
            ? 'platform.setup.email.firebase_on'
            : 'platform.setup.email.firebase_off'
        )}
        hint={t('platform.setup.email.firebase_hint')}
      />
      {signup && (
        <div className='grid gap-2 rounded-lg border p-3'>
          <p className='font-medium'>{t('platform.setup.email.signup')}</p>
          <label className='flex min-h-11 items-center justify-between gap-3'>
            <span className='text-sm'>{t('platform.signup.signup_open')}</span>
            <Switch
              defaultChecked={signup.signup_open !== false}
              onCheckedChange={(v) => void toggle('signup_open', v)}
            />
          </label>
          <label className='flex min-h-11 items-center justify-between gap-3'>
            <span className='text-sm'>
              {t('platform.signup.signup_approval')}
            </span>
            <Switch
              defaultChecked={signup.signup_approval === true}
              onCheckedChange={(v) => void toggle('signup_approval', v)}
            />
          </label>
        </div>
      )}
    </>
  )
}

function SwitchesStep({ state }: { state: SetupState }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [asking, setAsking] = useState(false)
  return (
    <>
      <div
        onClickCapture={() =>
          void send('put', 'setup', { checked: 'features' })
        }
      >
        <FeatureSwitches />
      </div>
      <div className='grid gap-2 rounded-lg border p-3'>
        <p className='font-medium'>{t('platform.setup.switches.demo')}</p>
        {state.demo_restaurants === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('platform.setup.switches.no_demo')}
          </p>
        ) : (
          <>
            <p className='text-sm'>
              {t('platform.setup.switches.demo_found', {
                count: state.demo_restaurants,
              })}
            </p>
            <Button
              variant='destructive'
              className='min-h-11 justify-self-start sm:min-h-9'
              onClick={() => setAsking(true)}
            >
              {t('platform.setup.switches.delete_demo')}
            </Button>
          </>
        )}
      </div>
      <ConfirmDialog
        open={asking}
        onOpenChange={setAsking}
        title={t('platform.setup.switches.delete_demo')}
        desc={t('platform.setup.switches.delete_confirm', {
          count: state.demo_restaurants,
        })}
        destructive
        handleConfirm={async () => {
          await send('post', 'setup/demo/delete', { confirm: true })
          setAsking(false)
          await qc.invalidateQueries({ queryKey: ['platform'] })
        }}
      />
    </>
  )
}
