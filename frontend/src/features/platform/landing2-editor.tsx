import { useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ChevronDown,
  ExternalLink,
  Loader2,
  Plus,
  RotateCcw,
  Trash2,
  Upload,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { send, usePlatformMutation } from './api'
import {
  fieldLabel,
  type Group,
  GROUPS,
  groupOf,
  type Landing2Data,
  landing2Q,
  type Locale,
  LOCALES,
  type Numbers,
  OWN_NUMBERS,
  type Payment,
  type Restaurant,
  SECTIONS,
} from './landing2-editor-data'
import { PageTitle } from './layout'

/**
 * Super Admin → Landing 2 (owner, 2026-11-22): every part ON/OFF, every text in English and
 * Amharic, real restaurants, payment methods, real numbers, the demo video and the footer in Amharic.
 */
export function Landing2EditorPage() {
  const { t } = useTranslation()
  const data = useQuery(landing2Q())
  return (
    <>
      <PageTitle
        title={t('platform.nav.landing2')}
        hint={t('platform.landing2_editor.hint')}
      >
        {LOCALES.map((l) => (
          <Button
            key={l}
            asChild
            variant='outline'
            className='min-h-11 sm:min-h-9'
          >
            <a href={data.data?.urls[l] ?? '#'} target='_blank' rel='noopener'>
              <ExternalLink aria-hidden />{' '}
              {t(`platform.landing2_editor.open_${l}`)}
            </a>
          </Button>
        ))}
      </PageTitle>
      {data.data ? (
        <div className='grid gap-6'>
          <SettingsForm data={data.data} />
          <RestaurantsCard data={data.data} />
          <VideoCard data={data.data} />
        </div>
      ) : (
        <Skeleton className='h-64' />
      )}
    </>
  )
}

/** Switches, texts, payment methods, numbers and the footer in Amharic: saved together. */
function SettingsForm({ data }: { data: Landing2Data }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'landing2', body)
  )
  const [sections, setSections] = useState(data.sections)
  const [texts, setTexts] = useState(data.texts)
  const [payments, setPayments] = useState<Payment[]>(data.payments)
  const [numbers, setNumbers] = useState<Numbers>(data.numbers)
  const [footerAm, setFooterAm] = useState<Record<string, string>>(
    data.footer.am
  )
  const [errors, setErrors] = useState<Record<string, string>>({})

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        sections,
        texts,
        payments,
        numbers,
        footer_am: footerAm,
      })
      toast.success(t('platform.saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
      toast.error(t('platform.landing_editor.fix_errors'))
    }
  }

  return (
    <form className='grid gap-6' onSubmit={submit}>
      <Card>
        <CardHeader>
          <CardTitle>{t('platform.landing2_editor.parts')}</CardTitle>
          <CardDescription>
            {t('platform.landing2_editor.parts_hint')}
          </CardDescription>
        </CardHeader>
        <CardContent className='grid gap-1 sm:grid-cols-2'>
          {SECTIONS.map((s) => (
            <label
              key={s}
              className='flex min-h-11 items-center justify-between gap-3 rounded-md px-2 hover:bg-accent'
            >
              <span>{t(`platform.landing2_editor.section.${s}`)}</span>
              <Switch
                checked={sections[s]}
                onCheckedChange={(on) =>
                  setSections((x) => ({ ...x, [s]: on }))
                }
              />
            </label>
          ))}
        </CardContent>
      </Card>

      <TextsCard
        data={data}
        texts={texts}
        errors={errors}
        onChange={(l, k, v) =>
          setTexts((x) => ({ ...x, [l]: { ...x[l], [k]: v } }))
        }
      />

      <PaymentsCard
        icons={data.payment_icons}
        payments={payments}
        setPayments={setPayments}
        errors={errors}
      />

      <NumbersCard
        data={data}
        numbers={numbers}
        setNumbers={setNumbers}
        errors={errors}
      />

      <FooterCard
        data={data}
        values={footerAm}
        onChange={(k, v) => setFooterAm((x) => ({ ...x, [k]: v }))}
      />

      <div className='sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur'>
        <Button
          type='submit'
          className='min-h-11 sm:min-h-9'
          disabled={save.isPending}
        >
          {save.isPending && <Loader2 className='animate-spin' />}
          {t('platform.landing2_editor.save')}
        </Button>
        <p className='text-sm text-muted-foreground'>
          {t('platform.landing2_editor.save_hint')}
        </p>
      </div>
    </form>
  )
}

function TextsCard({
  data,
  texts,
  errors,
  onChange,
}: {
  data: Landing2Data
  texts: Landing2Data['texts']
  errors: Record<string, string>
  onChange: (locale: Locale, key: string, value: string) => void
}) {
  const { t } = useTranslation()
  const [locale, setLocale] = useState<Locale>('en')
  const keys = Object.keys(data.defaults.en)
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.texts')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.texts_hint')}
        </CardDescription>
        <div
          className='mt-2 inline-flex w-fit gap-1 rounded-lg border p-1'
          role='group'
          aria-label={t('platform.landing2_editor.language')}
        >
          {LOCALES.map((l) => (
            <Button
              key={l}
              type='button'
              size='sm'
              variant={locale === l ? 'default' : 'ghost'}
              aria-pressed={locale === l}
              className='min-h-11 sm:min-h-8'
              onClick={() => setLocale(l)}
            >
              <span lang={l}>{t(`platform.landing2_editor.lang_${l}`)}</span>
            </Button>
          ))}
        </div>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {GROUPS.map((g) => (
          <TextGroup
            key={`${locale}-${g}`}
            group={g}
            locale={locale}
            keys={keys.filter((k) => groupOf(k) === g)}
            all={keys}
            data={data}
            texts={texts[locale]}
            errors={errors}
            onChange={(k, v) => onChange(locale, k, v)}
          />
        ))}
      </CardContent>
    </Card>
  )
}

function TextGroup({
  group,
  locale,
  keys,
  all,
  data,
  texts,
  errors,
  onChange,
}: {
  group: Group
  locale: Locale
  keys: string[]
  all: string[]
  data: Landing2Data
  texts: Record<string, string>
  errors: Record<string, string>
  onChange: (key: string, value: string) => void
}) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const err = (k: string) => errors[`texts.${locale}.${k}`]
  const changed = keys.filter((k) => (texts[k] ?? '').trim() !== '').length
  if (keys.length === 0) return null
  return (
    <Collapsible
      open={open || keys.some(err)}
      onOpenChange={setOpen}
      className='rounded-lg border'
    >
      <CollapsibleTrigger asChild>
        <Button
          type='button'
          variant='ghost'
          className='group min-h-11 w-full justify-start px-3 text-base font-semibold sm:min-h-9'
        >
          <ChevronDown
            aria-hidden
            className='transition-transform group-data-[state=open]:rotate-180'
          />
          {t(`platform.landing2_editor.group.${group}`)}
          {changed > 0 && (
            <span className='rounded-full bg-muted px-2 text-xs font-normal text-muted-foreground'>
              {t('platform.landing_editor.changed', { count: changed })}
            </span>
          )}
        </Button>
      </CollapsibleTrigger>
      <CollapsibleContent className='grid gap-4 border-t p-4'>
        {keys.map((k) => {
          const value = texts[k] ?? ''
          const id = `l2-${locale}-${k.replace(/\./g, '-')}`
          const long = (data.max[k] ?? 160) > 160
          const Field = long ? Textarea : Input
          return (
            <div key={k} className='grid gap-1'>
              <div className='flex items-end justify-between gap-2'>
                <Label htmlFor={id}>
                  {fieldLabel(k, all, t)}
                  {locale === 'en' && data.shared.includes(k) && (
                    <span className='ms-2 text-xs font-normal text-muted-foreground'>
                      {t('platform.landing2_editor.shared')}
                    </span>
                  )}
                </Label>
                {value.trim() !== '' && (
                  <Button
                    type='button'
                    variant='ghost'
                    size='sm'
                    className='min-h-11 sm:min-h-8'
                    onClick={() => onChange(k, '')}
                  >
                    <RotateCcw aria-hidden />{' '}
                    {t('platform.landing_editor.default')}
                  </Button>
                )}
              </div>
              <Field
                id={id}
                lang={locale}
                value={value}
                maxLength={data.max[k]}
                placeholder={data.defaults[locale][k]}
                aria-invalid={!!err(k)}
                onChange={(
                  e: React.ChangeEvent<HTMLInputElement & HTMLTextAreaElement>
                ) => onChange(k, e.target.value)}
                {...(long ? { rows: 2 } : {})}
              />
              {err(k) && <p className='text-sm text-destructive'>{err(k)}</p>}
            </div>
          )
        })}
      </CollapsibleContent>
    </Collapsible>
  )
}

function PaymentsCard({
  icons,
  payments,
  setPayments,
  errors,
}: {
  icons: string[]
  payments: Payment[]
  setPayments: React.Dispatch<React.SetStateAction<Payment[]>>
  errors: Record<string, string>
}) {
  const { t } = useTranslation()
  const set = (i: number, patch: Partial<Payment>) =>
    setPayments((x) => x.map((p, j) => (j === i ? { ...p, ...patch } : p)))
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.payments')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.payments_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {payments.map((p, i) => (
          <div
            key={i}
            className='grid items-end gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_9rem_auto_auto]'
          >
            <div className='grid gap-1'>
              <Label htmlFor={`pay-en-${i}`}>
                {t('platform.landing2_editor.label_en')}
              </Label>
              <Input
                id={`pay-en-${i}`}
                value={p.label_en}
                maxLength={40}
                aria-invalid={!!errors[`payments.${i}.label_en`]}
                onChange={(e) => set(i, { label_en: e.target.value })}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor={`pay-am-${i}`}>
                {t('platform.landing2_editor.label_am')}
              </Label>
              <Input
                id={`pay-am-${i}`}
                lang='am'
                value={p.label_am}
                maxLength={40}
                onChange={(e) => set(i, { label_am: e.target.value })}
              />
            </div>
            <div className='grid gap-1'>
              <Label htmlFor={`pay-icon-${i}`}>
                {t('platform.landing2_editor.icon')}
              </Label>
              <select
                id={`pay-icon-${i}`}
                className='h-11 rounded-md border bg-background px-2 sm:h-9'
                value={p.icon}
                onChange={(e) => set(i, { icon: e.target.value })}
              >
                {icons.map((ic) => (
                  <option key={ic} value={ic}>
                    {t(`platform.landing2_editor.icons.${ic}`)}
                  </option>
                ))}
              </select>
            </div>
            <label className='flex min-h-11 items-center gap-2 sm:min-h-9'>
              <Switch checked={p.on} onCheckedChange={(on) => set(i, { on })} />
              {t('platform.landing2_editor.shown')}
            </label>
            <Button
              type='button'
              variant='ghost'
              size='icon'
              className='size-11 sm:size-9'
              aria-label={t('platform.landing2_editor.remove_named', {
                name: p.label_en,
              })}
              onClick={() => setPayments((x) => x.filter((_, j) => j !== i))}
            >
              <Trash2 />
            </Button>
          </div>
        ))}
        {payments.length < 8 && (
          <Button
            type='button'
            variant='outline'
            className='min-h-11 w-fit sm:min-h-9'
            onClick={() =>
              setPayments((x) => [
                ...x,
                { label_en: '', label_am: '', icon: 'phone', on: true },
              ])
            }
          >
            <Plus /> {t('platform.landing2_editor.add_payment')}
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

function NumbersCard({
  data,
  numbers,
  setNumbers,
  errors,
}: {
  data: Landing2Data
  numbers: Numbers
  setNumbers: React.Dispatch<React.SetStateAction<Numbers>>
  errors: Record<string, string>
}) {
  const { t } = useTranslation()
  const setOut = (i: number, patch: Partial<Numbers['outside'][number]>) =>
    setNumbers((x) => ({
      ...x,
      outside: x.outside.map((n, j) => (j === i ? { ...n, ...patch } : n)),
    }))
  const today = data.today
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.numbers')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.numbers_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-4'>
        {data.stale > 0 && (
          <p className='flex items-start gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm'>
            <AlertTriangle className='mt-0.5 size-4 shrink-0' aria-hidden />
            {t('platform.landing2_editor.stale', {
              count: data.stale,
              days: data.stale_days,
            })}
          </p>
        )}
        <div className='grid gap-2'>
          <p className='font-medium'>{t('platform.landing2_editor.own')}</p>
          {OWN_NUMBERS.map((k) => (
            <div
              key={k}
              className='grid items-end gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_10rem_auto]'
            >
              <div>
                <p>{t(`platform.landing2_editor.own_${k}`)}</p>
                <p className='text-sm text-muted-foreground'>
                  {data.counted
                    ? t('platform.landing2_editor.counted', {
                        n: data.counted[k].toLocaleString(),
                        date: new Date(data.counted.at).toLocaleDateString(),
                      })
                    : t('platform.landing2_editor.not_counted')}
                </p>
              </div>
              <div className='grid gap-1'>
                <Label htmlFor={`own-min-${k}`}>
                  {t('platform.landing2_editor.min')}
                </Label>
                <Input
                  id={`own-min-${k}`}
                  type='number'
                  min={0}
                  value={numbers.own[k].min}
                  onChange={(e) =>
                    setNumbers((x) => ({
                      ...x,
                      own: {
                        ...x.own,
                        [k]: { ...x.own[k], min: Number(e.target.value) },
                      },
                    }))
                  }
                />
              </div>
              <label className='flex min-h-11 items-center gap-2 sm:min-h-9'>
                <Switch
                  checked={numbers.own[k].on}
                  onCheckedChange={(on) =>
                    setNumbers((x) => ({
                      ...x,
                      own: { ...x.own, [k]: { ...x.own[k], on } },
                    }))
                  }
                />
                {t('platform.landing2_editor.shown')}
              </label>
            </div>
          ))}
        </div>
        <div className='grid gap-2'>
          <p className='font-medium'>{t('platform.landing2_editor.outside')}</p>
          <p className='text-sm text-muted-foreground'>
            {t('platform.landing2_editor.outside_hint')}
          </p>
          {numbers.outside.map((n, i) => {
            const old = n.date < data.stale_before
            const err = (f: string) => errors[`numbers.outside.${i}.${f}`]
            return (
              <div
                key={i}
                className='grid gap-2 rounded-lg border p-3 sm:grid-cols-3'
              >
                {(
                  [
                    ['value', 'number'],
                    ['label_en', 'text'],
                    ['label_am', 'text'],
                    ['source', 'text'],
                    ['date', 'date'],
                    ['min', 'number'],
                  ] as const
                ).map(([f, type]) => (
                  <div key={f} className='grid gap-1'>
                    <Label htmlFor={`out-${f}-${i}`}>
                      {t(`platform.landing2_editor.out.${f}`)}
                    </Label>
                    <Input
                      id={`out-${f}-${i}`}
                      type={type}
                      lang={f === 'label_am' ? 'am' : undefined}
                      max={type === 'date' ? today : undefined}
                      value={n[f]}
                      aria-invalid={!!err(f)}
                      onChange={(e) =>
                        setOut(i, {
                          [f]:
                            type === 'number'
                              ? Number(e.target.value)
                              : e.target.value,
                        })
                      }
                    />
                    {err(f) && (
                      <p className='text-sm text-destructive'>{err(f)}</p>
                    )}
                  </div>
                ))}
                <div className='flex flex-wrap items-center gap-3 sm:col-span-3'>
                  <label className='flex min-h-11 items-center gap-2 sm:min-h-9'>
                    <Switch
                      checked={n.on}
                      onCheckedChange={(on) => setOut(i, { on })}
                    />
                    {t('platform.landing2_editor.shown')}
                  </label>
                  {old && (
                    <span className='flex items-center gap-1 text-sm text-amber-700 dark:text-amber-400'>
                      <AlertTriangle className='size-4' aria-hidden />
                      {t('platform.landing2_editor.old', {
                        days: data.stale_days,
                      })}
                    </span>
                  )}
                  <Button
                    type='button'
                    variant='ghost'
                    className='ms-auto min-h-11 sm:min-h-9'
                    onClick={() =>
                      setNumbers((x) => ({
                        ...x,
                        outside: x.outside.filter((_, j) => j !== i),
                      }))
                    }
                  >
                    <Trash2 /> {t('platform.landing2_editor.remove')}
                  </Button>
                </div>
              </div>
            )
          })}
          {numbers.outside.length < 6 && (
            <Button
              type='button'
              variant='outline'
              className='min-h-11 w-fit sm:min-h-9'
              onClick={() =>
                setNumbers((x) => ({
                  ...x,
                  outside: [
                    ...x.outside,
                    {
                      value: 0,
                      label_en: '',
                      label_am: '',
                      source: '',
                      date: today,
                      on: true,
                      min: 0,
                    },
                  ],
                }))
              }
            >
              <Plus /> {t('platform.landing2_editor.add_number')}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function FooterCard({
  data,
  values,
  onChange,
}: {
  data: Landing2Data
  values: Record<string, string>
  onChange: (key: string, value: string) => void
}) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.footer')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.footer_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3 sm:grid-cols-2'>
        {data.footer.texts.map((text, i) => (
          <div key={text} className='grid gap-1'>
            <Label htmlFor={`foot-${i}`}>{text}</Label>
            <Input
              id={`foot-${i}`}
              lang='am'
              maxLength={120}
              value={values[text] ?? ''}
              placeholder={data.footer.defaults[text] ?? text}
              onChange={(e) => onChange(text, e.target.value)}
            />
          </div>
        ))}
      </CardContent>
    </Card>
  )
}

/** Real restaurants, each saved on its own (with "permission given"). */
function RestaurantsCard({ data }: { data: Landing2Data }) {
  const { t } = useTranslation()
  const permitted = data.restaurants.filter(
    (r) => r.permission && r.is_active
  ).length
  const add = usePlatformMutation((body: object) =>
    send('post', 'landing2/restaurants', body)
  )
  const blank = {
    name: '',
    city: '',
    quote_en: '',
    quote_am: '',
    permission: false,
    is_active: true,
  }
  const [draft, setDraft] = useState(blank)
  const [errors, setErrors] = useState<Record<string, string>>({})
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.restaurants')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.restaurants_hint', {
            n: data.min_restaurants,
          })}
        </CardDescription>
        <p
          className={cn(
            'text-sm font-medium',
            permitted >= data.min_restaurants
              ? 'text-emerald-700 dark:text-emerald-400'
              : 'text-muted-foreground'
          )}
        >
          {permitted >= data.min_restaurants
            ? t('platform.landing2_editor.restaurants_shown', {
                count: permitted,
              })
            : t('platform.landing2_editor.restaurants_hidden', {
                count: permitted,
                n: data.min_restaurants,
              })}
        </p>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {data.restaurants.map((r, i) => (
          <RestaurantRow
            key={r.id}
            r={r}
            first={i === 0}
            last={i === data.restaurants.length - 1}
            prev={data.restaurants[i - 1]}
            next={data.restaurants[i + 1]}
          />
        ))}
        <form
          className='grid gap-2 rounded-lg border border-dashed p-3'
          onSubmit={async (e) => {
            e.preventDefault()
            setErrors({})
            try {
              await add.mutateAsync(draft)
              setDraft(blank)
              toast.success(t('platform.saved'))
            } catch (err) {
              setErrors(validationErrors(err) ?? {})
            }
          }}
        >
          <p className='font-medium'>
            {t('platform.landing2_editor.add_restaurant')}
          </p>
          <RestaurantFields
            value={draft}
            onChange={setDraft}
            errors={errors}
            idPrefix='new'
          />
          <Button
            type='submit'
            className='min-h-11 w-fit sm:min-h-9'
            disabled={add.isPending}
          >
            {add.isPending ? <Loader2 className='animate-spin' /> : <Plus />}
            {t('platform.landing2_editor.add_restaurant')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

type RestaurantForm = {
  name: string
  city: string
  quote_en: string
  quote_am: string
  permission: boolean
  is_active: boolean
}

function RestaurantFields({
  value,
  onChange,
  errors,
  idPrefix,
}: {
  value: RestaurantForm
  onChange: (v: RestaurantForm) => void
  errors: Record<string, string>
  idPrefix: string
}) {
  const { t } = useTranslation()
  return (
    <div className='grid gap-2 sm:grid-cols-2'>
      {(['name', 'city', 'quote_en', 'quote_am'] as const).map((f) => {
        const id = `rest-${idPrefix}-${f}`
        const Field = f.startsWith('quote') ? Textarea : Input
        return (
          <div key={f} className='grid gap-1'>
            <Label htmlFor={id}>
              {t(`platform.landing2_editor.rest.${f}`)}
            </Label>
            <Field
              id={id}
              lang={f === 'quote_am' ? 'am' : undefined}
              value={value[f]}
              maxLength={f === 'name' ? 80 : f === 'city' ? 60 : 300}
              aria-invalid={!!errors[f]}
              onChange={(
                e: React.ChangeEvent<HTMLInputElement & HTMLTextAreaElement>
              ) => onChange({ ...value, [f]: e.target.value })}
            />
            {errors[f] && (
              <p className='text-sm text-destructive'>{errors[f]}</p>
            )}
          </div>
        )
      })}
      <label className='flex min-h-11 items-center gap-2 sm:col-span-2'>
        <Checkbox
          checked={value.permission}
          onCheckedChange={(c) =>
            onChange({ ...value, permission: c === true })
          }
        />
        {t('platform.landing2_editor.rest.permission')}
      </label>
      <label className='flex min-h-11 items-center gap-2'>
        <Switch
          checked={value.is_active}
          onCheckedChange={(on) => onChange({ ...value, is_active: on })}
        />
        {t('platform.landing2_editor.shown')}
      </label>
    </div>
  )
}

function RestaurantRow({
  r,
  first,
  last,
  prev,
  next,
}: {
  r: Restaurant
  first: boolean
  last: boolean
  prev?: Restaurant
  next?: Restaurant
}) {
  const { t } = useTranslation()
  const toForm = (x: Restaurant): RestaurantForm => ({
    name: x.name,
    city: x.city ?? '',
    quote_en: x.quote_en ?? '',
    quote_am: x.quote_am ?? '',
    permission: x.permission,
    is_active: x.is_active,
  })
  const [value, setValue] = useState<RestaurantForm>(toForm(r))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = usePlatformMutation(
    (body: object) => send('put', `landing2/restaurants/${r.id}`, body),
    false
  )
  const swap = usePlatformMutation(async (other: Restaurant) => {
    await send('put', `landing2/restaurants/${r.id}`, {
      ...toForm(r),
      sort: other.sort,
    })
    await send('put', `landing2/restaurants/${other.id}`, {
      ...toForm(other),
      sort: r.sort,
    })
  }, false)
  const remove = usePlatformMutation(
    () => send('delete', `landing2/restaurants/${r.id}`),
    false
  )
  const file = useRef<HTMLInputElement>(null)
  const logo = usePlatformMutation((f: File | null) => {
    if (f === null) return send('delete', `landing2/restaurants/${r.id}/logo`)
    const form = new FormData()
    form.append('image', f)
    return send('post', `landing2/restaurants/${r.id}/logo`, form)
  }, false)
  return (
    <div className='grid gap-3 rounded-lg border p-3'>
      <div className='flex flex-wrap items-center gap-3'>
        {r.logo ? (
          <img
            src={r.logo}
            alt=''
            className='size-12 rounded-lg border object-cover'
          />
        ) : (
          <span className='grid size-12 place-items-center rounded-lg border bg-muted text-lg font-bold'>
            {r.name.slice(0, 1).toUpperCase()}
          </span>
        )}
        <input
          ref={file}
          type='file'
          accept='image/jpeg,image/png,image/webp'
          className='sr-only'
          aria-label={t('platform.landing2_editor.rest.logo_for', {
            name: r.name,
          })}
          onChange={async (e) => {
            const f = e.target.files?.[0]
            e.target.value = ''
            if (!f) return
            try {
              await logo.mutateAsync(f)
            } catch (err) {
              toast.error(
                Object.values(validationErrors(err) ?? {})[0] ??
                  t('platform.landing_editor.image_failed')
              )
            }
          }}
        />
        <Button
          type='button'
          variant='outline'
          className='min-h-11 sm:min-h-9'
          onClick={() => file.current?.click()}
        >
          {logo.isPending ? <Loader2 className='animate-spin' /> : <Upload />}
          {t('platform.landing2_editor.rest.logo')}
        </Button>
        {r.logo && (
          <Button
            type='button'
            variant='ghost'
            className='min-h-11 sm:min-h-9'
            onClick={() => logo.mutate(null)}
          >
            {t('platform.landing2_editor.remove')}
          </Button>
        )}
        <div className='ms-auto flex gap-1'>
          <Button
            type='button'
            variant='ghost'
            size='icon'
            className='size-11 sm:size-9'
            disabled={first || swap.isPending}
            aria-label={t('platform.landing2_editor.up', { name: r.name })}
            onClick={() => prev && swap.mutate(prev)}
          >
            <ArrowUp />
          </Button>
          <Button
            type='button'
            variant='ghost'
            size='icon'
            className='size-11 sm:size-9'
            disabled={last || swap.isPending}
            aria-label={t('platform.landing2_editor.down', { name: r.name })}
            onClick={() => next && swap.mutate(next)}
          >
            <ArrowDown />
          </Button>
        </div>
      </div>
      <RestaurantFields
        value={value}
        onChange={setValue}
        errors={errors}
        idPrefix={String(r.id)}
      />
      <div className='flex flex-wrap gap-2'>
        <Button
          type='button'
          className='min-h-11 sm:min-h-9'
          disabled={save.isPending}
          onClick={async () => {
            setErrors({})
            try {
              await save.mutateAsync(value)
              toast.success(t('platform.saved'))
            } catch (err) {
              setErrors(validationErrors(err) ?? {})
            }
          }}
        >
          {save.isPending && <Loader2 className='animate-spin' />}
          {t('platform.landing2_editor.save_restaurant')}
        </Button>
        <Button
          type='button'
          variant='ghost'
          className='min-h-11 sm:min-h-9'
          disabled={remove.isPending}
          onClick={() => remove.mutate(undefined)}
        >
          <Trash2 />{' '}
          {t('platform.landing2_editor.remove_named', { name: r.name })}
        </Button>
      </div>
    </div>
  )
}

/** The demo video: replace the built-in one (MP4 ≤ 2 MB, a first picture, captions). */
function VideoCard({ data }: { data: Landing2Data }) {
  const { t } = useTranslation()
  const form = useRef<HTMLFormElement>(null)
  const upload = usePlatformMutation(
    (body: FormData) => send('post', 'landing2/video', body),
    false
  )
  const reset = usePlatformMutation(
    () => send('delete', 'landing2/video'),
    false
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const fields = [
    ['video', 'video/mp4'],
    ['poster', 'image/jpeg,image/png,image/webp'],
    ['captions_en', '.vtt,text/vtt'],
    ['captions_am', '.vtt,text/vtt'],
  ] as const
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing2_editor.video')}</CardTitle>
        <CardDescription>
          {t('platform.landing2_editor.video_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-4'>
        <p className='text-sm'>
          {data.video === null
            ? t('platform.landing2_editor.video_none')
            : data.video.custom
              ? t('platform.landing_editor.custom')
              : t('platform.landing_editor.built_in')}
        </p>
        {data.video && (
          <img
            src={data.video.poster}
            alt=''
            loading='lazy'
            className='aspect-video w-full max-w-sm rounded-md border object-cover'
          />
        )}
        <form
          ref={form}
          className='grid gap-3 sm:grid-cols-2'
          onSubmit={async (e) => {
            e.preventDefault()
            setErrors({})
            try {
              await upload.mutateAsync(new FormData(e.currentTarget))
              e.currentTarget?.reset()
              toast.success(t('platform.saved'))
            } catch (err) {
              setErrors(validationErrors(err) ?? {})
            }
          }}
        >
          {fields.map(([name, accept]) => (
            <div key={name} className='grid gap-1'>
              <Label htmlFor={`video-${name}`}>
                {t(`platform.landing2_editor.video_field.${name}`)}
              </Label>
              <Input
                id={`video-${name}`}
                name={name}
                type='file'
                accept={accept}
                required={name === 'video' || name === 'poster'}
                aria-invalid={!!errors[name]}
              />
              {errors[name] && (
                <p className='text-sm text-destructive'>{errors[name]}</p>
              )}
            </div>
          ))}
          <div className='flex flex-wrap gap-2 sm:col-span-2'>
            <Button
              type='submit'
              className='min-h-11 sm:min-h-9'
              disabled={upload.isPending}
            >
              {upload.isPending ? (
                <Loader2 className='animate-spin' />
              ) : (
                <Upload />
              )}
              {t('platform.landing2_editor.video_upload')}
            </Button>
            {data.video?.custom && (
              <Button
                type='button'
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                disabled={reset.isPending}
                onClick={() => reset.mutate(undefined)}
              >
                <RotateCcw /> {t('platform.landing_editor.use_built_in')}
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
