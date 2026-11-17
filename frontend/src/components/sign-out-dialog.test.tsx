import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { catchNavigation } from '@/test-utils/navigation'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { ME_KEY } from '@/features/auth/api'
import { SignOutDialog } from './sign-out-dialog'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>
let away: ReturnType<typeof catchNavigation>

describe('SignOutDialog', () => {
  beforeEach(() => {
    navigate.mockClear()
    away = catchNavigation()
  })
  afterEach(() => {
    fake.restore()
    away.restore()
  })

  it('signs out on the server, forgets cached data and opens the sign-in page', async () => {
    fake = fakeApi({ 'POST /auth/logout': { status: 204 } })
    const screen = await renderWithQuery(
      <SignOutDialog open onOpenChange={vi.fn()} />,
      {
        me: meFixture(),
      }
    )

    await userEvent.click(screen.getByRole('button', { name: 'Sign out' }))

    // The sign-in page is a server page (QUESTIONS #222): a full page load.
    await vi.waitFor(() => expect(away.urls).toEqual(['/signin']))
    expect(fake.apiCalls().map((c) => `${c.method} ${c.url}`)).toEqual([
      'POST /auth/logout',
    ])
    expect(screen.client.getQueryData(ME_KEY)).toBeUndefined()
  })

  it('still leaves the admin when the network is down', async () => {
    fake = fakeApi({ 'POST /auth/logout': { status: 500 } })
    const screen = await renderWithQuery(
      <SignOutDialog open onOpenChange={vi.fn()} />,
      {
        me: meFixture(),
      }
    )

    await userEvent.click(screen.getByRole('button', { name: 'Sign out' }))

    await vi.waitFor(() => expect(away.urls).toEqual(['/signin']))
    expect(screen.client.getQueryData(ME_KEY)).toBeUndefined()
  })

  it('does nothing on Cancel', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(
      <SignOutDialog open onOpenChange={vi.fn()} />
    )

    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(fake.calls).toEqual([])
    expect(away.urls).toEqual([])
  })
})
