import { useState } from 'react'
import { ExternalLink, Loader2, Plus, Trash2 } from 'lucide-react'
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
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { send, usePlatformMutation } from './api'

const LANDING_SECTIONS = [
  'how',
  'features',
  'styles',
  'speed',
  'pricing',
  'testimonials',
  'faq',
  'cta',
] as const
const SOCIALS = [
  'telegram',
  'facebook',
  'instagram',
  'tiktok',
  'x',
  'youtube',
  'linkedin',
] as const

type Testimonial = { quote: string; name: string; role: string }

/** The landing-page part of GET /platform/settings (owner, 2026-11-13). */
export type LandingSettingsData = {
  brand_color: string
  landing_headline: string
  landing_subline: string
  landing_sections: Partial<Record<(typeof LANDING_SECTIONS)[number], boolean>>
  landing_testimonials: Testimonial[]
  socials: Partial<Record<(typeof SOCIALS)[number], string>>
  upgrade_whatsapp: string | null
}

/** Settings → Landing page: texts, colour, sections, real testimonials, links. */
export function LandingSettings({ initial }: { initial: LandingSettingsData }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings', body)
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [v, setV] = useState({
    landing_headline: initial.landing_headline ?? '',
    landing_subline: initial.landing_subline ?? '',
    brand_color: initial.brand_color || '#c2410c',
    upgrade_whatsapp: initial.upgrade_whatsapp ?? '',
    landing_sections: Object.fromEntries(
      LANDING_SECTIONS.map((k) => [k, initial.landing_sections?.[k] ?? true])
    ) as Record<(typeof LANDING_SECTIONS)[number], boolean>,
    socials: Object.fromEntries(
      SOCIALS.map((k) => [k, initial.socials?.[k] ?? ''])
    ) as Record<(typeof SOCIALS)[number], string>,
  })
  const [quotes, setQuotes] = useState<Testimonial[]>(
    (initial.landing_testimonials ?? []).map((q) => ({
      quote: q.quote ?? '',
      name: q.name ?? '',
      role: q.role ?? '',
    }))
  )
  const error = (k: string) =>
    errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        ...v,
        upgrade_whatsapp: v.upgrade_whatsapp.trim() || null,
        socials: Object.fromEntries(
          Object.entries(v.socials).map(([k, url]) => [k, url.trim()])
        ),
        landing_testimonials: quotes.filter(
          (q) => q.quote.trim() || q.name.trim()
        ),
      })
      toast.success(t('platform.saved'))
    } catch (err) {
      const found = validationErrors(err) ?? {}
      const merged: Record<string, string> = {}
      for (const [k, m] of Object.entries(found)) {
        const key = k.startsWith('landing_testimonials')
          ? 'landing_testimonials'
          : k
        merged[key] ??= m
      }
      setErrors(merged)
    }
  }

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.landing.title')}</CardTitle>
        <CardDescription>
          {t('platform.landing.hint')}{' '}
          <a
            href='/'
            target='_blank'
            rel='noreferrer'
            className='inline-flex items-center gap-1 underline underline-offset-4'
          >
            {t('platform.landing.open')} <ExternalLink className='size-3' />
          </a>
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form className='grid gap-5' onSubmit={submit}>
          <div className='grid gap-1'>
            <Label htmlFor='lp-headline'>
              {t('platform.landing.headline')}
            </Label>
            <Input
              id='lp-headline'
              maxLength={120}
              placeholder={t('platform.landing.headline_default')}
              value={v.landing_headline}
              onChange={(e) => setV({ ...v, landing_headline: e.target.value })}
            />
            {error('landing_headline')}
          </div>
          <div className='grid gap-1'>
            <Label htmlFor='lp-subline'>{t('platform.landing.subline')}</Label>
            <Textarea
              id='lp-subline'
              rows={3}
              maxLength={300}
              placeholder={t('platform.landing.subline_default')}
              value={v.landing_subline}
              onChange={(e) => setV({ ...v, landing_subline: e.target.value })}
            />
            <p className='text-sm text-muted-foreground'>
              {t('platform.landing.text_hint')}
            </p>
            {error('landing_subline')}
          </div>
          <div className='grid gap-3 sm:grid-cols-2'>
            <div className='grid gap-1'>
              <Label htmlFor='lp-color'>{t('platform.landing.color')}</Label>
              <div className='flex items-center gap-2'>
                <input
                  type='color'
                  aria-label={t('platform.landing.color')}
                  className='h-11 w-14 cursor-pointer rounded-md border bg-transparent p-1 sm:h-9'
                  value={v.brand_color}
                  onChange={(e) => setV({ ...v, brand_color: e.target.value })}
                />
                <Input
                  id='lp-color'
                  dir='ltr'
                  className='font-mono'
                  value={v.brand_color}
                  onChange={(e) => setV({ ...v, brand_color: e.target.value })}
                />
              </div>
              {error('brand_color')}
            </div>
            <div className='grid gap-1'>
              <Label htmlFor='lp-whatsapp'>
                {t('platform.landing.whatsapp')}
              </Label>
              <Input
                id='lp-whatsapp'
                inputMode='tel'
                placeholder='+251 911 000 000'
                value={v.upgrade_whatsapp}
                onChange={(e) =>
                  setV({ ...v, upgrade_whatsapp: e.target.value })
                }
              />
              {error('upgrade_whatsapp')}
            </div>
          </div>
          <p className='-mt-3 text-sm text-muted-foreground'>
            {t('platform.landing.color_hint')}{' '}
            {t('platform.landing.whatsapp_hint')}
          </p>

          <fieldset className='grid gap-2'>
            <legend className='mb-1 text-sm font-medium'>
              {t('platform.landing.sections')}
            </legend>
            <div className='grid gap-1 sm:grid-cols-2'>
              {LANDING_SECTIONS.map((k) => (
                <label
                  key={k}
                  className='flex min-h-11 items-center gap-3 text-sm sm:min-h-9'
                >
                  <Checkbox
                    checked={v.landing_sections[k]}
                    onCheckedChange={(on) =>
                      setV({
                        ...v,
                        landing_sections: {
                          ...v.landing_sections,
                          [k]: on === true,
                        },
                      })
                    }
                  />
                  {t(`platform.landing.section.${k}`)}
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className='grid gap-3'>
            <legend className='text-sm font-medium'>
              {t('platform.landing.testimonials')}
            </legend>
            <p className='text-sm text-muted-foreground'>
              {t('platform.landing.testimonials_hint')}
            </p>
            {quotes.map((q, i) => (
              <div key={i} className='grid gap-2 rounded-md border p-3'>
                <Label htmlFor={`lp-q-${i}`}>
                  {t('platform.landing.quote')}
                </Label>
                <Textarea
                  id={`lp-q-${i}`}
                  rows={2}
                  maxLength={400}
                  value={q.quote}
                  onChange={(e) =>
                    setQuotes(
                      quotes.map((x, j) =>
                        j === i ? { ...x, quote: e.target.value } : x
                      )
                    )
                  }
                />
                <div className='grid gap-2 sm:grid-cols-2'>
                  <div className='grid gap-1'>
                    <Label htmlFor={`lp-n-${i}`}>
                      {t('platform.landing.quote_name')}
                    </Label>
                    <Input
                      id={`lp-n-${i}`}
                      maxLength={80}
                      value={q.name}
                      onChange={(e) =>
                        setQuotes(
                          quotes.map((x, j) =>
                            j === i ? { ...x, name: e.target.value } : x
                          )
                        )
                      }
                    />
                  </div>
                  <div className='grid gap-1'>
                    <Label htmlFor={`lp-r-${i}`}>
                      {t('platform.landing.quote_role')}
                    </Label>
                    <Input
                      id={`lp-r-${i}`}
                      maxLength={80}
                      value={q.role}
                      onChange={(e) =>
                        setQuotes(
                          quotes.map((x, j) =>
                            j === i ? { ...x, role: e.target.value } : x
                          )
                        )
                      }
                    />
                  </div>
                </div>
                <Button
                  type='button'
                  variant='ghost'
                  className='min-h-11 justify-self-start sm:min-h-9'
                  onClick={() => setQuotes(quotes.filter((_, j) => j !== i))}
                >
                  <Trash2 /> {t('platform.landing.remove_quote')}
                </Button>
              </div>
            ))}
            {error('landing_testimonials')}
            {quotes.length < 12 && (
              <Button
                type='button'
                variant='outline'
                className='min-h-11 justify-self-start sm:min-h-9'
                onClick={() =>
                  setQuotes([...quotes, { quote: '', name: '', role: '' }])
                }
              >
                <Plus /> {t('platform.landing.add_quote')}
              </Button>
            )}
          </fieldset>

          <fieldset className='grid gap-3'>
            <legend className='text-sm font-medium'>
              {t('platform.landing.socials')}
            </legend>
            <div className='grid gap-3 sm:grid-cols-2'>
              {SOCIALS.map((k) => (
                <div key={k} className='grid gap-1'>
                  <Label htmlFor={`lp-s-${k}`}>
                    {t(`platform.landing.social.${k}`)}
                  </Label>
                  <Input
                    id={`lp-s-${k}`}
                    type='url'
                    dir='ltr'
                    placeholder='https://'
                    value={v.socials[k]}
                    onChange={(e) =>
                      setV({
                        ...v,
                        socials: { ...v.socials, [k]: e.target.value },
                      })
                    }
                  />
                  {error(`socials.${k}`)}
                </div>
              ))}
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
