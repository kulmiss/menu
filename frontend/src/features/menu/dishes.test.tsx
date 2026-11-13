import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { page, userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { DishesPage } from './dishes'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

let fake: ReturnType<typeof fakeApi>
const dish = (id: number, name: string, sold = false) => ({
  id,
  name: { en: name },
  category: { id: 2, name: { en: 'Coffee' } },
  menu: { id: 1, name: { en: 'All day' } },
  price_minor: 12000,
  from_minor: null,
  sizes: 0,
  is_sold_out: sold,
  is_visible: true,
  photo: null,
  updated_at: '2026-11-13T10:00:00Z',
})

describe('All dishes', () => {
  beforeEach(async () => {
    useTenantStore.setState({ currentTenantId: 1 })
    window.history.replaceState(null, '', '/admin/dishes')
    await page.viewport(1280, 900)
  })
  afterEach(() => fake.restore())

  it('lists dishes from the server and marks the selected ones sold out', async () => {
    fake = fakeApi({
      'GET /t/1/menus': {
        data: { data: [{ id: 1, name: { en: 'All day' } }] },
      },
      'GET /t/1/items': {
        data: {
          data: [dish(7, 'Macchiato'), dish(8, 'Espresso', true)],
          meta: { total: 2, page: 1, per_page: 25, pages: 1 },
        },
      },
      'POST /t/1/items/bulk': { status: 204 },
    })
    const screen = await renderWithQuery(<DishesPage />, {
      me: meFixture({ tenants: [tenantFixture()] }),
    })
    await expect.element(screen.getByText('Macchiato')).toBeVisible()
    await expect.element(screen.getByText('Br 120').first()).toBeVisible()
    await expect.element(screen.getByText('Sold out')).toBeVisible()
    expect(fake.calls.find((c) => c.url.startsWith('/t/1/items?'))?.url).toBe(
      '/t/1/items?page=1&per=25'
    )

    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Select all rows on this page' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Sold out' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toEqual({ ids: [7, 8], action: 'sold_out' })
  })
})
