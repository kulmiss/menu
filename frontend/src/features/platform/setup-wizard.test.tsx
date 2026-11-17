import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useSetupWizard } from '@/stores/setup-wizard-store'
import SetupChecklist from './setup-checklist'
import SetupWizard from './setup-wizard'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const state = (over: Record<string, unknown> = {}) => ({
  done: false,
  done_at: null,
  card_hidden: false,
  checks: [
    { key: 'two_factor', step: 1, done: true, optional: false },
    { key: 'backup_key', step: 1, done: false, optional: false },
    { key: 'contact', step: 2, done: false, optional: false },
    { key: 'firebase', step: 4, done: false, optional: true },
    { key: 'demo', step: 5, done: false, optional: false },
  ],
  missing: 4,
  demo_restaurants: 3,
  production: false,
  ...over,
})
const plans = [
  {
    id: 1,
    name: { en: 'Free' },
    slug: 'free',
    price_minor: 0,
    price_year_minor: null,
    currency: 'ETB',
    interval: 'month',
    trial_days: 0,
    replaced_by_plan_id: null,
    limits: { photos: 100, branches: 1 },
    features: {},
    is_public: true,
    is_active: true,
    restaurants: 2,
  },
  {
    id: 2,
    name: { en: 'Pro' },
    slug: 'pro',
    price_minor: 99000,
    price_year_minor: null,
    currency: 'ETB',
    interval: 'month',
    trial_days: 14,
    replaced_by_plan_id: null,
    limits: { photos: 2000, branches: 3 },
    features: {},
    is_public: true,
    is_active: true,
    restaurants: 5,
  },
]
let fake: ReturnType<typeof fakeApi>

describe('Super Admin setup wizard', () => {
  afterEach(() => fake.restore())

  it('goes through 5 steps, saves plans, deletes the demo data and finishes', async () => {
    fake = fakeApi({
      'GET /super/setup': { data: { data: state() } },
      'PUT /super/setup': { data: { data: state({ done: true }) } },
      'GET /super/settings': {
        data: {
          data: {
            name: 'Menu',
            support_email: null,
            upgrade_whatsapp: '',
            brand_color: '#c2410c',
            signup_open: true,
            signup_approval: false,
          },
        },
      },
      'PUT /super/settings': { data: { data: {} } },
      'GET /super/plans': { data: { data: plans } },
      'PATCH /super/plans/2': { data: { data: plans[1] } },
      'GET /super/settings/features': { data: { data: [] } },
      'POST /super/setup/demo/delete': {
        data: { data: state({ demo_restaurants: 0, deleted: 3 }) },
      },
      'GET /me': { data: { data: meFixture({ is_super_admin: true }) } },
    })
    const screen = await renderWithQuery(<SetupWizard />, {
      me: meFixture({ is_super_admin: true }),
    })
    await expect.element(screen.getByText('Step 1 of 5')).toBeVisible()
    await expect
      .element(screen.getByText('Backups are not encrypted yet.'))
      .toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Next' }))
    await userEvent.fill(
      screen.getByLabelText('Support e-mail'),
      'hello@menu.example'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(
        () =>
          fake.calls.find(
            (c) => c.method === 'PUT' && c.url === '/super/settings'
          )?.data
      )
      .toMatchObject({ support_email: 'hello@menu.example' })

    await userEvent.click(screen.getByRole('button', { name: 'Next' }))
    await userEvent.fill(screen.getByLabelText('Price of Pro (ETB)'), '1200')
    await userEvent.click(screen.getByRole('button', { name: 'Save plans' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PATCH')?.data)
      .toEqual({ price_minor: 120000, limits: { photos: 2000, branches: 3 } })
    expect(
      fake.calls.some(
        (c) => c.url === '/super/plans/1' && c.method === 'PATCH'
      )
    ).toBe(false)
    expect(
      fake.calls.find((c) => c.method === 'PUT' && c.url === '/super/setup')
        ?.data
    ).toEqual({ checked: 'plans' })

    await userEvent.click(screen.getByRole('button', { name: 'Next' }))
    await expect
      .element(
        screen.getByText('Push notifications are not set up (optional).')
      )
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Next' }))
    await expect
      .element(screen.getByText('3 demo restaurants found.'))
      .toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Delete the demo restaurants' })
    )
    await userEvent.click(
      screen.getByRole('alertdialog').getByRole('button', { name: 'Continue' })
    )
    await expect
      .poll(() =>
        fake.calls.some((c) => c.url === '/super/setup/demo/delete')
      )
      .toBe(true)

    await userEvent.click(
      screen.getByRole('button', { name: 'Finish', exact: true })
    )
    await expect
      .poll(
        () =>
          fake.calls
            .filter((c) => c.method === 'PUT' && c.url === '/super/setup')
            .slice(-1)[0]?.data
      )
      .toEqual({ done: true })
    expect(useSetupWizard.getState().open).toBe(false)
  })

  it('shows a dashboard checklist of what is not done, which opens the wizard', async () => {
    fake = fakeApi({
      'GET /super/setup': { data: { data: state({ done: true }) } },
      'PUT /super/setup': {
        data: { data: state({ done: true, card_hidden: true }) },
      },
    })
    const screen = await renderWithQuery(<SetupChecklist />)
    await expect
      .element(screen.getByText('Finish setting up the platform'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: /Encrypted backups/ }))
      .toHaveAttribute('href', '/platform/backups')
    await userEvent.click(
      screen.getByRole('button', { name: 'Open the setup wizard' })
    )
    expect(useSetupWizard.getState().open).toBe(true)
    await userEvent.click(
      screen.getByRole('button', { name: 'Hide this list' })
    )
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({ card_hidden: true })
  })
})
