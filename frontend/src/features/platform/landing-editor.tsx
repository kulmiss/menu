import { useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  ExternalLink,
  FileText,
  Loader2,
  RotateCcw,
  Upload,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { superPath } from '@/lib/area'
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
import { send, settingsQ, usePlatformMutation } from './api'
import {
  BUTTONS,
  type Fixed,
  fieldLabel,
  keysOf,
  type LandingData,
  landingQ,
  type Section,
  SLOTS,
  type Slot,
} from './landing-editor-data'
import { LandingSettings, type LandingSettingsData } from './landing-settings'
import { PageTitle } from './layout'

/** Super Admin → Landing page (owner, 2026-11-17). */
export function LandingEditorPage() {
  const { t } = useTranslation()
  const landing = useQuery(landingQ())
  const settings = useQuery(settingsQ())
  return (
    <>
      <PageTitle
        title={t('platform.nav.landing')}
        hint={t('platform.landing_editor.hint')}
      >
        <Button asChild variant='outline' className='min-h-11 sm:min-h-9'>
          <a href='/' target='_blank' rel='noopener'>
            <ExternalLink aria-hidden /> {t('platform.landing.open')}
          </a>
        </Button>
        {/* One-page sales sheet for owners: print it or save it as PDF (QUESTIONS #234). */}
        <Button asChild variant='outline' className='min-h-11 sm:min-h-9'>
          <a
            href={`${superPath() ?? '/super'}/sales-sheet`}
            target='_blank'
            rel='noopener'
          >
            <FileText aria-hidden /> {t('platform.landing.sales_sheet')}
          </a>
        </Button>
      </PageTitle>
      {landing.data ? (
        <div className='grid gap-6'>
          <ClicksCard clicks={landing.data.clicks} />
          <TextsEditor data={landing.data} />
          <ImagesCard images={landing.data.images} />
          {settings.data ? (
            <LandingSettings
              initial={settings.data as unknown as LandingSettingsData}
            />
          ) : (
            <Skeleton className='h-64' />
          )}
        </div>
      ) : (
        <Skeleton className='h-64' />
      )}
    </>
  )
}

/** Privacy-friendly click counts: a number per button and day, nothing else. */
function ClicksCard({ clicks }: { clicks: LandingData['clicks'] }) {
  const { t } = useTranslation()
  const recent = clicks.days.slice(-14).reverse()
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing_editor.clicks')}</CardTitle>
        <CardDescription>
          {t('platform.landing_editor.clicks_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-4'>
        <div className='grid gap-3 sm:grid-cols-3'>
          {BUTTONS.map((b) => (
            <div key={b} className='rounded-lg border p-4'>
              <p className='text-sm text-muted-foreground'>
                {t(`platform.landing_editor.button.${b}`)}
              </p>
              <p className='text-3xl font-bold tabular-nums'>
                {clicks.totals[b].toLocaleString()}
              </p>
              <p className='text-xs text-muted-foreground'>
                {t('platform.landing_editor.last_30')} ·{' '}
                {t('platform.landing_editor.all_time', {
                  n: clicks.all_time[b].toLocaleString(),
                })}
              </p>
            </div>
          ))}
        </div>
        <Collapsible>
          <CollapsibleTrigger asChild>
            <Button variant='ghost' className='group min-h-11 sm:min-h-9'>
              <ChevronDown
                aria-hidden
                className='transition-transform group-data-[state=open]:rotate-180'
              />
              {t('platform.landing_editor.by_day')}
            </Button>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className='overflow-x-auto'>
              <table className='w-full text-sm'>
                <thead>
                  <tr className='border-b text-start text-muted-foreground'>
                    <th className='py-2 text-start font-medium'>
                      {t('platform.landing_editor.day')}
                    </th>
                    {BUTTONS.map((b) => (
                      <th key={b} className='py-2 text-end font-medium'>
                        {t(`platform.landing_editor.button.${b}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {recent.map((d) => (
                    <tr key={d.day} className='border-b last:border-0'>
                      <td className='py-2'>
                        {new Date(`${d.day}T12:00:00`).toLocaleDateString(
                          undefined,
                          { weekday: 'short', day: 'numeric', month: 'short' }
                        )}
                      </td>
                      {BUTTONS.map((b) => (
                        <td key={b} className='py-2 text-end tabular-nums'>
                          {d[b]}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </CardContent>
    </Card>
  )
}

/** Every text, sections on/off and their order, saved together. */
function TextsEditor({ data }: { data: LandingData }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'landing', body)
  )
  const keys = Object.keys(data.defaults)
  const [texts, setTexts] = useState<Record<string, string>>(data.texts)
  const [sections, setSections] = useState(data.sections)
  const [order, setOrder] = useState<Section[]>(data.order)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const move = (i: number, by: -1 | 1) => {
    const next = [...order]
    ;[next[i], next[i + by]] = [next[i + by], next[i]]
    setOrder(next)
  }

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({ texts, sections, order })
      toast.success(t('platform.saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
      toast.error(t('platform.landing_editor.fix_errors'))
    }
  }

  const group = (id: Fixed | Section, extra?: React.ReactNode) => (
    <TextGroup
      key={id}
      id={id}
      keys={keysOf(id, keys)}
      all={keys}
      data={data}
      texts={texts}
      errors={errors}
      onChange={(k, v) => setTexts((x) => ({ ...x, [k]: v }))}
      header={extra}
    />
  )

  return (
    <form className='grid gap-4' onSubmit={submit}>
      <Card>
        <CardHeader>
          <CardTitle>{t('platform.landing_editor.texts')}</CardTitle>
          <CardDescription>
            {t('platform.landing_editor.texts_hint')}
          </CardDescription>
        </CardHeader>
        <CardContent className='grid gap-3'>
          {group('hero')}
          {order.map((s, i) => (
            <div key={s}>
              {group(
                s,
                <div className='flex items-center gap-1'>
                  <label className='inline-flex size-11 items-center justify-center sm:size-9'>
                    <Switch
                      checked={sections[s]}
                      onCheckedChange={(on) =>
                        setSections((x) => ({ ...x, [s]: on }))
                      }
                      aria-label={t('platform.landing_editor.show', {
                        section: t(`platform.landing.section.${s}`),
                      })}
                    />
                  </label>
                  <Button
                    type='button'
                    variant='ghost'
                    size='icon'
                    className='size-11 sm:size-9'
                    disabled={i === 0}
                    onClick={() => move(i, -1)}
                    aria-label={t('platform.landing_editor.up', {
                      section: t(`platform.landing.section.${s}`),
                    })}
                  >
                    <ArrowUp />
                  </Button>
                  <Button
                    type='button'
                    variant='ghost'
                    size='icon'
                    className='size-11 sm:size-9'
                    disabled={i === order.length - 1}
                    onClick={() => move(i, 1)}
                    aria-label={t('platform.landing_editor.down', {
                      section: t(`platform.landing.section.${s}`),
                    })}
                  >
                    <ArrowDown />
                  </Button>
                </div>
              )}
            </div>
          ))}
          {group('footer')}
          {group('buttons')}
          {group('search')}
        </CardContent>
      </Card>
      <div className='sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur'>
        <Button
          type='submit'
          className='min-h-11 sm:min-h-9'
          disabled={save.isPending}
        >
          {save.isPending && <Loader2 className='animate-spin' />}
          {t('platform.landing_editor.save')}
        </Button>
        <p className='text-sm text-muted-foreground'>
          {t('platform.landing_editor.save_hint')}
        </p>
      </div>
    </form>
  )
}

function TextGroup({
  id,
  keys,
  all,
  data,
  texts,
  errors,
  onChange,
  header,
}: {
  id: string
  keys: string[]
  all: string[]
  data: LandingData
  texts: Record<string, string>
  errors: Record<string, string>
  onChange: (key: string, value: string) => void
  header?: React.ReactNode
}) {
  const { t } = useTranslation()
  const changed = keys.filter((k) => (texts[k] ?? '').trim() !== '').length
  const hasError = keys.some((k) => errors[`texts.${k}`])
  const [open, setOpen] = useState(false)
  const title =
    id in data.sections
      ? t(`platform.landing.section.${id}`)
      : t(`platform.landing_editor.group.${id}`)
  return (
    <Collapsible
      open={open || hasError}
      onOpenChange={setOpen}
      className='rounded-lg border'
    >
      <div className='flex flex-wrap items-center gap-2 p-2 ps-3'>
        <CollapsibleTrigger asChild>
          <Button
            type='button'
            variant='ghost'
            className='group me-auto min-h-11 justify-start px-2 text-base font-semibold sm:min-h-9'
          >
            <ChevronDown
              aria-hidden
              className='transition-transform group-data-[state=open]:rotate-180'
            />
            {title}
            {changed > 0 && (
              <span className='rounded-full bg-muted px-2 text-xs font-normal text-muted-foreground'>
                {t('platform.landing_editor.changed', { count: changed })}
              </span>
            )}
          </Button>
        </CollapsibleTrigger>
        {header}
      </div>
      <CollapsibleContent className='grid gap-4 border-t p-4'>
        {keys.map((k) => {
          const long = (data.max[k] ?? 160) > 160
          const value = texts[k] ?? ''
          const fieldId = `lp-${k.replace(/\./g, '-')}`
          return (
            <div key={k} className='grid gap-1'>
              <div className='flex items-end justify-between gap-2'>
                <Label htmlFor={fieldId}>{fieldLabel(k, all, t)}</Label>
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
              {long ? (
                <Textarea
                  id={fieldId}
                  rows={2}
                  value={value}
                  maxLength={data.max[k]}
                  placeholder={data.defaults[k]}
                  aria-invalid={!!errors[`texts.${k}`]}
                  onChange={(e) => onChange(k, e.target.value)}
                />
              ) : (
                <Input
                  id={fieldId}
                  value={value}
                  maxLength={data.max[k]}
                  placeholder={data.defaults[k]}
                  aria-invalid={!!errors[`texts.${k}`]}
                  onChange={(e) => onChange(k, e.target.value)}
                />
              )}
              {errors[`texts.${k}`] && (
                <p className='text-sm text-destructive'>
                  {errors[`texts.${k}`]}
                </p>
              )}
            </div>
          )
        })}
      </CollapsibleContent>
    </Collapsible>
  )
}

/** Phone screenshots (light and dark) and the link preview, each with "Use the built-in one". */
function ImagesCard({ images }: { images: LandingData['images'] }) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing_editor.images')}</CardTitle>
        <CardDescription>
          {t('platform.landing_editor.images_hint')}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
        {SLOTS.map((slot) => (
          <ImageSlot key={slot} slot={slot} image={images[slot]} />
        ))}
      </CardContent>
    </Card>
  )
}

function ImageSlot({
  slot,
  image,
}: {
  slot: Slot
  image: { url: string; custom: boolean }
}) {
  const { t } = useTranslation()
  const file = useRef<HTMLInputElement>(null)
  const upload = usePlatformMutation((f: File) => {
    const form = new FormData()
    form.append('image', f)
    return send('post', `landing/images/${slot}`, form)
  }, false)
  const reset = usePlatformMutation(
    () => send('delete', `landing/images/${slot}`),
    false
  )
  const busy = upload.isPending || reset.isPending
  const name = t(`platform.landing_editor.slot.${slot}`)
  return (
    <div className='grid content-start gap-2 rounded-lg border p-3'>
      <p className='font-medium'>{name}</p>
      <img
        src={image.url}
        alt=''
        loading='lazy'
        className={cn(
          'rounded-md border bg-muted object-cover',
          slot === 'og'
            ? 'aspect-[1200/630] w-full'
            : 'aspect-[300/608] w-32 justify-self-center'
        )}
      />
      <p className='text-sm text-muted-foreground'>
        {image.custom
          ? t('platform.landing_editor.custom')
          : t('platform.landing_editor.built_in')}
      </p>
      <input
        ref={file}
        type='file'
        accept='image/jpeg,image/png,image/webp'
        className='sr-only'
        aria-label={t('platform.landing_editor.choose', { name })}
        onChange={async (e) => {
          const f = e.target.files?.[0]
          e.target.value = ''
          if (!f) return
          try {
            await upload.mutateAsync(f)
            toast.success(t('platform.landing_editor.image_saved'))
          } catch (err) {
            toast.error(
              Object.values(validationErrors(err) ?? {})[0] ??
                t('platform.landing_editor.image_failed')
            )
          }
        }}
      />
      <div className='flex flex-wrap gap-2'>
        <Button
          type='button'
          variant='outline'
          className='min-h-11 sm:min-h-9'
          disabled={busy}
          onClick={() => file.current?.click()}
        >
          {upload.isPending ? <Loader2 className='animate-spin' /> : <Upload />}
          {t('platform.landing_editor.upload')}
        </Button>
        {image.custom && (
          <Button
            type='button'
            variant='ghost'
            className='min-h-11 sm:min-h-9'
            disabled={busy}
            onClick={async () => {
              await reset.mutateAsync(undefined)
              toast.success(t('platform.landing_editor.image_reset'))
            }}
          >
            <RotateCcw /> {t('platform.landing_editor.use_built_in')}
          </Button>
        )}
      </div>
    </div>
  )
}
