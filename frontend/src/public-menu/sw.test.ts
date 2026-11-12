// Unit tests for the guest menu's service worker (backend/public/sw.js):
// a saved copy is shown at once, and when the fresh copy differs the open
// page is told ('mu') so it reloads with the new prices.
import source from '@guest-public/sw.js?raw'
import { describe, expect, it, vi } from 'vitest'

type Handler = (e: unknown) => void

function setup(saved: Response | undefined, fresh: Response) {
  const handlers: Record<string, Handler> = {}
  const stored: Record<string, Response> = {}
  const page = { url: 'https://menu.test/demo', postMessage: vi.fn() }
  const other = { url: 'https://menu.test/other', postMessage: vi.fn() }
  const cache = {
    match: vi.fn(async () => saved),
    put: vi.fn(async (k: string, r: Response) => {
      stored[k] = r
    }),
    keys: vi.fn(async () => []),
    delete: vi.fn(),
  }
  const self = {
    addEventListener: (n: string, f: Handler) => (handlers[n] = f),
    skipWaiting: vi.fn(),
    clients: {
      claim: vi.fn(),
      matchAll: vi.fn(async () => [page, other]),
    },
  }
  new Function('self', 'caches', 'fetch', 'location', source)(
    self,
    { open: async () => cache, keys: async () => [] },
    vi.fn(async () => fresh),
    { origin: 'https://menu.test' }
  )
  const waits: Promise<unknown>[] = []
  let shown: Promise<Response> | undefined
  handlers.fetch({
    request: new Request('https://menu.test/demo', { mode: 'same-origin' }),
    respondWith: (p: Promise<Response>) => (shown = p),
    waitUntil: (p: Promise<unknown>) => waits.push(p),
  })
  return { page, other, stored, shown: () => shown!, waits, handlers }
}

// Request objects can't be created with mode "navigate", so the test request
// pretends to be one.
Object.defineProperty(Request.prototype, 'mode', { get: () => 'navigate' })

const html = (body: string, etag?: string) =>
  new Response(`<html>${body}</html>`, {
    headers: etag ? { ETag: etag } : {},
  })
const settle = () => new Promise((r) => setTimeout(r, 20))

describe('guest menu service worker', () => {
  it('shows the saved copy and tells the page when the menu changed', async () => {
    const t = setup(
      html('Macchiato Br 75', '"v1"'),
      html('Macchiato Br 80', '"v2"')
    )

    expect(await (await t.shown()).text()).toContain('Br 75')
    await settle()
    expect(t.stored['/demo']).toBeDefined()
    expect(t.page.postMessage).toHaveBeenCalledWith('mu')
    expect(t.other.postMessage).not.toHaveBeenCalled()
  })

  it('stays quiet when nothing changed', async () => {
    const t = setup(
      html('Macchiato Br 75', '"v1"'),
      html('Macchiato Br 75', '"v1"')
    )

    await t.shown()
    await settle()
    expect(t.page.postMessage).not.toHaveBeenCalled()
  })

  it('compares the page text when there is no ETag', async () => {
    const same = setup(html('Br 75'), html('Br 75'))
    const diff = setup(html('Br 75'), html('Br 80'))

    await same.shown()
    await diff.shown()
    await settle()
    expect(same.page.postMessage).not.toHaveBeenCalled()
    expect(diff.page.postMessage).toHaveBeenCalledWith('mu')
  })

  it('has nothing to tell on a first visit (no saved copy)', async () => {
    const t = setup(undefined, html('Br 80', '"v2"'))

    expect(await (await t.shown()).text()).toContain('Br 80')
    await settle()
    expect(t.page.postMessage).not.toHaveBeenCalled()
  })

  it('answers a page that asks after loading, if the menu changed since it opened', async () => {
    const t = setup(html('Br 75', '"v1"'), html('Br 80', '"v2"'))
    const opened = Date.now() - 1000
    await t.shown()
    await settle()
    t.page.postMessage.mockClear()

    t.handlers.message({ data: opened, source: t.page })
    expect(t.page.postMessage).toHaveBeenCalledWith('mu')
    t.page.postMessage.mockClear()
    t.handlers.message({ data: Date.now() + 1000, source: t.page }) // opened after the change
    expect(t.page.postMessage).not.toHaveBeenCalled()
  })
})
