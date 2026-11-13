import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { toast } from '@/lib/toast'
import { PlatformDashboard } from './dashboard'
import { RestaurantsPage } from './restaurants'
import { PlansPage } from './screens'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
const restaurant = {
  id: 7,
  name: 'Sahan Grill',
  slug: 'sahan-grill',
  state: 'grace',
  status: 'active',
  suspended_reason: null,
  plan: { id: 2, name: 'Basic' },
  trial_ends_at: null,
  paid_until: '2026-11-01',
  stage_ends_at: '2026-11-08',
  owner: { name: 'Ayaan', email: 'ayaan@example.com' },
  timezone: 'Africa/Mogadishu',
  currency_code: 'SOS',
  created_at: '2026-10-01',
  deleted_at: null,
  url: 'http://localhost/sahan-grill',
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin screens', () => {
  afterEach(() => fake.restore())

  it('shows the platform at a glance', async () => {
    fake = fakeApi({
      'GET /platform/dashboard': {
        data: {
          data: {
            states: {
              trial: 3,
              active: 5,
              grace: 1,
              read_only: 0,
              unavailable: 0,
              suspended: 1,
            },
            restaurants: 10,
            signups_30d: 4,
            views_30d: 1200,
            scans_30d: 300,
            per_plan: { Basic: 6 },
            expiring: [
              {
                id: 7,
                name: 'Sahan Grill',
                state: 'active',
                ends_at: '2026-11-15',
              },
            ],
            revenue_30d: { ETB: 450000 },
          },
        },
      },
    })
    const screen = await renderWithQuery(<PlatformDashboard />, { me: admin })
    await expect
      .element(screen.getByRole('link', { name: /Trial\s*3/ }))
      .toHaveAttribute('href', '/platform/$section?state=trial')
    // Insights and the to-do widgets load after the page; with no answers they show their error state.
    await expect.element(screen.getByText('Overview')).toBeVisible()
    await expect.element(screen.getByText('System health')).toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: 'Last 30 days' }))
      .toHaveAttribute('aria-pressed', 'true')
  })

  it('records a payment for a restaurant', async () => {
    fake = fakeApi({
      'GET /platform/restaurants': { data: { data: [restaurant] } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/restaurants/7': {
        data: {
          data: {
            ...restaurant,
            members: [],
            features: {
              branches: true,
              promotions: true,
              insights: true,
              import_export: true,
              remove_branding: false,
            },
            overrides: {},
            payments: [],
            counts: { menus: 1, items: 20, branches: 1, views_30d: 80 },
          },
        },
      },
      'POST /platform/restaurants/7/payments': {
        status: 201,
        data: { data: {} },
      },
    })
    const screen = await renderWithQuery(<RestaurantsPage />, { me: admin })
    await userEvent.click(screen.getByRole('button', { name: 'Sahan Grill' }))
    await userEvent.type(screen.getByLabelText('Amount (SOS)'), '1500')
    await userEvent.clear(screen.getByLabelText('Months'))
    await userEvent.type(screen.getByLabelText('Months'), '3')
    await userEvent.click(screen.getByRole('button', { name: 'Record' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toMatchObject({
        amount_minor: 150000,
        method: 'mobile_money',
        months: 3,
      })
  })

  it('resends the set-password e-mail to the owner', async () => {
    fake = fakeApi({
      'GET /platform/restaurants': { data: { data: [restaurant] } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/restaurants/7': {
        data: {
          data: {
            ...restaurant,
            members: [
              {
                id: 3,
                name: 'Ayaan',
                email: 'ayaan@example.com',
                role: 'owner',
              },
            ],
            features: {},
            overrides: {},
            payments: [],
            counts: { menus: 1, items: 20, branches: 1, views_30d: 80 },
          },
        },
      },
      'POST /platform/restaurants/7/resend-welcome': {
        data: {
          data: { sent_to: 'ayaan@example.com', kind: 'reset' },
          message:
            'The owner has signed in before, so ayaan@example.com got a "reset your password" e-mail.',
        },
      },
    })
    const toastSuccess = vi.spyOn(toast, 'success')
    const screen = await renderWithQuery(<RestaurantsPage />, { me: admin })
    await userEvent.click(screen.getByRole('button', { name: 'Sahan Grill' }))
    await userEvent.click(
      screen.getByRole('button', { name: 'E-mail a password link' })
    )
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.url)
      .toBe('/platform/restaurants/7/resend-welcome')
    // The toast says which e-mail went out.
    await expect
      .poll(() => toastSuccess.mock.calls.at(-1)?.[0])
      .toMatch(/got a "reset your password" e-mail/)
  })

  it('logs in as the owner in one click, without a reason', async () => {
    fake = fakeApi({
      'GET /platform/restaurants': { data: { data: [restaurant] } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/restaurants/7': {
        data: {
          data: {
            ...restaurant,
            members: [
              {
                id: 3,
                name: 'Ayaan',
                email: 'ayaan@example.com',
                role: 'owner',
              },
            ],
            features: {},
            overrides: {},
            payments: [],
            counts: { menus: 1, items: 20, branches: 1, views_30d: 80 },
          },
        },
      },
      // Answered with an error so the test page doesn't navigate to /admin.
      'POST /platform/restaurants/7/impersonate': {
        status: 422,
        data: { message: 'No owner' },
      },
    })
    const screen = await renderWithQuery(<RestaurantsPage />, { me: admin })
    await userEvent.click(screen.getByRole('button', { name: 'Sahan Grill' }))
    await userEvent.click(
      screen.getByRole('button', { name: 'Log in as owner' })
    )

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.url)
      .toBe('/platform/restaurants/7/impersonate')
    const call = fake.calls.find((c) => c.method === 'POST')
    expect(call?.url).toBe('/platform/restaurants/7/impersonate')
    expect(call?.data ?? {}).toEqual({})
    expect(document.querySelector('#reason')).toBeNull()
  })

  it('creates a plan with limits and features', async () => {
    fake = fakeApi({
      'GET /platform/plans': { data: { data: [] } },
      'POST /platform/plans': { status: 201, data: { data: { id: 3 } } },
    })
    const screen = await renderWithQuery(<PlansPage />, { me: admin })
    await userEvent.click(screen.getByRole('button', { name: 'New plan' }))
    await userEvent.type(screen.getByLabelText('Name', { exact: true }), 'Pro')
    await userEvent.type(screen.getByLabelText('Code'), 'pro')
    await userEvent.type(screen.getByLabelText('Price', { exact: true }), '900')
    await userEvent.type(
      screen.getByLabelText('Branches', { exact: true }),
      '5'
    )
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Menu without our name' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toMatchObject({
        name: { en: 'Pro' },
        slug: 'pro',
        price_minor: 90000,
        limits: { branches: 5, menus: null },
        features: { remove_branding: true, promotions: true },
      })
  })
})
