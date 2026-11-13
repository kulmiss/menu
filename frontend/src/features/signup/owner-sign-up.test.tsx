import { fakeApi, invalid } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { passwordStrength } from './api'
import { OwnerSignUpPage } from './owner-sign-up'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

const config = (over: Record<string, unknown> = {}) => ({
  data: {
    data: {
      open: true,
      turnstile_site_key: null,
      currencies: ['ETB', 'KES', 'USD'],
      default_currency: 'ETB',
      terms: true,
      privacy: true,
      platform: 'Menu',
      ...over,
    },
  },
})

const slugFree = (slug: string) => ({
  data: { data: { slug, available: true, reason: null } },
})

type Screen = Awaited<ReturnType<typeof renderWithQuery>>

async function fill(screen: Screen) {
  await userEvent.fill(
    screen.getByRole('textbox', { name: 'Restaurant name' }),
    'Buna & Bread'
  )
  await userEvent.fill(
    screen.getByRole('textbox', { name: 'Your name' }),
    'Selam Tesfaye'
  )
  await userEvent.fill(
    screen.getByRole('textbox', { name: 'Email' }),
    'Selam@Example.com'
  )
  await userEvent.fill(
    screen.getByLabelText('Password', { exact: true }),
    'correct-horse-battery'
  )
  await userEvent.fill(
    screen.getByLabelText('Confirm password'),
    'correct-horse-battery'
  )
}

const submit = (screen: Screen) =>
  userEvent.click(screen.getByRole('button', { name: 'Create free account' }))

describe('OwnerSignUpPage', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake?.restore())

  it('suggests the address from the name and says whether it is free', async () => {
    fake = fakeApi({
      'GET /signup/config': config(),
      'GET /signup/slug?slug=buna-bread': slugFree('buna-bread'),
      'GET /signup/slug?slug=admin': {
        data: { data: { slug: 'admin', available: false, reason: 'reserved' } },
      },
    })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    const name = screen.getByRole('textbox', { name: 'Restaurant name' })
    const slug = screen.getByRole('textbox', { name: 'Menu address' })

    await userEvent.fill(name, 'Buna & Bread')
    await expect.element(slug).toHaveValue('buna-bread')
    await expect
      .element(screen.getByText('This address is available.'))
      .toBeVisible()

    await userEvent.fill(slug, 'admin')
    await userEvent.fill(name, 'Something Else')
    await expect.element(slug).toHaveValue('admin')
    await expect
      .element(screen.getByText('This address is reserved. Try another one.'))
      .toBeVisible()
  })

  it('creates the account and opens the admin "check your inbox" screen', async () => {
    let signedIn = false
    fake = fakeApi({
      'GET /signup/config': config(),
      'GET /signup/slug?slug=buna-bread': slugFree('buna-bread'),
      'POST /signup': () => {
        signedIn = true
        return { status: 201, data: { data: { status: 'check_inbox' } } }
      },
      'GET /me': () =>
        signedIn
          ? { data: { data: { id: 1, email: 'selam@example.com' } } }
          : { status: 401 },
    })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    await fill(screen)
    await userEvent.click(screen.getByRole('checkbox'))
    await submit(screen)

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({ to: '/admin', replace: true })
    )
    const post = fake.apiCalls().find((c) => c.url === '/signup')
    expect(post?.data).toEqual({
      restaurant_name: 'Buna & Bread',
      slug: 'buna-bread',
      name: 'Selam Tesfaye',
      email: 'selam@example.com',
      password: 'correct-horse-battery',
      password_confirmation: 'correct-horse-battery',
      currency: 'ETB',
      terms: true,
      website: '',
      locale: 'en',
    })
    expect(post?.headers.get('Idempotency-Key')).toBeTruthy()
  })

  it('shows the same "check your inbox" when nobody was signed in', async () => {
    fake = fakeApi({
      'GET /signup/config': config(),
      'GET /signup/slug?slug=buna-bread': slugFree('buna-bread'),
      'POST /signup': {
        status: 201,
        data: { data: { status: 'check_inbox' } },
      },
      'GET /me': { status: 401 },
    })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    await fill(screen)
    await userEvent.click(screen.getByRole('checkbox'))
    await submit(screen)

    await expect.element(screen.getByText('Check your inbox')).toBeVisible()
    expect(navigate).not.toHaveBeenCalled()
  })

  it('needs the terms accepted and shows server errors under the fields', async () => {
    fake = fakeApi({
      'GET /signup/config': config(),
      'GET /signup/slug?slug=buna-bread': slugFree('buna-bread'),
      'POST /signup': invalid({
        email: 'This email address cannot be used.',
      }),
    })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    await fill(screen)
    await submit(screen)
    await expect
      .element(screen.getByText('Please accept the terms to continue.'))
      .toBeVisible()
    expect(fake.apiCalls().some((c) => c.url === '/signup')).toBe(false)

    await userEvent.click(screen.getByRole('checkbox'))
    await submit(screen)
    await expect
      .element(screen.getByText('This email address cannot be used.'))
      .toBeVisible()
  })

  it('links the terms and privacy pages and hides the honeypot', async () => {
    fake = fakeApi({ 'GET /signup/config': config() })
    const screen = await renderWithQuery(<OwnerSignUpPage />)

    await expect
      .element(screen.getByRole('link', { name: 'Terms of Service' }))
      .toHaveAttribute('href', '/terms')
    await expect
      .element(screen.getByRole('link', { name: 'Privacy Policy' }))
      .toHaveAttribute('href', '/privacy')
    const honeypot = screen.container.querySelector('#signup-website')
    expect(honeypot?.closest('[aria-hidden="true"]')).not.toBeNull()
    expect(honeypot?.getAttribute('tabindex')).toBe('-1')
  })

  it('says registration is closed when the Super Admin turned it off', async () => {
    fake = fakeApi({ 'GET /signup/config': config({ open: false }) })
    const screen = await renderWithQuery(<OwnerSignUpPage />)

    await expect
      .element(screen.getByText('Registration is closed'))
      .toBeVisible()
    expect(screen.container.querySelector('form')).toBeNull()
  })

  it('shows a strength hint for the password', async () => {
    fake = fakeApi({ 'GET /signup/config': config() })
    const screen = await renderWithQuery(<OwnerSignUpPage />)
    const password = screen.getByLabelText('Password', { exact: true })

    await userEvent.fill(password, 'short')
    await expect
      .element(screen.getByText('Password strength: weak'))
      .toBeVisible()
    await userEvent.fill(password, 'Correct-Horse-9')
    await expect
      .element(screen.getByText('Password strength: strong'))
      .toBeVisible()
  })
})

describe('passwordStrength', () => {
  it('grows with length and variety', () => {
    expect(passwordStrength('')).toBe(0)
    expect(passwordStrength('abcdefghij')).toBe(1)
    expect(passwordStrength('abcdefghi1')).toBe(2)
    expect(passwordStrength('Abcdefghi1')).toBe(3)
    expect(passwordStrength('Abcdefgh-1')).toBe(4)
  })
})
