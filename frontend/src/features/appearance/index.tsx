import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  ArrowDown,
  Eye,
  EyeOff,
  Image as ImageIcon,
  LayoutGrid,
  Loader2,
  Moon,
  Palette,
  Sparkles,
  Sun,
  ToggleRight,
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useFeature } from '@/features/auth/use-me'
import { PhotoField } from '@/features/menu/components/photo-field'
import { type Media } from '@/features/menu/types'
import { useMenuContext } from '@/features/menu/use-menu-context'
import {
  type Appearance,
  type AppearanceValues,
  type Font,
  type Layout,
  MENU_DIRECTIONS,
  type MenuStyle,
  type PreviewScheme,
  appearanceQuery,
  layoutOf,
  previewUrl,
  useSaveAppearance,
} from './api'
import { isHex, tooLightOnWhite } from './contrast'
import { DesktopPreview } from './desktop-preview'
import { LayoutPicker } from './layout-picker'
import { StylePicker } from './style-picker'

const FONTS: Font[] = ['system', 'serif', 'rounded']
/** What guests see, in small groups (photos on or off is the "Text" layout). */
const GUEST_GROUPS = {
  dish: ['show_prices', 'show_descriptions', 'show_calories'],
  buttons: ['show_share', 'show_pdf_link', 'show_order_list'],
  more: ['show_other_branches'],
} as const
type GuestFlag = (typeof GUEST_GROUPS)[keyof typeof GUEST_GROUPS][number]
/** The steps of the page, in order, each a section the step bar scrolls to. */
const STEPS = ['look', 'layout', 'colours', 'images', 'guests'] as const
type Step = (typeof STEPS)[number]
const STEP_ICON: Record<Step, typeof Sparkles> = {
  look: Sparkles,
  layout: LayoutGrid,
  colours: Palette,
  images: ImageIcon,
  guests: ToggleRight,
}
/** Options that belong to one menu style (shown only while that style is picked). */
type StyleFlag =
  | 'show_halal'
  | 'ramadan_mode'
  | 'photos_greyscale'
  | 'mono_accent'
  | 'show_decoration'
/** Styles with "Menu direction": H and M–T (QUESTIONS #229, #232, #243). */
const DIRECTION_STYLES: readonly MenuStyle[] = [
  'arabic',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
  'burger',
  'injera',
  'juice',
]
const STYLE_SWITCHES: Partial<Record<MenuStyle, readonly StyleFlag[]>> = {
  islamic: ['show_halal', 'ramadan_mode'],
  mono: ['photos_greyscale', 'mono_accent'],
  coffee: ['show_decoration'],
  grill: ['show_decoration'],
  garden: ['show_decoration'],
  coastal: ['show_decoration'],
  luxe: ['show_decoration'],
  burger: ['show_decoration'],
  injera: ['show_decoration'],
  juice: ['show_decoration'],
}
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
    menu_layout: a.menu_layout ?? null,
    show_descriptions: a.show_descriptions,
    show_calories: a.show_calories,
    show_share: a.show_share,
    show_pdf_link: a.show_pdf_link,
    show_order_list: a.show_order_list,
    show_other_branches: a.show_other_branches,
    show_logo: a.show_logo,
    show_banner: a.show_banner,
    menu_direction: a.menu_direction ?? 'auto',
    show_halal: a.show_halal ?? false,
    ramadan_mode: a.ramadan_mode ?? false,
    photos_greyscale: a.photos_greyscale ?? false,
    mono_accent: a.mono_accent ?? false,
    show_decoration: a.show_decoration ?? true,
    theme_id: a.theme_id,
    logo_media_id: a.logo?.id ?? null,
    cover_media_id: a.cover?.id ?? null,
  }
}

function AppearanceForm({ initial }: { initial: Appearance }) {
  // Switches for features the plan or the platform has off are not shown.
  const featureOn: Partial<Record<GuestFlag, boolean>> = {
    show_share: useFeature('share'),
    show_pdf_link: useFeature('pdf_menu'),
    show_order_list: useFeature('order_list'),
  }
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const save = useSaveAppearance(ctx.tenantId)
  const [values, setValues] = useState(() => valuesOf(initial))
  const [saved, setSaved] = useState(() => valuesOf(initial))
  const [logo, setLogo] = useState<Media | null>(initial.logo)
  const [cover, setCover] = useState<Media | null>(initial.cover)
  const [colourText, setColourText] = useState(initial.primary_color)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [scheme, setScheme] = useState<PreviewScheme>('light')
  const [src, setSrc] = useState(() =>
    previewUrl(ctx.tenantId, values, 'light')
  )
  const [showPreview, setShowPreview] = useState(false)
  const [loading, setLoading] = useState(true)
  const disabled = !ctx.canManage || !ctx.canWrite
  const dirty = JSON.stringify(values) !== JSON.stringify(saved)

  // The preview follows the settings, a moment after the last change.
  useEffect(() => {
    const next = previewUrl(ctx.tenantId, values, scheme)
    // A new menu style, layout or colour mode shows at once; typing a colour waits for a pause.
    const instant =
      !src.includes(`style=${values.style}&`) ||
      !src.includes(`scheme=${scheme}`) ||
      !src.includes(`show_photos=${values.show_photos ? 1 : 0}`) ||
      !src.includes(`menu_layout=${values.menu_layout ?? ''}&`)
    const timer = setTimeout(
      () => {
        if (next !== src) {
          setLoading(true)
          setSrc(next)
        }
      },
      instant ? 0 : 350
    )
    return () => clearTimeout(timer)
  }, [ctx.tenantId, values, scheme, src])

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

  // Layout (QUESTIONS #242): the style's own needs no setting; "Text" is photos off.
  const own = initial.style_layouts[values.style]
  const layout = layoutOf(
    values.style,
    values.menu_layout,
    values.show_photos,
    initial.style_layouts,
    initial.layouts
  )
  function pickLayout(next: Layout) {
    setValues((v) => {
      const ownNow = initial.style_layouts[v.style]
      if (next === 'text') {
        return ownNow === 'text'
          ? { ...v, menu_layout: null, show_photos: true }
          : { ...v, show_photos: false }
      }
      return {
        ...v,
        show_photos: true,
        menu_layout: next === ownNow ? null : next,
      }
    })
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

  const guestSwitch = (flag: GuestFlag | 'show_logo' | 'show_banner') => {
    const locked =
      flag === 'show_order_list' &&
      (initial.my_order?.reason === 'platform' ||
        initial.my_order?.reason === 'plan')
        ? initial.my_order.reason
        : null
    return (
      <div
        key={flag}
        className='flex min-h-11 items-center justify-between gap-4 sm:min-h-0'
      >
        <Label htmlFor={flag} className='grid gap-0.5'>
          <span>{t(`appearance.flags.${flag}`)}</span>
          {flag === 'show_order_list' && (
            <span className='font-normal text-muted-foreground'>
              {locked
                ? t(`appearance.my_order_locked.${locked}`)
                : t('appearance.flags.show_order_list_hint')}
            </span>
          )}
        </Label>
        <Switch
          id={flag}
          checked={locked ? false : values[flag]}
          disabled={disabled || !!locked}
          onCheckedChange={(on) => set(flag, on)}
        />
      </div>
    )
  }

  return (
    <div className='grid items-start gap-6 lg:grid-cols-[1fr_380px]'>
      <div className='grid min-w-0 gap-5'>
        <StepBar />

        <Section step='look'>
          <StylePicker
            value={values.style}
            allowed={initial.styles}
            disabled={disabled}
            onChange={(style) => set('style', style)}
          />
          {DIRECTION_STYLES.includes(values.style) && (
            <fieldset className='grid gap-2'>
              <legend className='mb-1 text-sm font-medium'>
                {t('appearance.direction')}
              </legend>
              <p className='mb-1 text-sm text-muted-foreground'>
                {t('appearance.direction_hint')}
              </p>
              <div className='flex flex-wrap gap-2'>
                {MENU_DIRECTIONS.map((dir) => (
                  <Choice
                    key={dir}
                    name='menu_direction'
                    checked={values.menu_direction === dir}
                    disabled={disabled}
                    onChange={() => set('menu_direction', dir)}
                  >
                    {t(`appearance.directions.${dir}`)}
                  </Choice>
                ))}
              </div>
            </fieldset>
          )}
          {STYLE_SWITCHES[values.style] && (
            <fieldset className='grid gap-3 rounded-lg bg-muted/50 p-3'>
              <legend className='mb-1 text-sm font-medium'>
                {t('appearance.style_options')}
              </legend>
              {STYLE_SWITCHES[values.style]?.map((flag) => (
                <div
                  key={flag}
                  className='flex min-h-11 items-center justify-between gap-4 sm:min-h-0'
                >
                  <Label htmlFor={flag} className='grid gap-0.5'>
                    <span>{t(`appearance.style_flags.${flag}`)}</span>
                    <span className='font-normal text-muted-foreground'>
                      {t(`appearance.style_flags.${flag}_hint`)}
                    </span>
                  </Label>
                  <Switch
                    id={flag}
                    checked={values[flag]}
                    disabled={disabled}
                    onCheckedChange={(on) => set(flag, on)}
                  />
                </div>
              ))}
            </fieldset>
          )}
        </Section>

        <Section step='layout'>
          <LayoutPicker
            value={layout}
            own={own}
            allowed={initial.layouts}
            disabled={disabled}
            onChange={pickLayout}
          />
          {layout === 'text' && own !== 'text' && (
            <p className='text-sm text-muted-foreground'>
              {t('appearance.layout.text_note')}
            </p>
          )}
        </Section>

        <Section step='colours'>
          <div className='grid gap-2'>
            <p className='text-sm font-medium'>{t('appearance.themes')}</p>
            <div className='grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5'>
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
                    'flex min-h-11 items-center gap-2 rounded-lg border p-2 text-start text-sm hover:bg-accent disabled:opacity-60',
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
            </div>
          </div>

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
              <p className='text-sm text-destructive'>{errors.primary_color}</p>
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
        </Section>

        <Section step='images'>
          <div className='grid gap-4 sm:grid-cols-2'>
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
          </div>
          <p className='text-sm text-muted-foreground'>
            {t('appearance.images_hint')}
          </p>
          {cover && (
            <fieldset className='grid gap-2'>
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
          <div className='grid gap-3'>
            {guestSwitch('show_logo')}
            {guestSwitch('show_banner')}
          </div>
        </Section>

        <Section step='guests' last>
          {(Object.keys(GUEST_GROUPS) as (keyof typeof GUEST_GROUPS)[]).map(
            (group) => {
              const flags = GUEST_GROUPS[group].filter(
                (f: GuestFlag) =>
                  featureOn[f] !== false ||
                  // "My order" stays visible, switched off with the reason (QUESTIONS #236).
                  (f === 'show_order_list' && !!initial.my_order)
              )
              return flags.length === 0 ? null : (
                <div key={group} className='grid gap-3'>
                  <p className='text-xs font-semibold tracking-wide text-muted-foreground uppercase'>
                    {t(`appearance.guest_groups.${group}`)}
                  </p>
                  {flags.map((flag: GuestFlag) => guestSwitch(flag))}
                </div>
              )
            }
          )}
        </Section>

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
        <div
          role='group'
          aria-label={t('appearance.scheme.label')}
          className='mx-auto mb-3 flex w-fit gap-1 rounded-full border bg-muted p-1'
        >
          {(['light', 'dark'] as const).map((mode) => (
            <button
              key={mode}
              type='button'
              aria-pressed={scheme === mode}
              onClick={() => setScheme(mode)}
              className={cn(
                'flex min-h-9 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors',
                scheme === mode
                  ? 'bg-background shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              {mode === 'light' ? (
                <Sun className='size-4' aria-hidden />
              ) : (
                <Moon className='size-4' aria-hidden />
              )}
              {t(`appearance.scheme.${mode}`)}
            </button>
          ))}
        </div>
        <div
          className={cn(
            'relative mx-auto h-[640px] w-[340px] overflow-hidden rounded-[2.25rem] border-[10px] shadow-xl transition-colors',
            scheme === 'dark'
              ? 'border-zinc-700 bg-zinc-900'
              : 'border-zinc-800 bg-background'
          )}
        >
          {loading && (
            <Skeleton className='absolute inset-0 rounded-none' aria-hidden />
          )}
          <iframe
            title={t('appearance.preview')}
            src={src}
            onLoad={() => setLoading(false)}
            className='relative size-full'
          />
        </div>
        <p className='mt-2 text-center text-xs text-muted-foreground'>
          {t('appearance.preview_hint')}
        </p>
        <DesktopPreview src={src} />
      </div>
    </div>
  )
}

/** The five steps as chips that scroll to their section (easy to find your way). */
function StepBar() {
  const { t } = useTranslation()
  return (
    <nav
      aria-label={t('appearance.steps.label')}
      className='-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 sm:flex-wrap sm:overflow-visible'
    >
      {STEPS.map((step, i) => (
        <a
          key={step}
          href={`#ap-${step}`}
          onClick={(e) => {
            e.preventDefault()
            document
              .getElementById(`ap-${step}`)
              ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
          }}
          className='flex min-h-9 flex-none items-center gap-2 rounded-full border bg-background px-3 text-sm whitespace-nowrap hover:bg-accent'
        >
          <span className='flex size-5 items-center justify-center rounded-full bg-primary text-[0.7rem] font-bold text-primary-foreground'>
            {i + 1}
          </span>
          {t(`appearance.steps.${step}`)}
        </a>
      ))}
    </nav>
  )
}

/** One step: its number, icon, title and a one-line hint, then its settings. */
function Section({
  step,
  last = false,
  children,
}: {
  step: Step
  last?: boolean
  children: React.ReactNode
}) {
  const { t } = useTranslation()
  const Icon = STEP_ICON[step]
  const next = STEPS[STEPS.indexOf(step) + 1]
  return (
    <Card id={`ap-${step}`} className='scroll-mt-20'>
      <CardHeader>
        <CardTitle className='flex items-center gap-3 text-lg'>
          <span className='flex size-9 flex-none items-center justify-center rounded-xl bg-primary/10 text-primary'>
            <Icon className='size-5' aria-hidden />
          </span>
          <span>
            <span className='me-1.5 text-muted-foreground'>
              {STEPS.indexOf(step) + 1}.
            </span>
            {t(`appearance.steps.${step}`)}
          </span>
        </CardTitle>
        <CardDescription>{t(`appearance.step_hints.${step}`)}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-5'>
        {children}
        {!last && next && (
          <button
            type='button'
            onClick={() =>
              document
                .getElementById(`ap-${next}`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
            }
            className='flex w-fit items-center gap-1.5 text-sm font-medium text-primary hover:underline'
          >
            {t('appearance.next', { step: t(`appearance.steps.${next}`) })}
            <ArrowDown className='size-4' aria-hidden />
          </button>
        )}
      </CardContent>
    </Card>
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
