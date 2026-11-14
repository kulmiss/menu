import { queryOptions, type QueryClient } from '@tanstack/react-query'
import { api, ensureCsrfCookie } from '@/lib/api'

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
  membership: {
    id: number
    role: Role
    can_edit_prices: boolean
  } | null
  /** Plan features (with the Super Admin's overrides); missing = on. */
  features?: Partial<Record<string, boolean>>
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
  is_super_admin: boolean
  /** Set while a Super Admin is "logged in as" this user. */
  impersonation?: {
    tenant_id: number
    tenant_name: string
    user_name: string
  } | null
  tenants: Tenant[]
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

export type SignInInput = { email: string; password: string; remember: boolean }

/** Returns true when the account needs a two-factor code next. */
export async function signIn(input: SignInInput): Promise<boolean> {
  await ensureCsrfCookie()
  const { data } = await api.post<{ two_factor?: boolean }>(
    '/auth/login',
    input
  )
  return data?.two_factor === true
}

export type SignUpInput = {
  name: string
  email: string
  /** Fortify sign-up is for invitations only; restaurants use /signup. */
  invitation_token: string
  password: string
  password_confirmation: string
  locale?: string
}

export async function signUp(input: SignUpInput) {
  await ensureCsrfCookie()
  await api.post('/auth/register', input)
}

export async function requestPasswordReset(email: string) {
  await ensureCsrfCookie()
  await api.post('/auth/forgot-password', { email })
}

export type ResetPasswordInput = {
  token: string
  email: string
  password: string
  password_confirmation: string
  /** The link came from the new-owner welcome e-mail. */
  welcome?: boolean
}

export async function resetPassword(input: ResetPasswordInput) {
  await ensureCsrfCookie()
  await api.post('/auth/reset-password', input)
}

export type TwoFactorInput = { code: string } | { recovery_code: string }

export async function twoFactorChallenge(input: TwoFactorInput) {
  await api.post('/auth/two-factor-challenge', input)
}

export async function resendVerificationEmail() {
  await api.post('/auth/email/verification-notification')
}

export async function signOut(queryClient: QueryClient) {
  try {
    await api.post('/auth/logout')
  } finally {
    // Forget everything cached for this account, even if the request failed.
    queryClient.clear()
  }
}

/** GET /api/v1/invitations/{token} (public). */
export type InvitationPreview = {
  restaurant: string
  email: string
  role: Role
  expires_at: string
}

export function invitationQueryOptions(token: string) {
  return queryOptions({
    queryKey: ['invitation', token],
    queryFn: async () =>
      (await api.get<{ data: InvitationPreview }>(`/invitations/${token}`)).data
        .data,
    retry: false,
  })
}

export async function acceptInvitation(token: string): Promise<Tenant> {
  const { data } = await api.post<{ data: Tenant }>(
    `/invitations/${token}/accept`
  )
  return data.data
}
