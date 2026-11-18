import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { SignupSettings } from '@/features/platform/signup-settings'
import { SecuritySettings } from '@/features/settings/security'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

// "Continue with Google" (owner, 2026-11-17).
describe('Continue with Google', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake?.restore())

  it('links Google', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture({
        has_password: true,
        google: { available: true, linked: false, email: null },
      }),
    })
    await expect
      .element(screen.getByRole('link', { name: 'Continue with Google' }))
      .toHaveAttribute('href', '/auth/google?link=1')
  })

  it('unlinks Google', async () => {
    fake = fakeApi({
      'DELETE /me/google': { data: { data: { linked: false } } },
    })
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture({
        has_password: true,
        google: { available: true, linked: true, email: 'selam@gmail.com' },
      }),
    })
    await expect
      .element(screen.getByText(/Linked to selam@gmail.com/))
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Unlink Google' }))
    await vi.waitFor(() =>
      expect(fake.apiCalls().some((c) => c.url === '/me/google')).toBe(true)
    )
  })

  it('asks a Google-only account to set a password before unlinking', async () => {
    fake = fakeApi({
      'POST /me/password-link': {
        data: { data: { sent_to: 'selam@example.com' } },
      },
    })
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture({
        has_password: false,
        google: { available: true, linked: true, email: 'selam@gmail.com' },
      }),
    })
    await userEvent.click(
      screen.getByRole('button', {
        name: 'E-mail me a link to set a password',
      })
    )
    expect(
      screen.getByRole('button', { name: 'Unlink Google' }).elements()
    ).toHaveLength(0)
    await vi.waitFor(() =>
      expect(fake.apiCalls().some((c) => c.url === '/me/password-link')).toBe(
        true
      )
    )
  })

  it('shows nothing when Google sign-in is off or for a Super Admin', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture({
        google: { available: false, linked: false, email: null },
      }),
    })
    await expect.element(screen.getByText('Password').first()).toBeVisible()
    expect(
      screen.getByText('Link your Google account').elements()
    ).toHaveLength(0)
  })
})

describe('Super Admin → Settings → Sign-up → Continue with Google', () => {
  afterEach(() => fake?.restore())

  it('switches it on and warns while the keys are missing', async () => {
    fake = fakeApi({ 'PUT /super/settings': { data: { data: {} } } })
    const screen = await renderWithQuery(
      <SignupSettings
        initial={{
          signup_open: true,
          signup_plan_id: null,
          signup_approval: false,
          signup_notify: true,
          turnstile_enabled: false,
          turnstile_site_key: '',
          turnstile_secret_set: false,
          disposable_domains: [],
          plans: [],
          google_login: false,
          google_configured: false,
        }}
      />
    )
    await userEvent.click(
      screen.getByRole('switch', { name: 'Continue with Google' })
    )
    await expect
      .element(screen.getByText(/Google keys are missing/))
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({ google_login: true })
  })
})
