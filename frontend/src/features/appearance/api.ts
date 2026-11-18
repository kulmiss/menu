import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Media } from '@/features/menu/types'

/** Guest-menu styles A–Q, in picker order (backend TenantAppearance::STYLES). */
export const MENU_STYLES = [
  'editorial',
  'grid',
  'bistro',
  'minimal',
  'mesob',
  'lounge',
  'board',
  'arabic',
  'islamic',
  'somali',
  'mono',
  'colour',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
] as const
/** M–Q: styles with their own artwork (header picture, textures, category icons). */
export const ART_STYLES: readonly MenuStyle[] = [
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
]
export type MenuStyle = (typeof MENU_STYLES)[number]
export type Font = 'system' | 'serif' | 'rounded'
export type BannerText = 'over' | 'under'
/** Menu direction, style H only: auto = right-to-left when most of the menu is in Arabic script. */
export const MENU_DIRECTIONS = ['auto', 'ltr', 'rtl'] as const
export type MenuDirection = (typeof MENU_DIRECTIONS)[number]

export type AppearanceValues = {
  primary_color: string
  font: Font
  style: MenuStyle
  /** Logo and name on or below the banner; null = the style's default. */
  banner_text: BannerText | null
  show_prices: boolean
  show_photos: boolean
  show_descriptions: boolean
  show_calories: boolean
  /** "Share menu" button on the guest page. */
  show_share: boolean
  /** "Download menu (PDF)" link on the guest page (plan feature pdf_menu). */
  show_pdf_link: boolean
  /** "My order" list for guests (kept on their phone; plan feature order_list). */
  show_order_list: boolean
  show_other_branches: boolean
  show_logo: boolean
  show_banner: boolean
  /** Style H · Arabic only. */
  menu_direction: MenuDirection
  /** Style I · Islamic only: "Halal" badge and Ramadan mode. */
  show_halal: boolean
  ramadan_mode: boolean
  /** Style K · Black & White only: black-and-white photos, brand colour as a small accent. */
  photos_greyscale: boolean
  mono_accent: boolean
  /** Styles M–Q only: their artwork on or off (the banner and category photos replace it). */
  show_decoration: boolean
  theme_id: number | null
  logo_media_id: number | null
  cover_media_id: number | null
}

export type ThemePreset = {
  id: number
  name: string
  primary: string
  font: Font
  style: MenuStyle
}

export type Appearance = Omit<
  AppearanceValues,
  'logo_media_id' | 'cover_media_id'
> & {
  logo: Media | null
  cover: Media | null
  warnings: { key: string; message: string }[]
  themes: ThemePreset[]
  /** Styles this restaurant's plan allows, in picker order. */
  styles: MenuStyle[]
  /** Banner shape [width, height] and default name position per style. */
  banner: Record<MenuStyle, { ratio: [number, number]; text: BannerText }>
}

export const appearanceKey = (t: number) => ['appearance', t] as const

export const appearanceQuery = (t: number) =>
  queryOptions({
    queryKey: appearanceKey(t),
    queryFn: async () =>
      (await api.get<{ data: Appearance }>(tenantUrl(t, 'appearance'))).data
        .data,
  })

export function useSaveAppearance(t: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (values: AppearanceValues) =>
      (await api.put<{ data: Appearance }>(tenantUrl(t, 'appearance'), values))
        .data.data,
    meta: { inlineErrors: true },
    onSuccess: (data) => qc.setQueryData(appearanceKey(t), data),
  })
}

/** URL of the guest page rendered with unsaved values (for the preview frame). */
export function previewUrl(t: number, v: AppearanceValues) {
  const q = new URLSearchParams()
  for (const [key, value] of Object.entries(v)) {
    if (value === null || key === 'theme_id') continue
    q.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  return `${api.defaults.baseURL}${tenantUrl(t, 'appearance/preview')}?${q}`
}
