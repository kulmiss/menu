import { useState } from 'react'
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
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { send, usePlatformMutation } from './api'

const SOCIALS = [
  'telegram',
  'facebook',
  'instagram',
  'tiktok',
  'x',
  'youtube',
  'linkedin',
] as const

type Testimonial = { quote: string; name: string; role: string; real: boolean }

/** The landing-page part of GET /platform/settings (owner, 2026-11-13). */
export type LandingSettingsData = {
  brand_color: string
  landing_testimonials: Partial<Testimonial>[]
  socials: Partial<Record<(typeof SOCIALS)[number], string>>
  upgrade_whatsapp: string | null
}

/**
 * Landing page → colour, WhatsApp number, real testimonials and footer links.
 * Texts, pictures and sections have their own editor (landing-editor.tsx).
 */
export function LandingSettings({ initial }: { initial: LandingSettingsData }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'settings', body)
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [v, setV] = useState({
    brand_color: initial.brand_color || '#c2410c',
    upgrade_whatsapp: initial.upgrade_whatsapp ?? '',
    socials: Object.fromEntries(
      SOCIALS.map((k) => [k, initial.socials?.[k] ?? ''])
    ) as Record<(typeof SOCIALS)[number], string>,
  })
  const [quotes, setQuotes] = useState<Testimonial[]>(
    (initial.landing_testimonials ?? []).map((q) => ({
      quote: q.quote ?? '',
      name: q.name ?? '',
      role: q.role ?? '',
      real: q.real === true,
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
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.landing.title')}</CardTitle>
        <CardDescription>{t('platform.landing.hint')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className='grid gap-5' onSubmit={submit}>
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
                <label className='flex min-h-11 items-center gap-3 text-sm sm:min-h-9'>
                  <Checkbox
                    checked={q.real}
                    onCheckedChange={(on) =>
                      setQuotes(
                        quotes.map((x, j) =>
                          j === i ? { ...x, real: on === true } : x
                        )
                      )
                    }
                  />
                  {t('platform.landing.quote_real')}
                </label>
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
                  setQuotes([
                    ...quotes,
                    { quote: '', name: '', role: '', real: false },
                  ])
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
