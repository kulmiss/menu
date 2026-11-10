import { api, type Adapter, type SentRequest } from '@/lib/api'

type Reply = {
  status?: number
  data?: unknown
  headers?: Record<string, string>
}
type Handler = Reply | ((request: SentRequest) => Reply)

export type Call = {
  method: string
  url: string
  data: Record<string, unknown> | undefined
  headers: Headers
}

/**
 * Answers `api` requests from a table like `{ 'POST /auth/login': {...} }`
 * and records every call. Unknown routes answer 404. Call `restore()` after.
 */
export function fakeApi(routes: Record<string, Handler>) {
  const calls: Call[] = []
  const original = api.defaults.adapter

  const adapter: Adapter = async (request) => {
    const data =
      typeof request.body === 'string' && request.body !== ''
        ? (JSON.parse(request.body) as Record<string, unknown>)
        : undefined
    calls.push({
      method: request.method,
      url: request.url,
      data,
      headers: request.headers,
    })

    const handler =
      routes[`${request.method} ${request.url}`] ??
      // ensureCsrfCookie(): always fine unless a test says otherwise.
      (request.url === '/sanctum/csrf-cookie' ? { status: 204 } : undefined)
    const reply: Reply =
      typeof handler === 'function'
        ? handler(request)
        : (handler ?? { status: 404, data: { message: 'Not found' } })

    return {
      data: reply.data ?? {},
      status: reply.status ?? 200,
      headers: reply.headers ?? {},
    }
  }

  api.defaults.adapter = adapter
  return {
    calls,
    /** Calls to the API itself (without the CSRF cookie request). */
    apiCalls: () => calls.filter((c) => c.url !== '/sanctum/csrf-cookie'),
    restore: () => {
      api.defaults.adapter = original
    },
  }
}

/** A Laravel 422 reply. */
export const invalid = (errors: Record<string, string>): Reply => ({
  status: 422,
  data: {
    message: Object.values(errors)[0],
    errors: Object.fromEntries(
      Object.entries(errors).map(([k, v]) => [k, [v]])
    ),
  },
})
