import { axeViolations } from '@/test-utils/axe'
import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { RestaurantsPage } from './restaurants'
import { SettingsPage } from './screens'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
const pending = {
  id: 9,
  name: 'Buna & Bread',
  slug: 'buna-bread',
  state: 'pending',
  signup_state: 'pending_approval',
  status: 'active',
  suspended_reason: null,
  plan: { id: 1, name: 'Free' },
  trial_ends_at: null,
  paid_until: null,
  stage_ends_at: null,
  owner: { name: 'Selam', email: 'selam@example.com' },
  timezone: 'Africa/Addis_Ababa',
  currency_code: 'ETB',
  created_at: '2026-11-13',
  deleted_at: null,
  url: 'http://localhost/buna-bread',
}
const detail = {
  data: {
    data: {
      ...pending,
      members: [],
      features: {},
      overrides: {},
      payments: [],
      counts: { menus: 0, items: 0, branches: 1, views_30d: 0 },
    },
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin sign-up controls', () => {
  afterEach(() => fake.restore())

  it('lists pending sign-ups and approves one', async () => {
    fake = fakeApi({
      'GET /super/restaurants': { data: { data: [pending] } },
      'GET /super/plans': { data: { data: [] } },
      'GET /super/restaurants/9': detail,
      'POST /super/restaurants/9/approve': { data: { data: pending } },
    })
    const screen = await renderWithQuery(<RestaurantsPage />, { me: admin })
    await expect
      .element(screen.getByText('Pending sign-up').first())
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Buna & Bread' }))
    await expect
      .element(screen.getByText('Sign-up: waiting for your approval'))
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Approve' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.url)
      .toBe('/super/restaurants/9/approve')
  })

  it('rejects a sign-up with a reason', async () => {
    fake = fakeApi({
      'GET /super/restaurants': { data: { data: [pending] } },
      'GET /super/plans': { data: { data: [] } },
      'GET /super/restaurants/9': detail,
      'POST /super/restaurants/9/reject': {
        data: { data: { removed: true } },
      },
    })
    const screen = await renderWithQuery(<RestaurantsPage />, { me: admin })
    await userEvent.click(screen.getByRole('button', { name: 'Buna & Bread' }))
    await userEvent.click(screen.getByRole('button', { name: 'Reject' }))
    await userEvent.fill(
      screen.getByLabelText('Reason', { exact: true }),
      'Not a restaurant, please contact us.'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Continue' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toEqual({ reason: 'Not a restaurant, please contact us.' })
    expect(fake.calls.find((c) => c.method === 'POST')?.url).toBe(
      '/super/restaurants/9/reject'
    )
  })

  it('saves the sign-up settings without sending the stored secret', async () => {
    fake = fakeApi({
      'GET /super/settings': {
        data: {
          data: {
            name: 'Menu',
            support_email: 'help@menu.et',
            domain: null,
            default_currency: 'ETB',
            terms: '',
            privacy: '',
            share_image: null,
            share_image_url: '/img/share.jpg',
            logo_url: null,
            signup_open: true,
            signup_plan_id: null,
            signup_approval: false,
            signup_notify: true,
            turnstile_enabled: false,
            turnstile_site_key: '',
            turnstile_secret_set: true,
            disposable_domains: ['mailinator.com'],
            upgrade_whatsapp: '',
            plans: [
              { id: 1, name: 'Free', slug: 'free' },
              { id: 2, name: 'Basic', slug: 'basic' },
            ],
          },
        },
      },
      'GET /super/settings/mail': { status: 404 },
      'PUT /super/settings': { data: { data: {} } },
    })
    const screen = await renderWithQuery(<SettingsPage />, { me: admin })

    await userEvent.click(screen.getByRole('tab', { name: 'Sign-up' }))
    await userEvent.click(
      screen.getByRole('switch', { name: 'Public sign-up' })
    )
    await userEvent.click(
      screen.getByRole('switch', { name: 'Require my approval' })
    )
    await userEvent.fill(
      screen.getByLabelText('Blocked e-mail domains'),
      'mailinator.com\nYopmail.com'
    )
    await expect
      .element(screen.getByLabelText('Turnstile secret key'))
      .toHaveAttribute('placeholder', 'Saved (leave empty to keep it)')
    const save = screen.getByRole('button', { name: 'Save' })
    await userEvent.click(save)

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        signup_open: false,
        signup_approval: true,
        signup_plan_id: null,
        turnstile_secret: '',
        disposable_domains: ['mailinator.com', 'yopmail.com'],
      })
  })
})

describe('Super Admin landing page settings', () => {
  afterEach(() => {
    fake.restore()
    for (const key of Object.keys(localStorage))
      if (key.startsWith('menu:sections:')) localStorage.removeItem(key)
  })

  it('opens the new Landing page from an old "Settings → Landing page" link', async () => {
    window.history.replaceState(null, '', '#landing')
    navigate.mockClear()
    fake = fakeApi({
      'GET /super/settings': {
        data: {
          data: {
            name: 'Menu',
            share_image_url: '/img/share.jpg',
            logo_url: null,
            signup_open: true,
            signup_plan_id: null,
            signup_approval: false,
            signup_notify: true,
            turnstile_enabled: false,
            turnstile_site_key: '',
            turnstile_secret_set: false,
            disposable_domains: [],
            plans: [],
          },
        },
      },
      'GET /super/settings/mail': { status: 404 },
    })
    const screen = await renderWithQuery(<SettingsPage />, { me: admin })

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        to: '/platform/$section',
        params: { section: 'landing' },
        replace: true,
      })
    )
    expect(
      screen.getByRole('tab', { name: 'Landing page' }).elements()
    ).toHaveLength(0)
    window.history.replaceState(null, '', '#')
  })

  it('splits settings into tabs, keeps unsaved edits and remembers the tab in the address', async () => {
    window.history.replaceState(null, '', '#email')
    fake = fakeApi({
      'GET /super/settings': {
        data: {
          data: {
            name: 'Menu Hub',
            share_image_url: '/img/share.jpg',
            logo_url: null,
            signup_open: true,
            signup_plan_id: null,
            signup_approval: false,
            signup_notify: true,
            turnstile_enabled: false,
            turnstile_site_key: '',
            turnstile_secret_set: false,
            disposable_domains: [],
            upgrade_whatsapp: '',
            plans: [{ id: 1, name: 'Free', slug: 'free' }],
          },
        },
      },
      'GET /super/settings/mail': { status: 404 },
    })
    const screen = await renderWithQuery(<SettingsPage />, { me: admin })

    // Opens on the tab named in the address.
    await expect
      .element(screen.getByRole('tab', { name: 'Email (SMTP)' }))
      .toHaveAttribute('aria-selected', 'true')

    await userEvent.click(screen.getByRole('tab', { name: 'General' }))
    expect(window.location.hash).toBe('#general')
    await userEvent.fill(screen.getByLabelText('Platform name'), 'Menu Hub ET')
    // Only the open tab is shown.
    await expect.element(screen.getByLabelText('Platform name')).toBeVisible()
    await userEvent.click(screen.getByRole('tab', { name: 'Billing' }))
    await expect
      .element(screen.getByLabelText('Platform name'))
      .not.toBeVisible()
    await userEvent.click(screen.getByRole('tab', { name: 'General' }))
    await expect
      .element(screen.getByLabelText('Platform name'))
      .toHaveValue('Menu Hub ET')
    window.history.replaceState(null, '', '#')
  })

  it('shows the draft note and resets the Terms to the default text after confirming', async () => {
    window.history.replaceState(null, '', '#general')
    fake = fakeApi({
      'GET /super/settings': {
        data: {
          data: {
            name: 'Menu',
            terms: 'My own terms',
            privacy: 'My own privacy text',
            about: 'About text',
            contact: 'Contact text',
            cookies: 'Cookie text',
            share_image_url: '/img/share.jpg',
            logo_url: null,
            signup_open: true,
            signup_plan_id: null,
            signup_approval: false,
            signup_notify: true,
            turnstile_enabled: false,
            turnstile_site_key: '',
            turnstile_secret_set: false,
            disposable_domains: [],
            upgrade_whatsapp: '',
            plans: [{ id: 1, name: 'Free', slug: 'free' }],
          },
        },
      },
      'GET /super/settings/mail': { status: 404 },
      'POST /super/settings/legal/terms/reset': {
        data: {
          data: { terms: 'Last updated: 13 November 2026\n\nDefault terms' },
        },
      },
    })
    const screen = await renderWithQuery(<SettingsPage />, { me: admin })

    await expect
      .element(
        screen.getByText('Draft — have a lawyer review before relying on it.')
      )
      .toBeVisible()
    // The long texts start closed (owner, 2026-11-21).
    await userEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    const terms = screen.getByLabelText('Terms of use')
    await expect.element(terms).toHaveValue('My own terms')
    await expect
      .element(screen.getByRole('link', { name: 'View page' }).first())
      .toHaveAttribute('href', '/terms')

    // Cancel keeps the text and sends nothing.
    await userEvent.click(
      screen.getByRole('button', { name: 'Reset to default text' }).first()
    )
    await expect
      .element(screen.getByRole('alertdialog'))
      .toHaveTextContent('Reset the Terms of Service?')
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(fake.calls.some((c) => c.method === 'POST')).toBe(false)

    await userEvent.click(
      screen.getByRole('button', { name: 'Reset to default text' }).first()
    )
    await userEvent.click(
      screen
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Reset to default text' })
    )
    await expect
      .element(terms)
      .toHaveValue('Last updated: 13 November 2026\n\nDefault terms')
    expect(
      fake.calls.filter((c) => c.method === 'POST').map((c) => c.url)
    ).toEqual(['/super/settings/legal/terms/reset'])
    await expect
      .element(screen.getByLabelText('Privacy policy'))
      .toHaveValue('My own privacy text')

    // The footer's About us, Contact us and Cookie info pages (#230).
    await expect
      .element(screen.getByLabelText('About us page'))
      .toHaveValue('About text')
    await expect
      .element(screen.getByLabelText('Cookie info page'))
      .toHaveValue('Cookie text')
    const views = screen.getByRole('link', { name: 'View page' })
    expect(views.elements().map((a) => a.getAttribute('href'))).toEqual([
      '/terms',
      '/privacy',
      '/about',
      '/contact',
      '/cookies',
    ])
  })
})

describe('Super Admin settings: the legal texts open and close', () => {
  const SETTINGS = {
    name: 'Menu',
    support_email: 'help@example.com',
    domain: null,
    default_currency: 'ETB',
    terms: 'Our own terms, three.',
    privacy: 'Privacy text',
    about: 'About text',
    contact: 'Contact text',
    cookies: 'Cookie text',
    legal_updated_at: {
      terms: '2026-11-12T09:30:00+00:00',
      privacy: null,
      about: null,
      contact: null,
      cookies: null,
    },
    share_image_url: '/img/share.jpg',
    logo_url: null,
    signup_open: true,
    signup_plan_id: null,
    signup_approval: false,
    signup_notify: true,
    turnstile_enabled: false,
    turnstile_site_key: '',
    turnstile_secret_set: false,
    disposable_domains: [],
    upgrade_whatsapp: '',
    plans: [{ id: 1, name: 'Free', slug: 'free' }],
  }
  const termsHeader = (screen: Awaited<ReturnType<typeof renderWithQuery>>) =>
    screen.getByRole('button', { name: /^Terms of use/ })

  function render(put?: ReturnType<typeof invalid>) {
    fake = fakeApi({
      'GET /super/settings': { data: { data: SETTINGS } },
      'GET /super/settings/mail': { status: 404 },
      'PUT /super/settings': put ?? { data: { data: {} } },
    })
    return renderWithQuery(<SettingsPage />, { me: admin })
  }

  afterEach(() => {
    fake.restore()
    for (const key of Object.keys(localStorage))
      if (key.startsWith('menu:sections:')) localStorage.removeItem(key)
    window.history.replaceState(null, '', window.location.pathname)
  })

  it('starts closed with when it was saved and how long it is', async () => {
    const screen = await render()
    await expect
      .element(termsHeader(screen))
      .toHaveAttribute('aria-expanded', 'false')
    await expect
      .element(termsHeader(screen))
      .toHaveTextContent('Last updated 12 Nov 2026 · 4 words')
    await expect
      .element(screen.getByRole('button', { name: /^Privacy policy/ }))
      .toHaveTextContent('Default text · 2 words')
    expect(screen.container.querySelector('#st-terms')).toBeNull()

    await userEvent.click(termsHeader(screen))
    await expect
      .element(screen.getByLabelText('Terms of use'))
      .toHaveValue('Our own terms, three.')
  })

  it('opens from a link to it, also when the page loads', async () => {
    window.history.replaceState(null, '', '#legal-terms')
    const screen = await render()
    await expect
      .element(termsHeader(screen))
      .toHaveAttribute('aria-expanded', 'true')
    // The General tab is the one shown.
    await expect.element(screen.getByLabelText('Platform name')).toBeVisible()
  })

  it('saves the other settings without sending the Terms, and sends only a changed text', async () => {
    const screen = await render()
    await userEvent.fill(screen.getByLabelText('Platform name'), 'Menu Hub')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }).first())
    await expect
      .poll(() => fake.calls.filter((c) => c.method === 'PUT').length)
      .toBe(1)
    const first = fake.calls.find((c) => c.method === 'PUT')?.data as Record<
      string,
      unknown
    >
    expect(first).toEqual({
      name: 'Menu Hub',
      support_email: 'help@example.com',
      domain: null,
      default_currency: 'ETB',
    })

    await userEvent.click(termsHeader(screen))
    await userEvent.fill(screen.getByLabelText('Terms of use'), 'New terms')
    await userEvent.click(termsHeader(screen))
    await expect.element(termsHeader(screen)).toHaveTextContent(/Changed/)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }).first())
    await expect
      .poll(() => fake.calls.filter((c) => c.method === 'PUT').length)
      .toBe(2)
    const second = fake.calls.filter((c) => c.method === 'PUT')[1]
      .data as Record<string, unknown>
    expect(second.terms).toBe('New terms')
    expect(
      Object.keys(second).filter((k) =>
        ['privacy', 'about', 'contact', 'cookies'].includes(k)
      )
    ).toEqual([])
    await expect.element(termsHeader(screen)).not.toHaveTextContent(/Changed/)
  })

  it('opens itself on an error and puts the focus in the text', async () => {
    const screen = await render(invalid({ terms: 'The text is too long.' }))
    await userEvent.click(termsHeader(screen))
    await userEvent.fill(screen.getByLabelText('Terms of use'), 'Too long')
    await userEvent.click(termsHeader(screen))
    await expect
      .element(termsHeader(screen))
      .toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }).first())
    await expect
      .element(termsHeader(screen))
      .toHaveAttribute('aria-expanded', 'true')
    await expect
      .element(screen.getByText('The text is too long.'))
      .toBeVisible()
    await expect.poll(() => document.activeElement?.id).toBe('st-terms')
    await expect
      .element(screen.getByLabelText('Terms of use'))
      .toHaveAttribute('aria-invalid', 'true')
  })

  it('has no accessibility problems, closed and open', async () => {
    const screen = await render()
    await expect.element(termsHeader(screen)).toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
    await userEvent.click(screen.getByRole('button', { name: 'Expand all' }))
    await expect
      .element(screen.getByLabelText('Cookie info page'))
      .toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
  })
})
