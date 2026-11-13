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
      screen.getByLabelText('Reason'),
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
    await userEvent.fill(
      screen.getByLabelText('WhatsApp number for upgrades'),
      '+251 911 000 000'
    )
    await expect
      .element(screen.getByLabelText('Turnstile secret key'))
      .toHaveAttribute('placeholder', 'Saved (leave empty to keep it)')
    const save = screen.getByRole('button', { name: 'Save' }).nth(1)
    await userEvent.click(save)

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        signup_open: false,
        signup_approval: true,
        signup_plan_id: null,
        turnstile_secret: '',
        disposable_domains: ['mailinator.com', 'yopmail.com'],
        upgrade_whatsapp: '+251 911 000 000',
      })
  })
})
