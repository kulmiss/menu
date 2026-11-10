import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Media } from '@/features/menu/types'

export type Layout = 'list' | 'grid' | 'large'
export type Font = 'system' | 'serif' | 'rounded'

export type AppearanceValues = {
  primary_color: string
  font: Font
  layout: Layout
  show_prices: boolean
  show_photos: boolean
  show_descriptions: boolean
  show_calories: boolean
  theme_id: number | null
  logo_media_id: number | null
  cover_media_id: number | null
}

export type ThemePreset = {
  id: number
  name: string
  primary: string
  font: Font
  layout: Layout
}

export type Appearance = Omit<
  AppearanceValues,
  'logo_media_id' | 'cover_media_id'
> & {
  logo: Media | null
  cover: Media | null
  warnings: { key: string; message: string }[]
  themes: ThemePreset[]
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
