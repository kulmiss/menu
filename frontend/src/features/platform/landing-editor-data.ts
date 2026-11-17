import type { TFunction } from 'i18next'
import { q } from './api'

// Data and helpers of Super Admin → Landing page (landing-editor.tsx).

export const SECTIONS = [
  'problem',
  'demo',
  'how',
  'features',
  'compare',
  'pricing',
  'testimonials',
  'faq',
  'cta',
] as const
export type Section = (typeof SECTIONS)[number]
export const SLOTS = ['hero', 'hero_dark', 'demo', 'demo_dark', 'og'] as const
export type Slot = (typeof SLOTS)[number]
export const BUTTONS = ['signup', 'google', 'demo'] as const

export type LandingData = {
  url: string
  texts: Record<string, string>
  defaults: Record<string, string>
  max: Record<string, number>
  sections: Record<Section, boolean>
  order: Section[]
  images: Record<Slot, { url: string; custom: boolean }>
  clicks: {
    days: ({ day: string } & Record<(typeof BUTTONS)[number], number>)[]
    totals: Record<(typeof BUTTONS)[number], number>
    all_time: Record<(typeof BUTTONS)[number], number>
  }
}

export const landingQ = () => q<LandingData>('landing')

/** Text groups that are always on the page, then the movable sections. */
export type Fixed = 'hero' | 'buttons' | 'search' | 'footer'
const PREFIXES: Record<Fixed | Section, string[]> = {
  hero: ['hero.'],
  buttons: [
    'create_account',
    'google',
    'sign_in',
    'contact_',
    'nav.',
    'chat.',
    'faq.help_',
  ],
  search: ['meta_'],
  footer: ['footer.'],
  problem: ['problem.'],
  demo: ['demo.'],
  how: ['how.'],
  features: ['features.'],
  compare: ['compare.'],
  pricing: ['pricing.'],
  testimonials: ['testimonials.'],
  faq: ['faq.'],
  cta: ['cta.'],
}

export function keysOf(group: keyof typeof PREFIXES, all: string[]): string[] {
  return all.filter((k) => {
    // "faq.help_*" (the help button) is listed with the buttons.
    if (group === 'faq' && k.startsWith('faq.help_')) return false
    return PREFIXES[group].some((p) => k === p || k.startsWith(p))
  })
}

/**
 * A readable label for a text key, e.g. "faq.items.app.q" → "Question 2",
 * "how.steps.0.title" → "Step 1 · Title", "compare.rows.3.1" → "Row 4 · QR menu".
 */
export function fieldLabel(key: string, all: string[], t: TFunction): string {
  const parts = key.split('.')
  const last = parts[parts.length - 1]
  const field = (name: string) =>
    t(`platform.landing_editor.field.${name}`, { defaultValue: name })
  const listAt = parts.findIndex((p) =>
    ['items', 'steps', 'rows', 'trust', 'style_names'].includes(p)
  )
  if (listAt < 0) return field(parts.slice(0, -1).length ? last : key)
  const list = parts[listAt]
  const id = parts[listAt + 1]
  const prefix = parts.slice(0, listAt + 1).join('.') + '.'
  const ids = [
    ...new Set(
      all
        .filter((k) => k.startsWith(prefix))
        .map((k) => k.slice(prefix.length).split('.')[0])
    ),
  ]
  const n = ids.indexOf(id) + 1
  if (list === 'rows')
    return t('platform.landing_editor.row', {
      n,
      side: field(last === '0' ? 'before' : 'after'),
    })
  if (list === 'style_names')
    return t('platform.landing_editor.style_name', { n })
  if (list === 'trust') return t('platform.landing_editor.trust', { n })
  if (parts[0] === 'faq')
    return t(
      `platform.landing_editor.${last === 'q' ? 'question' : 'answer'}`,
      {
        n,
      }
    )
  return `${t(`platform.landing_editor.${list === 'steps' ? 'step' : 'card'}`, { n })} · ${field(last)}`
}
