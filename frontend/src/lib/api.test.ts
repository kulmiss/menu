import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios'
import { fakeApi, invalid } from '@/test-utils/fake-api'
import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  api,
  applyFieldErrors,
  IDEMPOTENCY_HEADER,
  tenantUrl,
  withIdempotencyKey,
} from './api'

let seen: InternalAxiosRequestConfig[] = []
const originalAdapter = api.defaults.adapter

const fakeAdapter: AxiosAdapter = async (config) => {
  seen.push(config)
  return { data: {}, status: 200, statusText: 'OK', headers: {}, config }
}

describe('api client', () => {
  afterEach(() => {
    seen = []
    api.defaults.adapter = originalAdapter
  })

  it('adds a fresh Idempotency-Key to every create/change request', async () => {
    api.defaults.adapter = fakeAdapter

    await api.post('/things', {})
    await api.patch('/things/1', {})
    await api.delete('/things/1')

    const keys = seen.map((c) => c.headers.get(IDEMPOTENCY_HEADER))
    expect(keys.every((k) => typeof k === 'string' && k.length >= 8)).toBe(true)
    expect(new Set(keys).size).toBe(3)
  })

  it('does not add a key to reads', async () => {
    api.defaults.adapter = fakeAdapter

    await api.get('/things')

    expect(seen[0].headers.has(IDEMPOTENCY_HEADER)).toBe(false)
  })

  it('keeps a key passed for a retried action', async () => {
    api.defaults.adapter = fakeAdapter

    await api.post('/things', {}, withIdempotencyKey('retry-key-123'))
    await api.post('/things', {}, withIdempotencyKey('retry-key-123'))

    expect(seen.map((c) => c.headers.get(IDEMPOTENCY_HEADER))).toEqual([
      'retry-key-123',
      'retry-key-123',
    ])
  })

  it('talks to the versioned API with cookies', () => {
    expect(api.defaults.baseURL).toBe('/api/v1')
    expect(api.defaults.withCredentials).toBe(true)
  })
})

describe('api helpers', () => {
  it('builds restaurant URLs', () => {
    expect(tenantUrl(3)).toBe('/t/3')
    expect(tenantUrl(3, '/members/9')).toBe('/t/3/members/9')
  })

  it('puts Laravel 422 messages on matching form fields only', async () => {
    const fake = fakeApi({
      'POST /x': invalid({ email: 'Taken.', other: 'Ignored.' }),
    })
    const setError = vi.fn()
    try {
      await api.post('/x', {})
    } catch (error) {
      expect(applyFieldErrors(error, setError, ['email', 'password'])).toBe(
        true
      )
    } finally {
      fake.restore()
    }
    expect(setError).toHaveBeenCalledOnce()
    expect(setError).toHaveBeenCalledWith('email', {
      type: 'server',
      message: 'Taken.',
    })
  })

  it('refreshes an expired CSRF cookie and repeats the request with the same key', async () => {
    let attempts = 0
    const fake = fakeApi({
      'POST /things': () =>
        ++attempts === 1 ? { status: 419 } : { status: 201 },
    })
    try {
      const response = await api.post(
        '/things',
        {},
        withIdempotencyKey('same-key-123')
      )
      expect(response.status).toBe(201)
    } finally {
      fake.restore()
    }
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
    const fake = fakeApi({ 'POST /things': { status: 419 } })
    try {
      await expect(api.post('/things', {})).rejects.toMatchObject({
        response: { status: 419 },
      })
    } finally {
      fake.restore()
    }
    expect(fake.calls.filter((c) => c.url === '/things')).toHaveLength(2)
  })
})
