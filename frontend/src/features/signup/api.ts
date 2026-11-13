import { queryOptions } from '@tanstack/react-query'
import { api, ensureCsrfCookie } from '@/lib/api'

/** GET /api/v1/signup/config: what the public sign-up page needs. */
export type SignUpConfig = {
  open: boolean
  /** Set when the Super Admin turned the Cloudflare Turnstile check on. */
  turnstile_site_key: string | null
  currencies: string[]
  default_currency: string
  terms: boolean
  privacy: boolean
  platform: string
}

export const signUpConfigQuery = queryOptions({
  queryKey: ['signup', 'config'],
  queryFn: async () =>
    (await api.get<{ data: SignUpConfig }>('/signup/config')).data.data,
  staleTime: 5 * 60_000,
})

export type SlugCheck = {
  slug: string
  available: boolean
  reason: 'taken' | 'reserved' | 'invalid' | null
}

export function slugCheckQuery(slug: string) {
  return queryOptions({
    queryKey: ['signup', 'slug', slug],
    queryFn: async () =>
      (
        await api.get<{ data: SlugCheck }>(
          `/signup/slug?slug=${encodeURIComponent(slug)}`
        )
      ).data.data,
    staleTime: 30_000,
    retry: false,
  })
}

export type OwnerSignUpInput = {
  restaurant_name: string
  slug: string
  name: string
  email: string
  password: string
  password_confirmation: string
  currency: string
  terms: boolean
  locale: string
  /** Honeypot: people never see it, so it stays empty. */
  website: string
  turnstile?: string
}

/** POST /api/v1/signup: always "check your inbox", whatever the address. */
export async function registerOwner(input: OwnerSignUpInput) {
  await ensureCsrfCookie()
  await api.post('/signup', input)
}

/** POST /api/v1/signup/email: an unverified owner corrects the address. */
export async function changeSignUpEmail(email: string): Promise<string> {
  const { data } = await api.post<{ data: { email: string } }>(
    '/signup/email',
    { email }
  )
  return data.data.email
}

/** Rough strength (0–4) for the hint under the password; the server decides. */
export function passwordStrength(password: string): number {
  if (password.length < 10) return password.length === 0 ? 0 : 1
  let kinds = 0
  if (/[a-z]/.test(password)) kinds++
  if (/[A-Z]/.test(password)) kinds++
  if (/[0-9]/.test(password)) kinds++
  if (/[^a-zA-Z0-9]/.test(password)) kinds++
  const long = password.length >= 14 ? 1 : 0
  return Math.min(4, 1 + Math.max(0, kinds - 1) + long)
}
