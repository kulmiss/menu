/** Text per language: { en: 'Macchiato', am: 'ማኪያቶ' }. */
export type Translations = Record<string, string>

export type Schedule = { weekday: number; starts_at: string; ends_at: string }

export type Menu = {
  id: number
  name: Translations
  slug: string
  schedule_mode: 'always' | 'scheduled'
  is_visible: boolean
  sort: number
  schedules?: Schedule[]
  categories_count?: number
  items_count?: number
  /** Branches the menu is limited to; empty = every branch. */
  branch_ids?: number[]
}

/** An uploaded photo with its sizes (MediaResource). */
export type Media = {
  id: number
  width: number
  height: number
  dominant_color: string | null
  urls: Partial<Record<'320' | '640' | '1280' | 'jpg' | 'og', string>>
}

export type ItemSummary = {
  id: number
  category_id: number
  name: Translations
  summary: Translations
  price_minor: number | null
  from_price_minor: number | null
  variants_count: number
  /** Sizes (id, name, main price) — used by the branch price screen. */
  variants?: { id: number; name: Translations; price_minor: number }[]
  media_id: number | null
  media?: Media | null
  is_sold_out: boolean
  is_visible: boolean
  is_new: boolean
  is_popular: boolean
  sort: number
}

export type Variant = {
  id?: number
  name: Translations
  price_minor: number
  is_default: boolean
  sort?: number
}

export type Item = ItemSummary & {
  description: Translations
  calories: number | null
  prep_minutes: number | null
  new_until: string | null
  variants: Variant[]
  addon_group_ids: number[]
  tag_ids: number[]
  allergens: string[]
  menu_id: number
}

export type Category = {
  id: number
  menu_id: number
  name: Translations
  description: Translations
  icon: string | null
  media_id: number | null
  media?: Media | null
  is_visible: boolean
  sort: number
  items: ItemSummary[]
}

export type MenuTree = { menu: Menu; categories: Category[] }

export type AddonOption = {
  id?: number
  name: Translations
  price_minor: number
  is_sold_out: boolean
  sort?: number
}

export type AddonGroup = {
  id: number
  name: Translations
  min_choices: number
  max_choices: number | null
  sort: number
  options: AddonOption[]
  items_count?: number
}

export type Tag = {
  id: number
  key: string | null
  name: Translations
  is_system: boolean
  sort: number
}

/** EU-14 allergens (config/menu.php). */
export const ALLERGENS = [
  'gluten',
  'crustaceans',
  'eggs',
  'fish',
  'peanuts',
  'soy',
  'milk',
  'nuts',
  'celery',
  'mustard',
  'sesame',
  'sulphites',
  'lupin',
  'molluscs',
] as const
