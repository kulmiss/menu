import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { Dashboard } from '@/features/dashboard'
import { InsightsPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const day = (date: string, views: number, scans: number) => ({
  date,
  views,
  scans,
})
let fake: ReturnType<typeof fakeApi>

describe('InsightsPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('shows totals, top dishes and scans per code, and changes the period', async () => {
    const data = (days: number) => ({
      data: {
        data: {
          days: Array.from({ length: days }, (_, i) =>
            day(`2026-11-${String(i + 1).padStart(2, '0')}`, i, 0)
          ),
          totals: { views: 120, scans: 45, item_views: 30 },
          top_items: [{ id: 1, name: 'Shiro', count: 12 }],
          scans: [
            {
              qr_id: 9,
              code: 'K7PM3XQ9',
              label: 'Table 7',
              deleted: false,
              count: 20,
            },
          ],
        },
      },
    })
    fake = fakeApi({
      'GET /t/1/branches': { data: { data: [] } },
      'GET /t/1/insights?days=30': data(30),
      'GET /t/1/insights?days=7': data(7),
    })
    const screen = await renderWithQuery(<InsightsPage />, {
      me: meFixture({ tenants: [tenantFixture()] }),
    })

    await expect.element(screen.getByText('120')).toBeVisible()
    await expect.element(screen.getByText('Shiro')).toBeVisible()
    await expect.element(screen.getByText('Table 7')).toBeVisible()
    await expect
      .element(screen.getByRole('img', { name: /Bar chart/ }))
      .toBeVisible()

    await userEvent.click(screen.getByRole('combobox', { name: 'Period' }))
    await userEvent.click(screen.getByRole('option', { name: 'Last 7 days' }))
    await expect
      .poll(() => fake.calls.some((c) => c.url === '/t/1/insights?days=7'))
      .toBe(true)
  })
})

describe('Dashboard', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('shows today and puts a sold-out dish back on sale', async () => {
    fake = fakeApi({
      'GET /t/1/dashboard': {
        data: {
          data: {
            today: { views: 31, scans: 9 },
            yesterday: { views: 28, scans: 7 },
            sold_out: [{ id: 5, name: { en: 'Doro wat' } }],
          },
        },
      },
      'PATCH /t/1/items/5/sold-out': { data: { data: {} } },
      'GET /t/1/widgets/trend': {
        data: {
          data: {
            days: [],
            today: { date: '2026-11-11', views: 31, scans: 9 },
            yesterday: { date: '2026-11-10', views: 28, scans: 7 },
          },
        },
      },
    })
    const screen = await renderWithQuery(<Dashboard />, {
      me: meFixture({ tenants: [tenantFixture()] }),
    })

    await expect.element(screen.getByText('31')).toBeVisible()
    await expect.element(screen.getByText('Doro wat')).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Available again' })
    )
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PATCH')?.data)
      .toEqual({ is_sold_out: false })
  })
})
