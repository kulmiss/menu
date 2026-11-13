import { useMemo, useState } from 'react'
import { queryOptions, useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { ArrowLeft, Download, Eye, ImageDown, Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import {
  type QrCodeRow,
  downloadUrl,
  qrCodesQuery,
} from '@/features/branches/api'
import { useMenuContext } from '@/features/menu/use-menu-context'

const MATERIALS = [
  'tent',
  'poster_a5',
  'poster_a4',
  'window',
  'sticker_round',
  'sticker_square',
] as const
const DESIGNS = ['classic', 'bold', 'minimal'] as const
type Material = (typeof MATERIALS)[number]
type Design = (typeof DESIGNS)[number]
type Theme = 'light' | 'dark'

type PrintInfo = {
  name: string
  colour: string
  logo: string | null
  headline: string
  scan: string
  branding: string | null
}

const infoQuery = (t: number) =>
  queryOptions({
    queryKey: ['tenant', t, 'print-materials'],
    queryFn: async () =>
      (await api.get<{ data: PrintInfo }>(tenantUrl(t, 'print-materials'))).data
        .data,
  })

/** Page shape for the small preview (width / height). */
const SHAPE: Record<Material, number> = {
  tent: 105 / 148.5,
  poster_a5: 148 / 210,
  poster_a4: 210 / 297,
  window: 297 / 210,
  sticker_round: 1,
  sticker_square: 1,
}
const BLEED: Material[] = ['poster_a5', 'poster_a4', 'window']

type RGB = [number, number, number]
type Palette = {
  bg: RGB
  text: RGB
  muted: RGB
  accent: RGB
  band: RGB | null
  border: RGB | null
}

function rgb(hex: string): RGB {
  const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex)
  return m
    ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)]
    : [20, 18, 16]
}

function luminance([r, g, b]: RGB) {
  const f = (c: number) => {
    c /= 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)
}

/** Same colours as the PDF (App\Support\Print\PrintMaterials::palette). */
function palette(design: Design, theme: Theme, brand: RGB): Palette {
  const ink: RGB = [20, 18, 16]
  const paper: RGB = [255, 255, 255]
  const night: RGB = [22, 20, 18]
  const onBrand = luminance(brand) > 0.45 ? ink : paper
  const dark = theme === 'dark'
  if (design === 'bold')
    return dark
      ? {
          bg: night,
          text: [245, 242, 238],
          muted: [190, 184, 178],
          accent: brand,
          band: brand,
          border: null,
        }
      : {
          bg: brand,
          text: onBrand,
          muted: onBrand,
          accent: onBrand,
          band: null,
          border: null,
        }
  if (design === 'minimal')
    return dark
      ? {
          bg: [12, 12, 12],
          text: [245, 245, 245],
          muted: [170, 170, 170],
          accent: [245, 245, 245],
          band: null,
          border: null,
        }
      : {
          bg: paper,
          text: ink,
          muted: [110, 106, 102],
          accent: ink,
          band: null,
          border: [200, 196, 192],
        }
  return dark
    ? {
        bg: night,
        text: [245, 242, 238],
        muted: [180, 174, 168],
        accent: luminance(brand) < 0.12 ? [245, 242, 238] : brand,
        band: brand,
        border: brand,
      }
    : {
        bg: paper,
        text: ink,
        muted: [105, 100, 95],
        accent: luminance(brand) > 0.6 ? ink : brand,
        band: brand,
        border: brand,
      }
}

const css = (c: RGB) => `rgb(${c.join(' ')})`

/** Print materials: posters, tents, stickers and a window sign (QUESTIONS #179). */
export function PrintMaterialsPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const codes = useQuery(qrCodesQuery(ctx.tenantId))
  const info = useQuery(infoQuery(ctx.tenantId))
  const [material, setMaterial] = useState<Material>('tent')
  const [design, setDesign] = useState<Design>('classic')
  const [theme, setTheme] = useState<Theme>('light')
  const [headline, setHeadline] = useState('')
  const [bleed, setBleed] = useState(false)
  const [picked, setPicked] = useState<number[] | null>(null)
  const [busy, setBusy] = useState<string | null>(null)

  const rows = useMemo(() => codes.data ?? [], [codes.data])
  // Until something is chosen: the restaurant's own code.
  const chosen =
    picked ??
    (rows.length
      ? [(rows.find((r) => r.kind === 'restaurant') ?? rows[0]).id]
      : [])
  const first = rows.find((r) => r.id === chosen[0])
  const groups = useMemo(() => {
    const out = new Map<string, QrCodeRow[]>()
    for (const r of rows) {
      const key = r.kind === 'table' ? r.branch : ''
      out.set(key, [...(out.get(key) ?? []), r])
    }
    return [...out.entries()]
  }, [rows])

  const toggle = (ids: number[], on: boolean) =>
    setPicked(
      on
        ? [...new Set([...chosen, ...ids])]
        : chosen.filter((id) => !ids.includes(id))
    )

  const query = (inline: boolean) => {
    const q = new URLSearchParams({
      material,
      design,
      theme,
      codes: chosen.join(','),
    })
    if (headline.trim()) q.set('headline', headline.trim())
    if (bleed && BLEED.includes(material)) q.set('bleed', '1')
    if (inline) q.set('inline', '1')
    return downloadUrl(ctx.tenantId, `print-materials.pdf?${q}`)
  }

  const colours = palette(design, theme, rgb(info.data?.colour ?? '#141210'))
  const shownHeadline = headline.trim() || info.data?.headline || ''

  async function social(kind: 'post' | 'story') {
    if (!info.data || !first) return
    setBusy(kind)
    try {
      const blob = await socialImage({
        width: 1080,
        height: kind === 'post' ? 1080 : 1920,
        colours,
        design,
        name: info.data.name,
        headline: shownHeadline,
        scan: info.data.scan,
        label: first.kind === 'table' ? first.description : null,
        branding: info.data.branding,
        logo: info.data.logo,
        qr: downloadUrl(ctx.tenantId, `qr-codes/${first.id}.svg`),
      })
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `${slug(info.data.name)}-${kind}.png`
      a.click()
      setTimeout(() => URL.revokeObjectURL(a.href), 10_000)
    } finally {
      setBusy(null)
    }
  }

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 grid gap-1'>
          <Button asChild variant='ghost' size='sm' className='w-fit'>
            <Link to='/admin/qr-codes'>
              <ArrowLeft /> {t('qr.title')}
            </Link>
          </Button>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('print.title')}
          </h1>
          <p className='text-muted-foreground'>{t('print.description')}</p>
        </div>

        <div className='grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]'>
          <div className='grid content-start gap-4'>
            <Card>
              <CardHeader>
                <CardTitle>{t('print.what')}</CardTitle>
              </CardHeader>
              <CardContent className='grid gap-2 sm:grid-cols-2 xl:grid-cols-3'>
                {MATERIALS.map((m) => (
                  <button
                    key={m}
                    type='button'
                    aria-pressed={material === m}
                    onClick={() => setMaterial(m)}
                    className={cn(
                      'grid min-h-11 gap-0.5 rounded-md border p-3 text-start text-sm transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                      material === m && 'border-primary bg-accent'
                    )}
                  >
                    <span className='font-medium'>
                      {t(`print.materials.${m}`)}
                    </span>
                    <span className='text-xs text-muted-foreground'>
                      {t(`print.sizes.${m}`)}
                    </span>
                  </button>
                ))}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{t('print.look')}</CardTitle>
              </CardHeader>
              <CardContent className='grid gap-4'>
                <fieldset className='grid gap-2'>
                  <legend className='mb-2 text-sm font-medium'>
                    {t('print.design')}
                  </legend>
                  <div className='flex flex-wrap gap-2'>
                    {DESIGNS.map((d) => (
                      <Button
                        key={d}
                        type='button'
                        variant={design === d ? 'default' : 'outline'}
                        aria-pressed={design === d}
                        className='min-h-11'
                        onClick={() => setDesign(d)}
                      >
                        {t(`print.designs.${d}`)}
                      </Button>
                    ))}
                  </div>
                </fieldset>
                <fieldset className='grid gap-2'>
                  <legend className='mb-2 text-sm font-medium'>
                    {t('print.colours')}
                  </legend>
                  <div className='flex flex-wrap gap-2'>
                    {(['light', 'dark'] as const).map((th) => (
                      <Button
                        key={th}
                        type='button'
                        variant={theme === th ? 'default' : 'outline'}
                        aria-pressed={theme === th}
                        className='min-h-11'
                        onClick={() => setTheme(th)}
                      >
                        {t(`print.themes.${th}`)}
                      </Button>
                    ))}
                  </div>
                </fieldset>
                <div className='grid gap-1'>
                  <Label htmlFor='print-headline'>{t('print.headline')}</Label>
                  <Input
                    id='print-headline'
                    value={headline}
                    maxLength={40}
                    placeholder={info.data?.headline}
                    onChange={(e) => setHeadline(e.target.value)}
                  />
                  <p className='text-xs text-muted-foreground'>
                    {t('print.headline_hint')}
                  </p>
                </div>
                {BLEED.includes(material) && (
                  <div className='flex items-start gap-3'>
                    <Checkbox
                      id='print-bleed'
                      checked={bleed}
                      onCheckedChange={(v) => setBleed(v === true)}
                      className='mt-0.5'
                    />
                    <div className='grid gap-0.5'>
                      <Label htmlFor='print-bleed'>{t('print.bleed')}</Label>
                      <p className='text-xs text-muted-foreground'>
                        {t('print.bleed_hint')}
                      </p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{t('print.codes')}</CardTitle>
                <CardDescription>
                  {t(
                    material.startsWith('sticker')
                      ? 'print.codes_hint_stickers'
                      : material === 'tent'
                        ? 'print.codes_hint_tent'
                        : 'print.codes_hint_pages'
                  )}
                </CardDescription>
              </CardHeader>
              <CardContent className='grid gap-4'>
                {codes.isPending ? (
                  <Skeleton className='h-24' />
                ) : (
                  groups.map(([branch, list]) => {
                    const ids = list.map((r) => r.id)
                    const all = ids.every((id) => chosen.includes(id))
                    return (
                      <div key={branch || 'main'} className='grid gap-1'>
                        {branch && (
                          <div className='flex flex-wrap items-center justify-between gap-2'>
                            <p className='text-sm font-medium'>
                              {t('print.tables_of', { name: branch })}
                            </p>
                            <Button
                              type='button'
                              size='sm'
                              variant='outline'
                              className='min-h-11 sm:min-h-8'
                              onClick={() => toggle(ids, !all)}
                            >
                              {all ? t('print.none') : t('print.all')}
                            </Button>
                          </div>
                        )}
                        <div className='grid gap-x-4 sm:grid-cols-2'>
                          {list.map((r) => (
                            <label
                              key={r.id}
                              className='flex min-h-11 cursor-pointer items-center gap-3 text-sm'
                            >
                              <Checkbox
                                checked={chosen.includes(r.id)}
                                onCheckedChange={(v) =>
                                  toggle([r.id], v === true)
                                }
                              />
                              {r.description}
                            </label>
                          ))}
                        </div>
                      </div>
                    )
                  })
                )}
              </CardContent>
            </Card>
          </div>

          <div className='grid content-start gap-4 lg:sticky lg:top-20'>
            <Card>
              <CardHeader>
                <CardTitle>{t('print.preview')}</CardTitle>
                <CardDescription>
                  {t('print.count', { count: chosen.length })}
                </CardDescription>
              </CardHeader>
              <CardContent className='grid gap-3'>
                <Preview
                  material={material}
                  colours={colours}
                  design={design}
                  name={info.data?.name ?? ''}
                  headline={shownHeadline}
                  label={first?.kind === 'table' ? first.description : null}
                  branding={info.data?.branding ?? null}
                  qr={
                    first
                      ? downloadUrl(ctx.tenantId, `qr-codes/${first.id}.svg`)
                      : null
                  }
                />
                {chosen.length === 0 ? (
                  <p className='text-sm text-destructive'>
                    {t('print.choose')}
                  </p>
                ) : (
                  <div className='grid gap-2'>
                    <Button asChild className='min-h-11'>
                      <a href={query(false)} download>
                        <Download /> {t('print.download')}
                      </a>
                    </Button>
                    <Button asChild variant='outline' className='min-h-11'>
                      <a href={query(true)} target='_blank' rel='noopener'>
                        <Eye /> {t('print.open')}
                      </a>
                    </Button>
                  </div>
                )}
                <p className='text-xs text-muted-foreground'>
                  {t('qr.print_tip')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{t('print.social')}</CardTitle>
                <CardDescription>{t('print.social_hint')}</CardDescription>
              </CardHeader>
              <CardContent className='grid gap-2'>
                {(['post', 'story'] as const).map((k) => (
                  <Button
                    key={k}
                    type='button'
                    variant='outline'
                    className='min-h-11'
                    disabled={!info.data || !first || busy !== null}
                    onClick={() => social(k)}
                  >
                    {busy === k ? (
                      <Loader2 className='animate-spin' />
                    ) : (
                      <ImageDown />
                    )}
                    {t(`print.${k}`)}
                  </Button>
                ))}
              </CardContent>
            </Card>
          </div>
        </div>
      </Main>
    </>
  )
}

/** A small, approximate picture of one face, so the choices are easy to compare. */
function Preview(props: {
  material: Material
  colours: Palette
  design: Design
  name: string
  headline: string
  label: string | null
  branding: string | null
  qr: string | null
}) {
  const { t } = useTranslation()
  const c = props.colours
  const round = props.material === 'sticker_round'
  const wide = props.material === 'window'
  const sticker = props.material.startsWith('sticker')
  return (
    <figure
      aria-label={t('print.preview_label')}
      className='mx-auto w-full max-w-64 overflow-hidden border shadow-sm'
      style={{
        aspectRatio: SHAPE[props.material],
        background: css(c.bg),
        color: css(c.text),
        borderRadius: round ? '50%' : sticker ? '8%' : 2,
        borderTop: c.band ? `6px solid ${css(c.band)}` : undefined,
      }}
    >
      <div
        className={cn(
          'flex h-full items-center justify-center gap-[6%] p-[8%] text-center',
          wide ? 'flex-row text-start' : 'flex-col'
        )}
      >
        <div className={cn('grid gap-0.5', wide && 'flex-1')}>
          {!sticker && (
            <span className='text-[10px] font-semibold'>{props.name}</span>
          )}
          <span
            className={cn(
              'leading-tight font-bold',
              sticker ? 'text-[9px]' : 'text-sm'
            )}
            style={{
              color: css(props.design === 'bold' ? c.text : c.accent),
            }}
          >
            {props.headline}
          </span>
        </div>
        {props.qr && (
          <img
            src={props.qr}
            alt=''
            className={cn(
              'aspect-square rounded bg-white p-[3%]',
              wide ? 'w-[40%]' : sticker ? 'w-[48%]' : 'w-[58%]'
            )}
            style={{
              outline: c.border ? `1px solid ${css(c.border)}` : undefined,
            }}
          />
        )}
        {props.label && (
          <span className='text-[10px] font-bold'>{props.label}</span>
        )}
        {props.branding && !wide && (
          <span className='text-[7px]' style={{ color: css(c.muted) }}>
            {props.branding}
          </span>
        )}
      </div>
    </figure>
  )
}

const slug = (s: string) =>
  s
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '') || 'menu'

function loadImage(src: string): Promise<HTMLImageElement | null> {
  return new Promise((resolve) => {
    const img = new Image()
    // A picture that never arrives must not block the download.
    const timer = setTimeout(() => resolve(null), 8000)
    img.crossOrigin = 'anonymous'
    img.onload = () => {
      clearTimeout(timer)
      resolve(img)
    }
    img.onerror = () => {
      clearTimeout(timer)
      resolve(null)
    }
    img.src = src
  })
}

/** Wraps text into at most `max` lines, shrinking the font until it fits. */
function fitLines(
  ctx: CanvasRenderingContext2D,
  text: string,
  width: number,
  size: number,
  min: number,
  max: number
): [string[], number] {
  const words = text.split(/\s+/).filter(Boolean)
  for (let s = size; ; s -= 2) {
    ctx.font = `700 ${s}px system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`
    const lines: string[] = ['']
    for (const w of words) {
      const next = `${lines[lines.length - 1]} ${w}`.trim()
      if (ctx.measureText(next).width <= width || !lines[lines.length - 1])
        lines[lines.length - 1] = next
      else lines.push(w)
    }
    if (lines.length <= max || s <= min) return [lines.slice(0, max), s]
  }
}

/** A picture for social media (1080 × 1080 post or 1080 × 1920 story), drawn in the browser. */
async function socialImage(o: {
  width: number
  height: number
  colours: Palette
  design: Design
  name: string
  headline: string
  scan: string
  label: string | null
  branding: string | null
  logo: string | null
  qr: string
}): Promise<Blob> {
  const canvas = document.createElement('canvas')
  canvas.width = o.width
  canvas.height = o.height
  const g = canvas.getContext('2d')
  if (!g) throw new Error('Canvas is not available.')
  const c = o.colours
  const W = o.width
  const font = (weight: number, size: number) =>
    `${weight} ${size}px system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`
  g.fillStyle = css(c.bg)
  g.fillRect(0, 0, W, o.height)
  if (c.band) {
    g.fillStyle = css(c.band)
    g.fillRect(0, 0, W, W * 0.03)
  }
  const [logo, qr] = await Promise.all([
    o.logo ? loadImage(o.logo) : Promise.resolve(null),
    loadImage(o.qr),
  ])
  const story = o.height > o.width
  const qrSize = W * (story ? 0.62 : 0.44)
  let y = story ? o.height * 0.12 : W * 0.08
  g.textAlign = 'center'
  g.textBaseline = 'top'
  if (logo) {
    const s = W * (story ? 0.16 : 0.1)
    if (c.bg.join() !== '255,255,255') {
      g.fillStyle = '#fff'
      g.beginPath()
      g.roundRect(W / 2 - s / 2 - 12, y - 12, s + 24, s + 24, 24)
      g.fill()
    }
    g.drawImage(logo, W / 2 - s / 2, y, s, s)
    y += s + W * 0.04
  }
  g.fillStyle = css(c.text)
  g.font = font(700, W * 0.04)
  g.fillText(o.name, W / 2, y, W * 0.84)
  y += W * 0.065
  const [lines, size] = fitLines(
    g,
    o.headline,
    W * 0.84,
    W * 0.085,
    W * 0.05,
    2
  )
  g.fillStyle = css(o.design === 'bold' ? c.text : c.accent)
  for (const line of lines) {
    g.fillText(line, W / 2, y, W * 0.84)
    y += size * 1.15
  }
  y += story ? W * 0.08 : W * 0.03
  const pad = W * 0.03
  g.fillStyle = '#fff'
  g.beginPath()
  g.roundRect(
    W / 2 - qrSize / 2 - pad,
    y - pad,
    qrSize + 2 * pad,
    qrSize + 2 * pad,
    pad * 1.2
  )
  g.fill()
  if (c.border) {
    g.strokeStyle = css(c.border)
    g.lineWidth = 4
    g.stroke()
  }
  if (qr) g.drawImage(qr, W / 2 - qrSize / 2, y, qrSize, qrSize)
  y += qrSize + pad + W * 0.035
  g.fillStyle = css(c.muted)
  g.font = font(400, W * 0.03)
  g.fillText(o.scan, W / 2, y, W * 0.9)
  y += W * 0.05
  if (o.label) {
    g.fillStyle = css(c.text)
    g.font = font(700, W * 0.045)
    g.fillText(o.label, W / 2, y, W * 0.84)
  }
  if (o.branding) {
    g.fillStyle = css(c.muted)
    g.font = font(400, W * 0.022)
    g.textBaseline = 'bottom'
    g.fillText(o.branding, W / 2, o.height - W * 0.04, W * 0.84)
  }
  return new Promise((resolve, reject) =>
    canvas.toBlob(
      (b) => (b ? resolve(b) : reject(new Error('No image'))),
      'image/png'
    )
  )
}
