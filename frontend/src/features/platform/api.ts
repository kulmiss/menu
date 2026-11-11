import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api } from '@/lib/api'
import { type SubscriptionState } from '@/features/auth/api'

export const FEATURES = [
  'branches',
  'promotions',
  'insights',
  'import_export',
  'remove_branding',
  'pdf_menu',
  'style_editorial',
  'style_grid',
  'style_bistro',
] as const
export const LIMITS = [
  'menus',
  'items',
  'branches',
  'tables',
  'languages',
  'photos',
  'members',
] as const
export const STATES: SubscriptionState[] = [
  'trial',
  'active',
  'grace',
  'read_only',
  'unavailable',
  'suspended',
]

export type Restaurant = {
  id: number
  name: string
  slug: string
  state: SubscriptionState
  status: string
  suspended_reason: string | null
  plan: { id: number; name: string } | null
  trial_ends_at: string | null
  paid_until: string | null
  stage_ends_at: string | null
  owner: { name: string; email: string } | null
  timezone: string
  currency_code: string
  created_at: string | null
  deleted_at: string | null
  url: string
}

export type PaymentRow = {
  id: number
  tenant: { id: number; name: string } | null
  plan: string | null
  amount_minor: number
  currency: string
  method: string
  reference: string | null
  paid_at: string
  period_start: string
  period_end: string
  notes: string | null
}

export type RestaurantDetail = Restaurant & {
  members: { id: number; name: string; email: string; role: string }[]
  features: Record<string, boolean>
  overrides: Record<string, boolean>
  payments: PaymentRow[]
  counts: { menus: number; items: number; branches: number; views_30d: number }
}

export type PlanRow = {
  id: number
  name: Record<string, string>
  slug: string
  price_minor: number
  currency: string
  interval: 'month' | 'year'
  trial_days: number
  limits: Partial<Record<string, number | null>>
  features: Record<string, boolean>
  is_public: boolean
  is_active: boolean
  restaurants: number
}

export const pkeys = { all: ['platform'] as const }
const get = async <T>(path: string) =>
  (await api.get<{ data: T }>(`/platform/${path}`)).data.data
const q = <T>(path: string) =>
  queryOptions({
    queryKey: [...pkeys.all, path] as const,
    queryFn: () => get<T>(path),
  })

export const dashboardQ = () =>
  q<{
    states: Record<string, number>
    restaurants: number
    signups_30d: number
    views_30d: number
    scans_30d: number
    per_plan: Record<string, number>
    expiring: {
      id: number
      name: string
      state: string
      ends_at: string | null
    }[]
    revenue_30d: Record<string, number>
  }>('dashboard')
export const restaurantsQ = (params: string) =>
  q<Restaurant[]>(`restaurants${params ? `?${params}` : ''}`)
export const restaurantQ = (id: number) =>
  q<RestaurantDetail>(`restaurants/${id}`)
export const plansQ = () => q<PlanRow[]>('plans')
export const paymentsQ = (params: string) =>
  q<PaymentRow[]>(`payments${params ? `?${params}` : ''}`)
export const themesQ = () =>
  q<
    {
      id: number
      name: Record<string, string>
      primary: string
      font: string
      style: string
      is_active: boolean
    }[]
  >('themes')
export const announcementsQ = () =>
  q<
    {
      id: number
      title: Record<string, string>
      body: Record<string, string>
      level: string
      plan_ids: number[] | null
      starts_at: string | null
      ends_at: string | null
    }[]
  >('announcements')
export const activityQ = (params: string) =>
  q<
    {
      id: number
      at: string
      tenant: { id: number; name: string | null } | null
      actor: string | null
      impersonator: string | null
      action: string
      subject: string | null
      changes: unknown
    }[]
  >(`activity${params ? `?${params}` : ''}`)
export const settingsQ = () => q<Record<string, string | null>>('settings')

/** SMTP settings (Settings → Email); the password itself never comes back. */
export type MailSettings = {
  source: 'platform' | 'env'
  host: string
  port: number
  encryption: 'none' | 'ssl' | 'tls'
  username: string
  has_password: boolean
  from_address: string
  from_name: string
  env_host: string
  env_mailer: string
}
export const mailQ = () => q<MailSettings>('settings/mail')

/** Any platform change: refetch every platform list afterwards. */
export function usePlatformMutation<V>(
  fn: (v: V) => Promise<unknown>,
  form = true
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    meta: { inlineErrors: form },
    onSettled: () => qc.invalidateQueries({ queryKey: pkeys.all }),
  })
}

export const send = (
  method: 'post' | 'patch' | 'put' | 'delete',
  path: string,
  body?: unknown
) =>
  api.request({
    method: method.toUpperCase() as 'POST',
    url: `/platform/${path}`,
    data: body,
  })

export const money = (minor: number, currency: string) =>
  `${(minor / 100).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${currency}`
