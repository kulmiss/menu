import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Translations } from '@/features/menu/types'

export const SOCIALS = [
  'facebook',
  'instagram',
  'tiktok',
  'x',
  'youtube',
  'website',
] as const
export type Social = (typeof SOCIALS)[number]

export type Profile = {
  description: Translations
  address: Translations
  lat: number | null
  lng: number | null
  maps_url: string | null
  phone: string | null
  whatsapp: string | null
  telegram: string | null
  email: string | null
  socials: Partial<Record<Social, string>>
  wifi_name: string | null
  wifi_password: string | null
  show_wifi: boolean
}

/** [weekday 0–6 (Sunday = 0), opens "HH:MM", closes "HH:MM"]. */
export type Hour = { weekday: number; opens_at: string; closes_at: string }

export type Closure = {
  id: number
  branch_id: number | null
  starts_on: string
  ends_on: string
  note: Translations
}

const keys = {
  all: (t: number) => ['tenant', t, 'info'] as const,
  profile: (t: number) => ['tenant', t, 'info', 'profile'] as const,
  hours: (t: number, b: number | null) =>
    ['tenant', t, 'info', 'hours', b] as const,
  closures: (t: number) => ['tenant', t, 'info', 'closures'] as const,
}

const get = async <T>(t: number, path: string) =>
  (await api.get<{ data: T }>(tenantUrl(t, path))).data.data

export const profileQuery = (t: number) =>
  queryOptions({
    queryKey: keys.profile(t),
    queryFn: () => get<Profile>(t, 'profile'),
  })
export const hoursQuery = (t: number, branch: number | null) =>
  queryOptions({
    queryKey: keys.hours(t, branch),
    queryFn: () =>
      get<Hour[]>(t, branch ? `hours?branch_id=${branch}` : 'hours'),
  })
export const closuresQuery = (t: number) =>
  queryOptions({
    queryKey: keys.closures(t),
    queryFn: () => get<Closure[]>(t, 'closures'),
  })

function useInfoMutation<V, R>(
  t: number,
  fn: (v: V) => Promise<R>,
  form = true
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    meta: { inlineErrors: form },
    onSettled: () => qc.invalidateQueries({ queryKey: keys.all(t) }),
  })
}

export const useSaveProfile = (t: number) =>
  useInfoMutation(
    t,
    async (v: Partial<Profile>) =>
      (await api.put<{ data: Profile }>(tenantUrl(t, 'profile'), v)).data.data
  )

export const useSaveHours = (t: number) =>
  useInfoMutation(
    t,
    ({ branch, hours }: { branch: number | null; hours: Hour[] }) =>
      api.put(tenantUrl(t, 'hours'), {
        hours,
        ...(branch ? { branch_id: branch } : {}),
      })
  )

export const useAddClosure = (t: number) =>
  useInfoMutation(
    t,
    (v: {
      starts_on: string
      ends_on: string
      note: Translations
      branch_id?: number | null
    }) => api.post(tenantUrl(t, 'closures'), v)
  )

export const useDeleteClosure = (t: number) =>
  useInfoMutation(
    t,
    (id: number) => api.delete(tenantUrl(t, `closures/${id}`)),
    false
  )
