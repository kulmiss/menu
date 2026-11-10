import { afterEach, describe, expect, it } from 'vitest'
import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios'
import { api, IDEMPOTENCY_HEADER, withIdempotencyKey } from './api'

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
