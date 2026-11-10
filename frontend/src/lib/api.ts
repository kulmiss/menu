import axios, {
  isAxiosError,
  type AxiosRequestConfig,
  type InternalAxiosRequestConfig,
} from 'axios'

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
  return {
    ...config,
    headers: { ...config.headers, [IDEMPOTENCY_HEADER]: key },
  }
}

/** URL of a restaurant API route: `tenantUrl(3, 'members')` → `/t/3/members`. */
export function tenantUrl(tenantId: number, path = ''): string {
  const tail = path.replace(/^\/+/, '')
  return tail ? `/t/${tenantId}/${tail}` : `/t/${tenantId}`
}

/**
 * Laravel answers 419 when the CSRF cookie expired (long idle tab). Fetch a
 * fresh one and repeat the request once; the Idempotency-Key header is kept,
 * so the server still treats it as the same action.
 */
export async function retryAfterCsrfRefresh(error: unknown) {
  if (
    isAxiosError(error) &&
    error.response?.status === 419 &&
    error.config &&
    !(error.config as { _csrfRetried?: boolean })._csrfRetried
  ) {
    await ensureCsrfCookie()
    return api.request({ ...error.config, _csrfRetried: true } as never)
  }
  throw error
}

api.interceptors.response.use(undefined, retryAfterCsrfRefresh)

/** Status code of a failed request (0 for network errors). */
export function errorStatus(error: unknown): number {
  return isAxiosError(error) ? (error.response?.status ?? 0) : 0
}

type FieldErrors = Record<string, string[] | string>

/** Field messages of a Laravel 422 response, or null. */
export function validationErrors(
  error: unknown
): Record<string, string> | null {
  if (errorStatus(error) !== 422 || !isAxiosError(error)) return null
  const errors = (error.response?.data as { errors?: FieldErrors })?.errors
  if (!errors) return null
  return Object.fromEntries(
    Object.entries(errors).map(([field, msgs]) => [
      field,
      Array.isArray(msgs) ? msgs[0] : msgs,
    ])
  )
}

/**
 * Puts the server's 422 messages under the matching form fields. Returns
 * true when at least one field got a message (so no extra toast is needed).
 */
export function applyFieldErrors<T extends string>(
  error: unknown,
  setError: (name: T, error: { type: string; message: string }) => void,
  fields: readonly T[]
): boolean {
  const errors = validationErrors(error)
  if (!errors) return false
  let applied = false
  for (const [field, message] of Object.entries(errors)) {
    if ((fields as readonly string[]).includes(field)) {
      setError(field as T, { type: 'server', message })
      applied = true
    }
  }
  return applied
}

/** The server's own message for a failed request, if it sent one. */
export function serverMessage(error: unknown): string | null {
  if (!isAxiosError(error)) return null
  const message = (error.response?.data as { message?: unknown })?.message
  return typeof message === 'string' && message.length > 0 ? message : null
}
