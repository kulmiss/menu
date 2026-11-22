import type { TFunction } from 'i18next'
import { q } from './api'

// Data and helpers of Super Admin → Landing 2 (landing2-editor.tsx), owner 2026-11-22.

export const LOCALES = ['en', 'am'] as const
export type Locale = (typeof LOCALES)[number]

export const SECTIONS = [
  'switcher',
  'hero_photo',
  'pattern',
  'problem',
  'how',
  'price_demo',
  'numbers',
  'styles',
  'video',
  'compare',
  'qr',
  'poster',
  'features',
  'restaurants',
  'pricing',
  'billing',
  'payments',
  'faq',
  'final',
  'bands',
  'motion',
  'languages',
] as const
export type Section = (typeof SECTIONS)[number]

export const OWN_NUMBERS = ['restaurants', 'dishes', 'views'] as const

export type Payment = {
  label_en: string
  label_am: string
  icon: string
  on: boolean
}
export type OutsideNumber = {
  value: number
  label_en: string
  label_am: string
  source: string
  date: string
  on: boolean
  min: number
}
export type Numbers = {
  own: Record<(typeof OWN_NUMBERS)[number], { on: boolean; min: number }>
  outside: OutsideNumber[]
}
export type Restaurant = {
  id: number
  name: string
  city: string | null
  quote_en: string | null
  quote_am: string | null
  logo: string | null
  permission: boolean
  is_active: boolean
  sort: number
}
export type Video = {
  mp4: string
  poster: string
  captions: { en: string | null; am: string | null }
  custom: boolean
} | null

export type Landing2Data = {
  urls: Record<Locale, string>
  is_home: boolean
  sections: Record<Section, boolean>
  texts: Record<Locale, Record<string, string>>
  defaults: Record<Locale, Record<string, string>>
  shared: string[]
  max: Record<string, number>
  payments: Payment[]
  payment_icons: string[]
  numbers: Numbers
  counted: {
    at: string
    restaurants: number
    dishes: number
    views: number
  } | null
  stale: number
  stale_days: number
  today: string
  stale_before: string
  restaurants: Restaurant[]
  min_restaurants: number
  video: Video
  footer: {
    texts: string[]
    am: Record<string, string>
    defaults: Record<string, string>
  }
}

export const landing2Q = () => q<Landing2Data>('landing2')

/** Text groups, in page order: the first part of each text key. */
export const GROUPS = [
  'hero',
  'switcher',
  'numbers',
  'problem',
  'how',
  'price_demo',
  'styles',
  'style_names',
  'video',
  'compare',
  'qr',
  'poster',
  'features',
  'restaurants',
  'pricing',
  'faq',
  'final',
  'buttons',
  'search',
] as const
export type Group = (typeof GROUPS)[number]

const OTHER: Record<string, Group> = {
  meta_title: 'search',
  meta_description: 'search',
  meta_description_paid: 'search',
  skip: 'buttons',
  lang: 'buttons',
  nav: 'buttons',
  create_account: 'buttons',
  contact_whatsapp: 'buttons',
  contact_email: 'buttons',
  contact_text: 'buttons',
  sticky: 'buttons',
}

export function groupOf(key: string): Group {
  const first = key.split('.')[0]
  return (OTHER[first] ??
    ((GROUPS as readonly string[]).includes(first)
      ? first
      : 'buttons')) as Group
}

/**
 * A readable label for a text key: "how.steps.0.title" → "Step 1 · Title",
 * "faq.items.app.q" → "Question 2", "hero.points.1" → "Point 2".
 */
export function fieldLabel(key: string, all: string[], t: TFunction): string {
  const parts = key.split('.')
  const field = (name: string) =>
    t(`platform.landing2_editor.field.${name}`, {
      defaultValue: name
        .replace(/_/g, ' ')
        .replace(/^./, (c) => c.toUpperCase()),
    })
  const listAt = parts.findIndex((p) =>
    ['items', 'steps', 'rows', 'points', 'colours', 'plan_names'].includes(p)
  )
  if (listAt < 0) return parts.slice(1).map(field).join(' · ') || field(key)
  const prefix = parts.slice(0, listAt + 1).join('.') + '.'
  const ids = [
    ...new Set(
      all
        .filter((k) => k.startsWith(prefix))
        .map((k) => k.slice(prefix.length).split('.')[0])
    ),
  ]
  const n = ids.indexOf(parts[listAt + 1]) + 1
  const rest = parts.slice(listAt + 2)
  const list = parts[listAt]
  const head = t(`platform.landing2_editor.list.${list}`, { n })
  if (list === 'rows')
    return `${head} · ${field(rest[0] === '0' ? 'before' : 'after')}`
  if (list === 'colours' || list === 'plan_names')
    return `${head} · ${parts[listAt + 1]}`
  return rest.length ? `${head} · ${rest.map(field).join(' · ')}` : head
}
