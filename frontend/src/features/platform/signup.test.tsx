import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
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
      'GET /platform/restaurants': { data: { data: [pending] } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/restaurants/9': detail,
      'POST /platform/restaurants/9/approve': { data: { data: pending } },
    })
    const screen = await renderWithQuery(
      <RestaurantsPage onState={() => {}} />,
      { me: admin }
    )
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
      .toBe('/platform/restaurants/9/approve')
  })

  it('rejects a sign-up with a reason', async () => {
    fake = fakeApi({
      'GET /platform/restaurants': { data: { data: [pending] } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/restaurants/9': detail,
      'POST /platform/restaurants/9/reject': {
        data: { data: { removed: true } },
      },
    })
    const screen = await renderWithQuery(
      <RestaurantsPage onState={() => {}} />,
      { me: admin }
    )
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
      '/platform/restaurants/9/reject'
    )
  })

  it('saves the sign-up settings without sending the stored secret', async () => {
    fake = fakeApi({
      'GET /platform/settings': {
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
      'GET /platform/settings/mail': { status: 404 },
      'PUT /platform/settings': { data: { data: {} } },
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
  afterEach(() => fake.restore())

  it('saves texts, colour, sections, real testimonials and footer links', async () => {
    fake = fakeApi({
      'GET /platform/settings': {
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
            upgrade_whatsapp: '',
            plans: [{ id: 1, name: 'Free', slug: 'free' }],
            brand_color: '#c2410c',
            landing_headline: '',
            landing_subline: '',
            landing_sections: { how: true, features: true, speed: true },
            landing_testimonials: [],
            socials: { telegram: '' },
          },
        },
      },
      'GET /platform/settings/mail': { status: 404 },
      'PUT /platform/settings': { data: { data: {} } },
    })
    const screen = await renderWithQuery(<SettingsPage />, { me: admin })

    await userEvent.click(screen.getByRole('tab', { name: 'Landing page' }))
    await userEvent.fill(
      screen.getByLabelText('Headline'),
      'QR menus for Addis restaurants'
    )
    await userEvent.fill(
      screen.getByLabelText('WhatsApp number', { exact: true }),
      '+251 911 000 000'
    )
    await userEvent.click(screen.getByRole('checkbox', { name: 'Speed' }))
    await userEvent.click(
      screen.getByRole('button', { name: 'Add a testimonial' })
    )
    await userEvent.fill(
      screen.getByLabelText('Quote'),
      'Guests find the menu on their own now.'
    )
    await userEvent.fill(screen.getByLabelText('Name', { exact: true }), 'Hana')
    await userEvent.fill(
      screen.getByLabelText('Telegram'),
      'https://t.me/menu_et'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        landing_headline: 'QR menus for Addis restaurants',
        upgrade_whatsapp: '+251 911 000 000',
        brand_color: '#c2410c',
        landing_sections: { speed: false, pricing: true, faq: true },
        landing_testimonials: [
          {
            quote: 'Guests find the menu on their own now.',
            name: 'Hana',
            role: '',
          },
        ],
        socials: { telegram: 'https://t.me/menu_et', facebook: '' },
      })
  })

  it('splits settings into tabs, keeps unsaved edits and remembers the tab in the address', async () => {
    window.history.replaceState(null, '', '#email')
    fake = fakeApi({
      'GET /platform/settings': {
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
      'GET /platform/settings/mail': { status: 404 },
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
      'GET /platform/settings': {
        data: {
          data: {
            name: 'Menu',
            terms: 'My own terms',
            privacy: 'My own privacy text',
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
      'GET /platform/settings/mail': { status: 404 },
      'POST /platform/settings/legal/terms/reset': {
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
    ).toEqual(['/platform/settings/legal/terms/reset'])
    await expect
      .element(screen.getByLabelText('Privacy policy'))
      .toHaveValue('My own privacy text')
  })
})
