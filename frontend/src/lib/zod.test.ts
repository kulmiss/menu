// Bug (2026-11-17): every page with a form (sign-in, sign-up, password reset,
// two-factor, team invite) logged "CSP script-src blocked eval" in the console:
// zod probes `new Function("")` on first use and the page's CSP blocks it.
import { afterEach, describe, expect, it, vi } from 'vitest'
import { z } from './zod'

afterEach(() => vi.unstubAllGlobals())

describe('@/lib/zod', () => {
  it('never calls the Function constructor (blocked by the CSP) when validating', () => {
    const probe = vi.fn()
    vi.stubGlobal(
      'Function',
      new Proxy(Function, {
        apply: (...a) => (probe(), Reflect.apply(...a)),
        construct: (...a) => (probe(), Reflect.construct(...a)),
      })
    )
    const schema = z.object({
      email: z.email(),
      password: z.string().min(8),
      remember: z.boolean().optional(),
    })
    expect(
      schema.safeParse({ email: 'a@b.co', password: 'password' }).success
    ).toBe(true)
    expect(schema.safeParse({ email: 'nope', password: '' }).success).toBe(
      false
    )
    expect(probe).not.toHaveBeenCalled()
  })
})
