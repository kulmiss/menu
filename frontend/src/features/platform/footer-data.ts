import { q } from './api'

// Data of Super Admin → Footer (footer-editor.tsx), as GET /super/footer sends it.

export type FooterLink = {
  id: string
  label: string
  /** A page of this site (a key of `pages`), or null for a web address. */
  page: string | null
  url: string
  on: boolean
}

export type FooterColumn = {
  id: string
  title: string
  on: boolean
  links: FooterLink[]
}

export type FooterContent = {
  illustration: boolean
  columns: FooterColumn[]
  contact: {
    on: boolean
    title: string
    whatsapp: string
    phone: string
    email: string
    address: string
    hours: string
  }
  socials: Record<string, string>
  app: { on: boolean; play_url: string }
  trust: { on: boolean; text: string }
  bottom: { company: string; made_in: string; back_to_top: boolean }
}

export type FooterVersionRow = {
  id: number
  at: string
  by: string | null
  restored_from: number | null
  live: boolean
}

export type FooterData = {
  draft: FooterContent
  live: FooterContent
  changed: boolean
  pages: { key: string; label: string; path: string }[]
  /** Pages that exist right now (e.g. the demo menu only when there is one). */
  available: string[]
  socials: string[]
  limits: { columns: number; links: number }
  versions: FooterVersionRow[]
}

export const footerQ = () => q<FooterData>('footer')

/** A new id for a column or link (letters, digits and "-", as the server asks). */
export const newId = (prefix: 'c' | 'l') =>
  `${prefix}-${Math.random().toString(36).slice(2, 10)}`

/** Moves one item of a list up (-1) or down (+1). */
export function move<T>(list: T[], index: number, by: -1 | 1): T[] {
  const to = index + by
  if (to < 0 || to >= list.length) return list
  const next = [...list]
  ;[next[index], next[to]] = [next[to], next[index]]
  return next
}

/** The preview page of the footer: the draft or the live one, light or dark. */
export const previewUrl = (
  scheme: 'light' | 'dark',
  source: 'draft' | 'live',
  version: number
) =>
  `/api/v1/super/footer/preview?scheme=${scheme}&source=${source}&v=${version}`
