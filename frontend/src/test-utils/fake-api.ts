import axios, {
  AxiosError,
  type AxiosHeaders,
  type AxiosAdapter,
  type InternalAxiosRequestConfig,
} from 'axios'
import { api } from '@/lib/api'

type Reply = { status?: number; data?: unknown }
type Handler = Reply | ((config: InternalAxiosRequestConfig) => Reply)

export type Call = {
  method: string
  url: string
  data: Record<string, unknown> | undefined
  headers: AxiosHeaders
}

/**
 * Answers `api` requests from a table like `{ 'POST /auth/login': {...} }`
 * and records every call. Unknown routes answer 404. Call `restore()` after.
 */
export function fakeApi(routes: Record<string, Handler>) {
  const calls: Call[] = []
  const original = api.defaults.adapter
  const originalGlobal = axios.defaults.adapter

  const adapter: AxiosAdapter = async (config) => {
    const method = (config.method ?? 'get').toUpperCase()
    const url = config.url ?? ''
    const data =
      typeof config.data === 'string' && config.data !== ''
        ? (JSON.parse(config.data) as Record<string, unknown>)
        : undefined
    calls.push({ method, url, data, headers: config.headers })

    const handler =
      routes[`${method} ${url}`] ??
      // ensureCsrfCookie() (plain axios): always fine unless a test says otherwise.
      (url === '/sanctum/csrf-cookie' ? { status: 204 } : undefined)
    const reply: Reply =
      typeof handler === 'function'
        ? handler(config)
        : (handler ?? { status: 404, data: { message: 'Not found' } })
    const status = reply.status ?? 200
    const response = {
      data: reply.data ?? {},
      status,
      statusText: String(status),
      headers: {},
      config,
    }
    if (status >= 400) {
      throw new AxiosError(
        `Request failed with status code ${status}`,
        AxiosError.ERR_BAD_REQUEST,
        config,
        null,
        response
      )
    }
    return response
  }

  api.defaults.adapter = adapter
  axios.defaults.adapter = adapter
  return {
    calls,
    /** Calls to the API itself (without the CSRF cookie request). */
    apiCalls: () => calls.filter((c) => c.url !== '/sanctum/csrf-cookie'),
    restore: () => {
      api.defaults.adapter = original
      axios.defaults.adapter = originalGlobal
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
