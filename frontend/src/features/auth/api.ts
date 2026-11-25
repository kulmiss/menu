import { queryOptions, type QueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type Role = 'owner' | 'manager' | 'staff'

export type SubscriptionState =
  | 'trial'
  | 'active'
  | 'grace'
  | 'read_only'
  | 'unavailable'
  | 'suspended'
  | 'pending'

/** A restaurant the signed-in user belongs to (TenantResource). */
export type Tenant = {
  id: number
  slug: string
  name: string
  timezone: string
  default_locale: string
  currency_code: string
  subscription: {
    state: SubscriptionState
    allows_writes: boolean
    shows_banner: boolean
    stage_ends_at: string | null
  }
  /** Blocked by the Super Admin: the admin shows a full-screen card instead of the app. */
  blocked?: boolean
  /** Why it was blocked (shown on the card); null unless blocked. */
  blocked_reason?: string | null
  membership: {
    id: number
    role: Role
    can_edit_prices: boolean
  } | null
  /** Plan features (with the Super Admin's overrides); missing = on. */
  features?: Partial<Record<string, boolean>>
  /** The plan's branch limit (null = unlimited), so the admin can hide "+ New branch" at the limit. */
  branch_limit?: number | null
  /** Where "Upgrade" buttons point (WhatsApp link / support e-mail). */
  upgrade?: { whatsapp: string | null; email: string | null }
}

/** GET /api/v1/me */
export type Me = {
  id: number
  name: string
  email: string
  locale: string
  email_verified: boolean
  /** A new address waiting for its confirmation link. */
  pending_email?: string | null
  /** The Super Admin requires two-factor for Owners and it isn't on yet (M17). */
  two_factor_required?: boolean
  /** On the common-password list: a gentle banner (SECURITY_REPORT.md M3). */
  password_weak?: boolean
  /** Platform news e-mails on (account e-mails are always sent). */
  news_subscribed?: boolean
  two_factor_enabled: boolean
  /** False for an account made with Google that never set a password. */
  has_password?: boolean
  /** "Continue with Google" (owner, 2026-11-17). Missing on an older server. */
  google?: { available: boolean; linked: boolean; email: string | null }
  is_super_admin: boolean
  /** Set while a Super Admin is "logged in as" this user. */
  impersonation?: {
    tenant_id: number
    tenant_name: string
    user_name: string
  } | null
  tenants: Tenant[]
  /** First-time guidance (backend QUESTIONS #195). Missing on an older server. */
  onboarding?: { web_tour: boolean; app_tour: boolean; setup_needed: boolean }
}

export const ME_KEY = ['me'] as const

export const meQueryOptions = queryOptions({
  queryKey: ME_KEY,
  queryFn: async () => (await api.get<{ data: Me }>('/me')).data.data,
  staleTime: 60_000,
  retry: false,
  // The /admin route guard sends signed-out visitors to sign-in itself.
  meta: { authRedirect: false },
})

export async function signOut(queryClient: QueryClient) {
  try {
    await api.post('/auth/logout')
  } finally {
    // Forget everything cached for this account, even if the request failed.
    queryClient.clear()
  }
}
