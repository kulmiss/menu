import axios, { type AxiosRequestConfig, type InternalAxiosRequestConfig } from 'axios'

/**
 * HTTP client for the Laravel API (same domain, Sanctum cookie auth).
 *
 * Every create/change request carries an `Idempotency-Key` header, so a
 * double tap or a retry on a slow network never saves twice (PLAN.md §6.3).
 * Pass the same key again (see `newIdempotencyKey`) when retrying one action.
 */
export const IDEMPOTENCY_HEADER = 'Idempotency-Key'

const MUTATING = new Set(['post', 'put', 'patch', 'delete'])

export function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID()
  }
  // Old browsers: 32 random hex characters.
  return Array.from({ length: 32 }, () =>
    Math.floor(Math.random() * 16).toString(16)
  ).join('')
}

export function addIdempotencyKey(
  config: InternalAxiosRequestConfig
): InternalAxiosRequestConfig {
  const method = (config.method ?? 'get').toLowerCase()
  if (MUTATING.has(method) && !config.headers.has(IDEMPOTENCY_HEADER)) {
    config.headers.set(IDEMPOTENCY_HEADER, newIdempotencyKey())
  }
  return config
}

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(addIdempotencyKey)

/** Sanctum sets the XSRF-TOKEN cookie; call before signing in or up. */
export function ensureCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

/** Run one user action; retries of it should reuse the returned key. */
export function withIdempotencyKey(
  key: string,
  config: AxiosRequestConfig = {}
): AxiosRequestConfig {
  return { ...config, headers: { ...config.headers, [IDEMPOTENCY_HEADER]: key } }
}
