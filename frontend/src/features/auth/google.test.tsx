import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { SignupSettings } from '@/features/platform/signup-settings'
import { SecuritySettings } from '@/features/settings/security'
import { OwnerSignUpPage } from '@/features/signup/owner-sign-up'
import { GoogleFinishPage } from './google-finish'
import { SignInPage } from './sign-in'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

const config = (google: boolean) => ({
  data: {
    data: {
      open: true,
      turnstile_site_key: null,
      currencies: ['ETB'],
      default_currency: 'ETB',
      terms: true,
      privacy: true,
      platform: 'Menu',
      google,
    },
  },
})

// "Continue with Google" (owner, 2026-11-17).
describe('Continue with Google', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake?.restore())

  it('shows the button on the sign-in page while it is switched on', async () => {
    fake = fakeApi({ 'GET /signup/config': config(true) })
    const screen = await renderWithQuery(<SignInPage />)
    await expect
      .element(screen.getByRole('link', { name: 'Continue with Google' }))
      .toHaveAttribute('href', '/auth/google')
  })

  it('shows it on the sign-up page too, above the e-mail form', async () => {
    fake = fakeApi({ 'GET /signup/config': config(true) })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    await expect
      .element(screen.getByRole('link', { name: 'Continue with Google' }))
      .toBeVisible()
    await expect
      .element(screen.getByText('or sign up with e-mail'))
      .toBeVisible()
  })

  it('hides it while it is switched off', async () => {
    fake = fakeApi({ 'GET /signup/config': config(false) })
    const screen = await renderWithQuery(<SignInPage />)
    await expect
      .element(screen.getByRole('button', { name: 'Sign in' }))
      .toBeVisible()
    expect(
      screen.getByRole('link', { name: 'Continue with Google' }).elements()
    ).toHaveLength(0)
  })

  it('says why Google could not sign in', async () => {
    fake = fakeApi({ 'GET /signup/config': config(true) })
    const screen = await renderWithQuery(<SignInPage google='super_admin' />)
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent(
        'Super Admin accounts sign in with their password and two-factor code only.'
      )
  })

  it('asks a new Google user only for the restaurant name and the Terms', async () => {
    fake = fakeApi({
      'GET /auth/google/pending': {
        data: {
          data: {
            email: 'selam@gmail.com',
            name: 'Selam',
            terms: true,
            privacy: true,
          },
        },
      },
      'GET /sanctum/csrf-cookie': { status: 204 },
      'POST /auth/google/finish': {
        status: 201,
        data: { data: { redirect: '/admin' } },
      },
    })
    const screen = await renderWithQuery(<GoogleFinishPage />)
    await expect
      .element(
        screen.getByText('You are signing up with Google as selam@gmail.com.')
      )
      .toBeVisible()

    await userEvent.click(
      screen.getByRole('button', { name: 'Create my free menu' })
    )
    await expect
      .element(screen.getByText("Enter the restaurant's name."))
      .toBeVisible()
    await expect
      .element(screen.getByText('Please accept the terms to continue.'))
      .toBeVisible()

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Restaurant name' }),
      'Buna & Bread'
    )
    await expect
      .element(screen.getByText(/Your menu will be at .+\/buna-bread/))
      .toBeVisible()
    await userEvent.click(screen.getByRole('checkbox'))
    await userEvent.click(
      screen.getByRole('button', { name: 'Create my free menu' })
    )

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({ to: '/admin', replace: true })
    )
    expect(
      fake.apiCalls().find((c) => c.url === '/auth/google/finish')?.data
    ).toEqual({ restaurant_name: 'Buna & Bread', terms: true, locale: 'en' })
  })

  it('offers "Continue with Google" again when the wait is over', async () => {
    fake = fakeApi({ 'GET /auth/google/pending': { status: 404 } })
    const screen = await renderWithQuery(<GoogleFinishPage />)
    await expect
      .element(screen.getByText('That took too long. Please start again.'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Continue with Google' }))
      .toBeVisible()
  })
})

describe('Account → Security → Google', () => {
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
    fake = fakeApi({ 'PUT /platform/settings': { data: { data: {} } } })
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
