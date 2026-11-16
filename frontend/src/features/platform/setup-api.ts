import { queryOptions } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Setup wizard and dashboard checklist (backend QUESTIONS #195). */
export type SetupCheck = {
  key: string
  step: number
  done: boolean
  optional: boolean
}
export type SetupState = {
  done: boolean
  done_at: string | null
  card_hidden: boolean
  checks: SetupCheck[]
  missing: number
  demo_restaurants: number
  production: boolean
}

export const setupQ = () =>
  queryOptions({
    queryKey: ['platform', 'setup'] as const,
    queryFn: async () =>
      (await api.get<{ data: SetupState }>('/platform/setup')).data.data,
  })

/** Where each check is fixed. */
export const CHECK_LINK: Record<string, string> = {
  two_factor: '/platform/security',
  backup_key: '/platform/backups',
  name: '/platform/settings#general',
  logo: '/platform/settings#branding',
  contact: '/platform/settings#general',
  plans: '/platform/plans',
  email: '/platform/settings#email',
  firebase: '/platform/mobile-app',
  store_links: '/platform/mobile-app#links',
  legal: '/platform/settings#general',
  features: '/platform/settings#features',
  demo: '/platform/restaurants',
}
