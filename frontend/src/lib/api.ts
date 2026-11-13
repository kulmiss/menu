/**
 * HTTP client for the Laravel API (same domain, Sanctum cookie auth), built
 * on `fetch` instead of axios to keep the admin download small (PLAN.md
 * §15.6, option A). It keeps what the app relied on:
 * - every create/change request carries an `Idempotency-Key` header, so a
 *   double tap or a retry on a slow network never saves twice (§6.3); pass
 *   the same key again (`withIdempotencyKey`) when retrying one action;
 * - the `XSRF-TOKEN` cookie is sent back as `X-XSRF-TOKEN` (Sanctum);
 * - an expired CSRF cookie (419) is refreshed and the request repeated once
 *   with the same key;
 * - errors carry `response.status` / `response.data` like axios did.
 */
export const IDEMPOTENCY_HEADER = 'Idempotency-Key'

const MUTATING = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

export type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export type RequestConfig = {
  method?: Method
  url?: string
  /** Defaults to `api.defaults.baseURL`; '' for site-relative URLs. */
  baseURL?: string
  params?: Record<string, string | number | boolean | undefined>
  data?: unknown
  headers?: HeadersInit
  responseType?: 'json' | 'blob'
}

/** The request as sent (also what test adapters receive). */
export type SentRequest = {
  method: Method
  url: string
  fullUrl: string
  headers: Headers
  body: BodyInit | null
  responseType: 'json' | 'blob'
  /** Set on the automatic repeat after a CSRF refresh. */
  csrfRetried?: boolean
}

export type ApiResponse<T = unknown> = {
  data: T
  status: number
  /** Lower-case header names. */
  headers: Record<string, string>
}

export type Adapter = (request: SentRequest) => Promise<ApiResponse>

export class ApiError extends Error {
  readonly isApiError = true
  constructor(
    message: string,
    readonly request: SentRequest,
    /** Missing when the network failed (no answer). */
    readonly response?: ApiResponse
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export function isApiError(error: unknown): error is ApiError {
  return (
    typeof error === 'object' &&
    error !== null &&
    (error as { isApiError?: unknown }).isApiError === true
  )
}

export function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID()
  }
  // Old browsers: 32 random hex characters.
  return Array.from({ length: 32 }, () =>
    Math.floor(Math.random() * 16).toString(16)
  ).join('')
}

function readCookie(name: string): string | null {
  const match = document.cookie
    .split('; ')
    .find((part) => part.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

/** The browser `fetch` adapter (tests swap in their own). */
const fetchAdapter: Adapter = async (request) => {
  let response: Response
  try {
    response = await fetch(request.fullUrl, {
      method: request.method,
      headers: request.headers,
      body: request.body,
      credentials: 'include',
    })
  } catch {
    throw new ApiError('Network Error', request)
  }
  const headers: Record<string, string> = {}
  response.headers.forEach((value, key) => {
    headers[key.toLowerCase()] = value
  })
  let data: unknown = null
  if (request.responseType === 'blob' && response.ok) {
    data = await response.blob()
  } else if (response.status !== 204) {
    const text = await response.text()
    try {
      data = text === '' ? null : JSON.parse(text)
    } catch {
      data = text
    }
  }
  return { data, status: response.status, headers }
}

function build(config: RequestConfig): SentRequest {
  const method = (config.method ?? 'GET').toUpperCase() as Method
  const base = config.baseURL ?? api.defaults.baseURL
  const url = config.url ?? ''
  const query = new URLSearchParams(
    Object.entries(config.params ?? {})
      .filter(([, v]) => v !== undefined)
      .map(([k, v]) => [k, String(v)])
  ).toString()
  const fullUrl = `${base}${url}${query ? `?${query}` : ''}`

  const headers = new Headers(config.headers)
  headers.set('Accept', 'application/json')
  headers.set('X-Requested-With', 'XMLHttpRequest')
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf) headers.set('X-XSRF-TOKEN', xsrf)
  if (MUTATING.has(method) && !headers.has(IDEMPOTENCY_HEADER)) {
    headers.set(IDEMPOTENCY_HEADER, newIdempotencyKey())
  }

  let body: BodyInit | null = null
  if (config.data instanceof FormData || config.data instanceof Blob) {
    body = config.data // the browser sets the multipart boundary
  } else if (config.data !== undefined) {
    headers.set('Content-Type', 'application/json')
    body = JSON.stringify(config.data)
  }

  return {
    method,
    url,
    fullUrl,
    headers,
    body,
    responseType: config.responseType ?? 'json',
  }
}

async function send<T>(request: SentRequest): Promise<ApiResponse<T>> {
  const response = await api.defaults.adapter(request)
  if (response.status >= 200 && response.status < 300) {
    return response as ApiResponse<T>
  }
  // Laravel answers 419 when the CSRF cookie expired (long idle tab): fetch
  // a fresh one and repeat once; the Idempotency-Key header is kept, so the
  // server still treats it as the same action.
  if (response.status === 419 && !request.csrfRetried) {
    await ensureCsrfCookie()
    const xsrf = readCookie('XSRF-TOKEN')
    if (xsrf) request.headers.set('X-XSRF-TOKEN', xsrf)
    return send<T>({ ...request, csrfRetried: true })
  }
  // "Log in as" ended after 2 hours: the server already switched back.
  if ((response.data as { code?: unknown })?.code === 'impersonation.expired') {
    location.assign('/platform')
  }
  throw new ApiError(
    `Request failed with status code ${response.status}`,
    request,
    response
  )
}

export const api = {
  defaults: {
    baseURL: '/api/v1',
    adapter: fetchAdapter,
  },
  request: <T = unknown>(config: RequestConfig) => send<T>(build(config)),
  get: <T = unknown>(url: string, config: RequestConfig = {}) =>
    api.request<T>({ ...config, method: 'GET', url }),
  delete: <T = unknown>(url: string, config: RequestConfig = {}) =>
    api.request<T>({ ...config, method: 'DELETE', url }),
  post: <T = unknown>(
    url: string,
    data?: unknown,
    config: RequestConfig = {}
  ) => api.request<T>({ ...config, method: 'POST', url, data }),
  put: <T = unknown>(url: string, data?: unknown, config: RequestConfig = {}) =>
    api.request<T>({ ...config, method: 'PUT', url, data }),
  patch: <T = unknown>(
    url: string,
    data?: unknown,
    config: RequestConfig = {}
  ) => api.request<T>({ ...config, method: 'PATCH', url, data }),
}

/** Sanctum sets the XSRF-TOKEN cookie; call before signing in or up. */
export async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie', { baseURL: '' })
}

/** Run one user action; retries of it should reuse the returned key. */
export function withIdempotencyKey(
  key: string,
  config: RequestConfig = {}
): RequestConfig {
  const headers = new Headers(config.headers)
  headers.set(IDEMPOTENCY_HEADER, key)
  return { ...config, headers }
}

/** URL of a restaurant API route: `tenantUrl(3, 'members')` → `/t/3/members`. */
export function tenantUrl(tenantId: number, path = ''): string {
  const tail = path.replace(/^\/+/, '')
  return tail ? `/t/${tenantId}/${tail}` : `/t/${tenantId}`
}

/** Status code of a failed request (0 for network errors). */
export function errorStatus(error: unknown): number {
  return isApiError(error) ? (error.response?.status ?? 0) : 0
}

type FieldErrors = Record<string, string[] | string>

/** Field messages of a Laravel 422 response, or null. */
export function validationErrors(
  error: unknown
): Record<string, string> | null {
  if (errorStatus(error) !== 422 || !isApiError(error)) return null
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
  if (!isApiError(error)) return null
  const message = (error.response?.data as { message?: unknown })?.message
  return typeof message === 'string' && message.length > 0 ? message : null
}
