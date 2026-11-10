import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { PromotionsPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

let fake: ReturnType<typeof fakeApi>

describe('PromotionsPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('creates a dated announcement', async () => {
    fake = fakeApi({
      'GET /t/1/promotions': { data: { data: [] } },
      'POST /t/1/promotions': { status: 201, data: { data: {} } },
      'GET /t/1/branches': { data: { data: [] } },
      'GET /t/1/menus': { data: { data: [] } },
    })
    const screen = await renderWithQuery(<PromotionsPage />, {
      me: meFixture({ tenants: [tenantFixture()] }),
    })
    await expect.element(screen.getByText(/No promotions yet/)).toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'New promotion' }))
    await userEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    await userEvent.click(screen.getByRole('option', { name: 'Announcement' }))
    await userEvent.type(screen.getByLabelText('Title'), 'Live music tonight')
    await userEvent.fill(screen.getByLabelText('Starts'), '2026-11-14T19:00')
    await userEvent.fill(screen.getByLabelText('Ends'), '2026-11-14T23:00')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toMatchObject({
        type: 'announcement',
        title: { en: 'Live music tonight' },
        item_id: null,
        media_id: null,
        starts_at: '2026-11-14 19:00',
        ends_at: '2026-11-14 23:00',
        is_active: true,
      })
  })
})
