import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Media } from '@/features/menu/types'

/** Guest-menu styles A–T, in picker order (backend TenantAppearance::STYLES). */
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
  'burger',
  'injera',
  'juice',
] as const
/** M–T: styles with their own artwork (header picture, textures, category icons). */
export const ART_STYLES: readonly MenuStyle[] = [
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
  'burger',
  'injera',
  'juice',
]
export type MenuStyle = (typeof MENU_STYLES)[number]
/** Layouts (QUESTIONS #242): photo cards in two columns, a list with small photos, or text only. */
export const LAYOUTS = ['grid', 'list', 'text'] as const
export type Layout = (typeof LAYOUTS)[number]
/** The phone's colour mode in the preview: as the phone decides, or forced. */
export type PreviewScheme = 'light' | 'dark'
export type Font = 'system' | 'serif' | 'rounded'
export type BannerText = 'over' | 'under'
/** Menu direction, style H only: auto = right-to-left when most of the menu is in Arabic script. */
/** Styles with "Menu direction": H and M–T (QUESTIONS #229, #232, #243). */
export const DIRECTION_STYLES: readonly MenuStyle[] = [
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
  /** Another layout than the style's own (null = the style's own); text = show_photos off. */
  menu_layout: 'grid' | 'list' | null
  show_descriptions: boolean
  show_calories: boolean
  /** "Share menu" button on the guest page. */
  show_share: boolean
  /** "Download menu (PDF)" link on the guest page (plan feature pdf_menu). */
  show_pdf_link: boolean
  /** "My order" list for guests (kept on their phone; plan feature order_list). */
  show_order_list: boolean
  /**
   * "My order" as guests get it, and which level turned it off (QUESTIONS #236):
   * the platform (Settings → Features or the Super Admin for this restaurant), the plan, or this switch.
   */
  my_order?: {
    enabled: boolean
    available: boolean
    restaurant: boolean
    reason: 'platform' | 'plan' | 'restaurant' | null
  }
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
  /** Styles M–T only: their artwork on or off (the banner and category photos replace it). */
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
  /** Each style's own layout. */
  style_layouts: Record<MenuStyle, Layout>
  /** The plan allows a layout other than the style's own (Plus and up). */
  layouts: boolean
  /** What guests see now. */
  layout: Layout
}

/** The layout guests get (mirrors the backend's PageTheme::layout). */
export function layoutOf(
  style: MenuStyle,
  menuLayout: 'grid' | 'list' | null,
  showPhotos: boolean,
  own: Record<MenuStyle, Layout>,
  allowed: boolean
): Layout {
  const layout = menuLayout && allowed ? menuLayout : own[style]
  return showPhotos ? layout : 'text'
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

/**
 * URL of the guest page rendered with unsaved values (for the preview frame),
 * as a phone in light or dark mode when a scheme is given. An empty value
 * (no logo, the style's own layout) is sent empty, so the saved one doesn't show.
 */
export function previewUrl(
  t: number,
  v: AppearanceValues,
  scheme?: PreviewScheme
) {
  const q = new URLSearchParams()
  for (const [key, value] of Object.entries(v)) {
    if (key === 'theme_id') continue
    q.set(
      key,
      value === null
        ? ''
        : typeof value === 'boolean'
          ? value
            ? '1'
            : '0'
          : String(value)
    )
  }
  if (scheme) q.set('scheme', scheme)
  return `${api.defaults.baseURL}${tenantUrl(t, 'appearance/preview')}?${q}`
}
