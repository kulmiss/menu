import { isRedirect } from '@tanstack/react-router'
import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { testQueryClient } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { requireMe } from './guard'

let fake: ReturnType<typeof fakeApi>
afterEach(() => fake.restore())

describe('requireMe (the /admin route guard)', () => {
  it('loads the signed-in user', async () => {
    fake = fakeApi({ 'GET /me': { data: { data: meFixture() } } })

    const me = await requireMe(testQueryClient(), '/admin/team')

    expect(me.email).toBe('selam@example.com')
  })

  it('sends signed-out visitors to sign-in and back afterwards', async () => {
    fake = fakeApi({ 'GET /me': { status: 401 } })

    const result = await requireMe(testQueryClient(), '/admin/team?x=1').catch(
      (e) => e
    )

    expect(isRedirect(result)).toBe(true)
    expect(result.options).toMatchObject({
      to: '/auth/sign-in',
      search: { redirect: '/admin/team?x=1' },
    })
  })

  it('lets other errors through to the error page', async () => {
    fake = fakeApi({ 'GET /me': { status: 500 } })

    const result = await requireMe(testQueryClient(), '/admin').catch((e) => e)

    expect(isRedirect(result)).toBe(false)
    expect(result.response.status).toBe(500)
  })
})
