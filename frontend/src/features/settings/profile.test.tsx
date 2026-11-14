import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ProfileSettings } from './profile'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('Settings → Profile (SECURITY_REPORT.md H3)', () => {
  afterEach(() => fake.restore())

  it('asks for the current password only when the email changes, and sends it', async () => {
    fake = fakeApi({
      'PUT /auth/user/profile-information': { data: {} },
      'GET /me': { data: { data: meFixture() } },
    })
    const screen = await renderWithQuery(<ProfileSettings />, {
      me: meFixture(),
    })

    expect(screen.getByLabelText('Current password').query()).toBeNull()
    await screen.getByLabelText('Email').fill('new@example.com')
    await expect
      .element(screen.getByLabelText('Current password'))
      .toBeVisible()
    await screen.getByLabelText('Current password').fill('my-old-secret')
    await screen.getByRole('button', { name: 'Save' }).click()

    await vi.waitFor(() => {
      const put = fake.apiCalls().find((c) => c.method.toUpperCase() === 'PUT')
      expect(put?.data).toMatchObject({
        email: 'new@example.com',
        current_password: 'my-old-secret',
      })
    })
  })

  it('shows an address that waits for its confirmation link', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<ProfileSettings />, {
      me: meFixture({ pending_email: 'next@example.com' }),
    })

    await expect
      .element(screen.getByText(/Waiting for confirmation: next@example.com/))
      .toBeVisible()
  })
})
