import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import CheckInbox from './check-inbox'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const me = meFixture({ email: 'selam@example.com', email_verified: false })
let fake: ReturnType<typeof fakeApi>

describe('CheckInbox', () => {
  afterEach(() => fake.restore())

  it('resends the link and then waits before allowing another', async () => {
    fake = fakeApi({
      'GET /me': { data: { data: me } },
      'POST /auth/email/verification-notification': { status: 202 },
    })
    const screen = await renderWithQuery(<CheckInbox />, { me })

    await expect
      .element(screen.getByText(/We sent a link to selam@example.com/))
      .toBeVisible()
    const resend = screen.getByRole('button', { name: 'Resend email' })
    await userEvent.click(resend)
    await expect.element(resend).toBeDisabled()
    await expect
      .element(screen.getByText(/You can send another email in \d+ s/))
      .toBeVisible()
  })

  it('changes a wrong address', async () => {
    fake = fakeApi({
      'GET /me': { data: { data: me } },
      'POST /signup/email': (req) =>
        JSON.parse(String(req.body)).email === 'used@example.com'
          ? invalid({ email: 'This email address cannot be used.' })
          : { data: { data: { email: 'selam.t@example.com' } } },
    })
    const screen = await renderWithQuery(<CheckInbox />, { me })

    await userEvent.click(
      screen.getByRole('button', { name: 'Wrong email? Change it' })
    )
    const input = screen.getByLabelText('New email address')
    await userEvent.fill(input, 'used@example.com')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send link to this address' })
    )
    await expect
      .element(screen.getByText('This email address cannot be used.'))
      .toBeVisible()

    await userEvent.fill(input, 'selam.t@example.com')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send link to this address' })
    )
    await expect
      .element(screen.getByText(/We sent a link to selam.t@example.com/))
      .toBeVisible()
  })
})
