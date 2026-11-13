import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import DashboardWidgets from './widgets'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const days = ['05', '06', '07', '08', '09', '10', '11'].map((d, i) => ({
  date: `2026-11-${d}`,
  views: i * 3,
  scans: i,
}))
const all = {
  'GET /t/1/widgets/setup': {
    data: {
      data: {
        steps: [
          { key: 'logo', done: true, link: '/admin/appearance' },
          { key: 'qr', done: false, link: '/admin/qr-codes' },
        ],
        percent: 50,
        complete: false,
        hidden: false,
      },
    },
  },
  'GET /t/1/widgets/trend': {
    data: { data: { days, today: days[6], yesterday: days[5] } },
  },
  'GET /t/1/widgets/plan': {
    data: {
      data: {
        plan: { en: 'Basic' },
        trial: false,
        days_left: 12,
        usage: [
          { key: 'items', used: 9, limit: 10 },
          { key: 'branches', used: 1, limit: null },
        ],
        next: {
          name: { en: 'Plus' },
          adds: ['All 4 menu styles', 'Insights and monthly report'],
        },
        upgrade: { whatsapp: 'https://wa.me/251911000000', email: null },
      },
    },
  },
  'GET /t/1/widgets/orders': {
    data: {
      data: {
        enabled: true,
        currency: 'ETB',
        periods: {
          7: {
            top: [{ id: 3, name: { en: 'Shiro' }, added: 6 }],
            lists_shown: 3,
            average_minor: 42000,
          },
          30: { top: [], lists_shown: 0, average_minor: null },
        },
      },
    },
  },
  'GET /t/1/widgets/missing-photos': {
    data: {
      data: {
        enabled: true,
        total: 1,
        items: [{ id: 8, name: { en: 'Tibs' }, views: 40 }],
      },
    },
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Dashboard widgets', () => {
  afterEach(() => fake.restore())

  it('shows every widget for owners and managers', async () => {
    fake = fakeApi({
      ...all,
      'POST /t/1/widgets/setup/hide': { data: { data: { hidden: true } } },
    })
    const screen = await renderWithQuery(
      <DashboardWidgets tenantId={1} locale='en' manager />
    )

    await expect
      .element(screen.getByRole('link', { name: /Download your QR codes/ }))
      .toHaveAttribute('href', '/admin/qr-codes')
    await expect
      .element(screen.getByRole('progressbar', { name: '50% set up' }))
      .toHaveAttribute('aria-valuenow', '50')
    await expect
      .element(
        screen.getByRole('img', { name: /Menu views, last 7 days: .* 18$/ })
      )
      .toBeVisible()
    await expect.element(screen.getByText('Basic')).toBeVisible()
    await expect.element(screen.getByText('12 days left')).toBeVisible()
    await expect.element(screen.getByText('9 / 10')).toBeVisible()
    await expect
      .element(screen.getByText(/Almost at your plan's limit/))
      .toBeVisible()
    await expect.element(screen.getByText('Upgrade to Plus')).toBeVisible()
    await expect
      .element(screen.getByText('Insights and monthly report'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Upgrade on WhatsApp' }))
      .toHaveAttribute('href', 'https://wa.me/251911000000')
    await expect
      .element(screen.getByText(/Almost at your plan's limit/))
      .toBeVisible()
    await expect
      .element(screen.getByText('Average list Br 420 (3 shown to waiters)'))
      .toBeVisible()
    await expect.element(screen.getByText('No data yet')).toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Add photo' }))
      .toBeVisible()

    await userEvent.click(
      screen.getByRole('button', { name: 'Hide this list' })
    )
    await expect
      .poll(() => fake.calls.some((c) => c.method === 'POST'))
      .toBe(true)
  })

  it('keeps staff to the visit trend', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(
      <DashboardWidgets tenantId={1} locale='en' manager={false} />
    )

    await expect.element(screen.getByText('Views and scans')).toBeVisible()
    expect(fake.calls.map((c) => c.url)).toEqual(['/t/1/widgets/trend'])
    expect(screen.container.textContent).not.toContain('Plan and usage')
  })

  it('lets one widget fail without breaking the others', async () => {
    const { 'GET /t/1/widgets/plan': _plan, ...rest } = all
    fake = fakeApi({
      ...rest,
      'GET /t/1/widgets/plan': { status: 500, data: {} },
    })
    const screen = await renderWithQuery(
      <DashboardWidgets tenantId={1} locale='en' manager />
    )

    await expect
      .element(screen.getByText('This part could not be loaded.'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: 'Try again' }))
      .toBeVisible()
    await expect.element(screen.getByText('Views and scans')).toBeVisible()
    await expect.element(screen.getByText('Shiro')).toBeVisible()
  })
})

describe('Welcome after confirming the e-mail', () => {
  afterEach(() => {
    fake.restore()
    window.history.replaceState(null, '', '/')
  })

  it('shows the next setup steps when opened from the confirmation link', async () => {
    fake = fakeApi(all)
    window.history.replaceState(null, '', '/?welcome=1')
    const screen = await renderWithQuery(
      <DashboardWidgets tenantId={1} locale='en' manager />
    )
    await expect
      .element(screen.getByText('Welcome! Your email is confirmed'))
      .toBeVisible()
    await expect
      .element(
        screen.getByRole('link', { name: /Download your QR codes/ }).first()
      )
      .toHaveAttribute('href', '/admin/qr-codes')
  })

  it('is not shown on a normal visit', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(
      <DashboardWidgets tenantId={1} locale='en' manager />
    )
    await expect.element(screen.getByText('Finish setting up')).toBeVisible()
    expect(screen.container.textContent).not.toContain('Welcome!')
  })
})
