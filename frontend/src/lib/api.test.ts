import { fakeApi, invalid } from '@/test-utils/fake-api'
import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  api,
  ApiError,
  applyFieldErrors,
  errorStatus,
  IDEMPOTENCY_HEADER,
  tenantUrl,
  withIdempotencyKey,
} from './api'

let fake: ReturnType<typeof fakeApi>
afterEach(() => fake?.restore())

describe('api client', () => {
  it('adds a fresh Idempotency-Key to every create/change request', async () => {
    fake = fakeApi({
      'POST /things': {},
      'PATCH /things/1': {},
      'DELETE /things/1': { status: 204 },
    })

    await api.post('/things', {})
    await api.patch('/things/1', {})
    await api.delete('/things/1')

    const keys = fake.calls.map((c) => c.headers.get(IDEMPOTENCY_HEADER))
    expect(keys.every((k) => typeof k === 'string' && k.length >= 8)).toBe(true)
    expect(new Set(keys).size).toBe(3)
  })

  it('does not add a key to reads', async () => {
    fake = fakeApi({ 'GET /things': {} })

    await api.get('/things')

    expect(fake.calls[0].headers.has(IDEMPOTENCY_HEADER)).toBe(false)
  })

  it('keeps a key passed for a retried action', async () => {
    fake = fakeApi({ 'POST /things': {} })

    await api.post('/things', {}, withIdempotencyKey('retry-key-123'))
    await api.post('/things', {}, withIdempotencyKey('retry-key-123'))

    expect(fake.calls.map((c) => c.headers.get(IDEMPOTENCY_HEADER))).toEqual([
      'retry-key-123',
      'retry-key-123',
    ])
  })

  it('talks to the versioned API with JSON, cookies and the XSRF token', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/'
    const seen = vi.fn()
    fake = fakeApi({ 'POST /things': (r) => (seen(r), {}) })

    await api.post('/things', { a: 1 }, { params: { page: 2 } })

    const request = seen.mock.calls[0][0]
    expect(request.fullUrl).toBe('/api/v1/things?page=2')
    expect(request.headers.get('X-XSRF-TOKEN')).toBe('abc==')
    expect(request.headers.get('Accept')).toBe('application/json')
    expect(request.headers.get('Content-Type')).toBe('application/json')
    expect(fake.calls[0].data).toEqual({ a: 1 })
    document.cookie = 'XSRF-TOKEN=; max-age=0; path=/'
  })

  it('sends files as multipart without a JSON content type', async () => {
    const seen = vi.fn()
    fake = fakeApi({ 'POST /media': (r) => (seen(r), { status: 201 }) })
    const form = new FormData()
    form.append('file', new Blob(['x']), 'a.jpg')

    await api.post('/media', form)

    expect(seen.mock.calls[0][0].body).toBe(form)
    expect(seen.mock.calls[0][0].headers.has('Content-Type')).toBe(false)
  })

  it('throws an error with the status and the server answer', async () => {
    fake = fakeApi({ 'GET /x': { status: 403, data: { message: 'No.' } } })

    const error = await api.get('/x').catch((e) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(errorStatus(error)).toBe(403)
    expect(error.response.data).toEqual({ message: 'No.' })
  })
})

describe('api helpers', () => {
  it('goes back to the platform when "log in as" ended (2 hours)', async () => {
    // Catch the page navigation instead of leaving the test page.
    const assign = vi.fn()
    const nav = (window as unknown as { navigation: EventTarget }).navigation
    const stop = (e: Event) => {
      e.preventDefault()
      assign(
        new URL(
          (e as unknown as { destination: { url: string } }).destination.url
        ).pathname
      )
    }
    nav.addEventListener('navigate', stop)
    fake = fakeApi({
      'GET /t/1/menus': {
        status: 409,
        data: { message: 'Ended.', code: 'impersonation.expired' },
      },
      'POST /t/1/menus': { status: 409, data: { message: 'Busy.' } },
    })
    await expect(api.get('/t/1/menus')).rejects.toBeInstanceOf(ApiError)
    expect(assign).toHaveBeenCalledWith('/platform')
    // Any other 409 (a save still running) stays on the page.
    await expect(api.post('/t/1/menus', {})).rejects.toBeInstanceOf(ApiError)
    expect(assign).toHaveBeenCalledTimes(1)
    nav.removeEventListener('navigate', stop)
  })

  it('builds restaurant URLs', () => {
    expect(tenantUrl(3)).toBe('/t/3')
    expect(tenantUrl(3, '/members/9')).toBe('/t/3/members/9')
  })

  it('puts Laravel 422 messages on matching form fields only', async () => {
    fake = fakeApi({
      'POST /x': invalid({ email: 'Taken.', other: 'Ignored.' }),
    })
    const setError = vi.fn()

    const error = await api.post('/x', {}).catch((e) => e)

    expect(applyFieldErrors(error, setError, ['email', 'password'])).toBe(true)
    expect(setError).toHaveBeenCalledOnce()
    expect(setError).toHaveBeenCalledWith('email', {
      type: 'server',
      message: 'Taken.',
    })
  })

  it('refreshes an expired CSRF cookie and repeats the request with the same key', async () => {
    let attempts = 0
    fake = fakeApi({
      'POST /things': () =>
        ++attempts === 1 ? { status: 419 } : { status: 201 },
    })

    const response = await api.post(
      '/things',
      {},
      withIdempotencyKey('same-key-123')
    )

    expect(response.status).toBe(201)
    expect(fake.calls.map((c) => c.url)).toEqual([
      '/things',
      '/sanctum/csrf-cookie',
      '/things',
    ])
    const keys = fake.calls
      .filter((c) => c.url === '/things')
      .map((c) => c.headers.get(IDEMPOTENCY_HEADER))
    expect(keys).toEqual(['same-key-123', 'same-key-123'])
  })

  it('gives up after one CSRF retry', async () => {
    fake = fakeApi({ 'POST /things': { status: 419 } })

    await expect(api.post('/things', {})).rejects.toMatchObject({
      response: { status: 419 },
    })
    expect(fake.calls.filter((c) => c.url === '/things')).toHaveLength(2)
  })

  it('reports a network failure as status 0', async () => {
    const original = api.defaults.adapter
    api.defaults.adapter = async (request) => {
      throw new ApiError('Network Error', request)
    }
    const error = await api.get('/x').catch((e) => e)
    api.defaults.adapter = original

    expect(errorStatus(error)).toBe(0)
  })
})
