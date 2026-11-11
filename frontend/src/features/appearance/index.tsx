import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Eye, EyeOff, Loader2 } from 'lucide-react'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { PhotoField } from '@/features/menu/components/photo-field'
import { type Media } from '@/features/menu/types'
import { useMenuContext } from '@/features/menu/use-menu-context'
import {
  type Appearance,
  type AppearanceValues,
  type Font,
  appearanceQuery,
  previewUrl,
  useSaveAppearance,
} from './api'
import { isHex, tooLightOnWhite } from './contrast'
import { StylePicker } from './style-picker'

const FONTS: Font[] = ['system', 'serif', 'rounded']
const FLAGS = [
  'show_prices',
  'show_photos',
  'show_descriptions',
  'show_calories',
] as const
const FONT_STACK: Record<Font, string> = {
  system: 'system-ui, sans-serif',
  serif: '"Iowan Old Style", Palatino, Georgia, serif',
  rounded: 'ui-rounded, "SF Pro Rounded", Nunito, system-ui, sans-serif',
}

/** Appearance: settings on the left, the live guest page on the right. */
export function AppearancePage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const query = useQuery(appearanceQuery(ctx.tenantId))

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('appearance.title')}
          </h1>
          <p className='text-muted-foreground'>{t('appearance.description')}</p>
        </div>
        {query.data ? (
          <AppearanceForm initial={query.data} />
        ) : (
          <div className='grid gap-4 lg:grid-cols-[1fr_380px]'>
            <Skeleton className='h-96' />
            <Skeleton className='hidden h-[640px] lg:block' />
          </div>
        )}
      </Main>
    </>
  )
}

function valuesOf(a: Appearance): AppearanceValues {
  return {
    primary_color: a.primary_color,
    font: a.font,
    style: a.style,
    banner_text: a.banner_text,
    show_prices: a.show_prices,
    show_photos: a.show_photos,
    show_descriptions: a.show_descriptions,
    show_calories: a.show_calories,
    theme_id: a.theme_id,
    logo_media_id: a.logo?.id ?? null,
    cover_media_id: a.cover?.id ?? null,
  }
}

function AppearanceForm({ initial }: { initial: Appearance }) {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const save = useSaveAppearance(ctx.tenantId)
  const [values, setValues] = useState(() => valuesOf(initial))
  const [saved, setSaved] = useState(() => valuesOf(initial))
  const [logo, setLogo] = useState<Media | null>(initial.logo)
  const [cover, setCover] = useState<Media | null>(initial.cover)
  const [colourText, setColourText] = useState(initial.primary_color)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [src, setSrc] = useState(() => previewUrl(ctx.tenantId, values))
  const [showPreview, setShowPreview] = useState(false)
  const [loading, setLoading] = useState(true)
  const disabled = !ctx.canManage || !ctx.canWrite
  const dirty = JSON.stringify(values) !== JSON.stringify(saved)

  // The preview follows the settings, a moment after the last change.
  useEffect(() => {
    const next = previewUrl(ctx.tenantId, values)
    // A new menu style shows at once; typing a colour waits for a pause.
    const styleChanged = !src.includes(`style=${values.style}`)
    const timer = setTimeout(
      () => {
        if (next !== src) {
          setLoading(true)
          setSrc(next)
        }
      },
      styleChanged ? 0 : 350
    )
    return () => clearTimeout(timer)
  }, [ctx.tenantId, values, src])

  function set<K extends keyof AppearanceValues>(
    key: K,
    value: AppearanceValues[K]
  ) {
    setValues((v) => ({
      ...v,
      [key]: value,
      theme_id: key === 'theme_id' ? (value as number | null) : v.theme_id,
    }))
  }

  function colour(value: string) {
    setColourText(value)
    if (isHex(value)) set('primary_color', value.toLowerCase())
  }

  async function submit() {
    setErrors({})
    try {
      const data = await save.mutateAsync(values)
      const next = valuesOf(data)
      setSaved(next)
      setValues(next)
      toast.success(t('appearance.saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const warn = tooLightOnWhite(values.primary_color)
  // The banner's shape and default name position follow the menu style.
  const bannerRatio = initial.banner[values.style].ratio
  const bannerText = values.banner_text ?? initial.banner[values.style].text

  return (
    <div className='grid items-start gap-4 lg:grid-cols-[1fr_380px]'>
      <div className='grid min-w-0 gap-4'>
        <Card>
          <CardHeader>
            <CardTitle>{t('appearance.themes')}</CardTitle>
            <CardDescription>{t('appearance.themes_hint')}</CardDescription>
          </CardHeader>
          <CardContent className='grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5'>
            {initial.themes.map((theme) => (
              <button
                key={theme.id}
                type='button'
                disabled={disabled}
                aria-pressed={values.theme_id === theme.id}
                onClick={() => {
                  setColourText(theme.primary)
                  setValues((v) => ({
                    ...v,
                    primary_color: theme.primary,
                    font: theme.font,
                    style: initial.styles.includes(theme.style)
                      ? theme.style
                      : v.style,
                    theme_id: theme.id,
                  }))
                }}
                className={cn(
                  'flex min-h-11 items-center gap-2 rounded-md border p-2 text-start text-sm hover:bg-accent disabled:opacity-60',
                  values.theme_id === theme.id &&
                    'border-primary ring-2 ring-primary/30'
                )}
              >
                <span
                  className='size-6 flex-none rounded-full border'
                  style={{ background: theme.primary }}
                />
                <span style={{ fontFamily: FONT_STACK[theme.font] }}>
                  {theme.name}
                </span>
              </button>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('appearance.style')}</CardTitle>
          </CardHeader>
          <CardContent className='grid gap-5'>
            <div className='grid gap-2'>
              <Label htmlFor='primary'>{t('appearance.colour')}</Label>
              <div className='flex items-center gap-2'>
                <input
                  type='color'
                  aria-label={t('appearance.pick_colour')}
                  value={values.primary_color}
                  disabled={disabled}
                  onChange={(e) => colour(e.target.value)}
                  className='h-11 w-14 cursor-pointer rounded-md border bg-transparent p-1 sm:h-9'
                />
                <Input
                  id='primary'
                  value={colourText}
                  maxLength={7}
                  disabled={disabled}
                  onChange={(e) => colour(e.target.value.trim())}
                  aria-invalid={
                    !isHex(colourText) || !!errors.primary_color || undefined
                  }
                  className='w-32 font-mono'
                />
              </div>
              {warn && (
                <p className='flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400'>
                  <AlertTriangle className='mt-0.5 size-4 flex-none' />
                  {t('appearance.contrast_light')}
                </p>
              )}
              {errors.primary_color && (
                <p className='text-sm text-destructive'>
                  {errors.primary_color}
                </p>
              )}
            </div>

            <fieldset className='grid gap-2'>
              <legend className='mb-2 text-sm font-medium'>
                {t('appearance.font')}
              </legend>
              <div className='flex flex-wrap gap-2'>
                {FONTS.map((font) => (
                  <Choice
                    key={font}
                    name='font'
                    checked={values.font === font}
                    disabled={disabled}
                    onChange={() => set('font', font)}
                  >
                    <span style={{ fontFamily: FONT_STACK[font] }}>
                      {t(`appearance.fonts.${font}`)}
                    </span>
                  </Choice>
                ))}
              </div>
            </fieldset>

            <StylePicker
              value={values.style}
              allowed={initial.styles}
              disabled={disabled}
              onChange={(style) => set('style', style)}
            />

            <div className='grid gap-3'>
              {FLAGS.map((flag) => (
                <div
                  key={flag}
                  className='flex items-center justify-between gap-4'
                >
                  <Label htmlFor={flag}>{t(`appearance.flags.${flag}`)}</Label>
                  <Switch
                    id={flag}
                    checked={values[flag]}
                    disabled={disabled}
                    onCheckedChange={(on) => set(flag, on)}
                  />
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('appearance.images')}</CardTitle>
            <CardDescription>{t('appearance.images_hint')}</CardDescription>
          </CardHeader>
          <CardContent className='grid gap-4 sm:grid-cols-2'>
            <PhotoField
              tenantId={ctx.tenantId}
              media={logo}
              aspect={1}
              label={t('appearance.logo')}
              disabled={disabled}
              onChange={(m) => {
                setLogo(m)
                set('logo_media_id', m?.id ?? null)
              }}
            />
            <PhotoField
              tenantId={ctx.tenantId}
              media={cover}
              aspect={bannerRatio[0] / bannerRatio[1]}
              label={t('appearance.cover')}
              hint={t('appearance.banner_size', {
                width: 1600,
                height: Math.round((1600 * bannerRatio[1]) / bannerRatio[0]),
              })}
              purpose='banner'
              disabled={disabled}
              onChange={(m) => {
                setCover(m)
                set('cover_media_id', m?.id ?? null)
              }}
            />
            {cover && (
              <fieldset className='grid gap-2 sm:col-span-2'>
                <legend className='mb-2 text-sm font-medium'>
                  {t('appearance.banner_text')}
                </legend>
                <div className='flex flex-wrap gap-2'>
                  {(['over', 'under'] as const).map((place) => (
                    <Choice
                      key={place}
                      name='banner_text'
                      checked={bannerText === place}
                      disabled={disabled}
                      onChange={() => set('banner_text', place)}
                    >
                      {t(`appearance.banner_texts.${place}`)}
                    </Choice>
                  ))}
                </div>
              </fieldset>
            )}
          </CardContent>
        </Card>

        <div className='sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur'>
          <Button
            onClick={submit}
            disabled={disabled || !dirty || save.isPending}
          >
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('appearance.save')}
          </Button>
          <Button
            variant='outline'
            disabled={!dirty || save.isPending}
            onClick={() => {
              setValues(saved)
              setColourText(saved.primary_color)
              setLogo(
                initial.logo?.id === saved.logo_media_id ? initial.logo : logo
              )
              setCover(
                initial.cover?.id === saved.cover_media_id
                  ? initial.cover
                  : cover
              )
            }}
          >
            {t('appearance.reset')}
          </Button>
          <Button
            variant='ghost'
            className='ms-auto lg:hidden'
            aria-expanded={showPreview}
            onClick={() => setShowPreview((v) => !v)}
          >
            {showPreview ? <EyeOff /> : <Eye />}
            {t('appearance.preview')}
          </Button>
          {dirty && !disabled && (
            <span className='text-sm text-muted-foreground'>
              {t('appearance.unsaved')}
            </span>
          )}
        </div>
      </div>

      <div
        className={cn(
          'lg:sticky lg:top-20 lg:block',
          showPreview ? 'block' : 'hidden'
        )}
      >
        <div className='relative mx-auto h-[640px] w-[340px] overflow-hidden rounded-[2rem] border-8 border-foreground/80 bg-background shadow-lg'>
          {loading && (
            <Skeleton className='absolute inset-0 rounded-none' aria-hidden />
          )}
          <iframe
            title={t('appearance.preview')}
            src={src}
            onLoad={() => setLoading(false)}
            className='relative size-full'
            sandbox='allow-scripts allow-same-origin'
          />
        </div>
        <p className='mt-2 text-center text-xs text-muted-foreground'>
          {t('appearance.preview_hint')}
        </p>
      </div>
    </div>
  )
}

function Choice({
  name,
  checked,
  disabled,
  onChange,
  children,
}: {
  name: string
  checked: boolean
  disabled: boolean
  onChange: () => void
  children: React.ReactNode
}) {
  return (
    <label
      className={cn(
        'flex min-h-11 cursor-pointer items-center rounded-md border px-3 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring sm:min-h-9',
        checked && 'border-primary bg-primary/5 font-medium',
        disabled && 'cursor-not-allowed opacity-60'
      )}
    >
      <input
        type='radio'
        name={name}
        className='sr-only'
        checked={checked}
        disabled={disabled}
        onChange={onChange}
      />
      {children}
    </label>
  )
}
