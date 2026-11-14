import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useTenantStore } from '@/stores/tenant-store'
import { InsightsPage } from '@/features/insights'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

let fake: ReturnType<typeof fakeApi>

describe('A screen the plan does not include', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('explains what Insights gives and how to upgrade, without calling its API', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<InsightsPage />, {
      me: meFixture({
        tenants: [
          tenantFixture({
            features: { insights: false },
            upgrade: {
              whatsapp: 'https://wa.me/251911000000',
              email: 'sales@menu.et',
            },
          }),
        ],
      }),
    })
    await expect
      .element(screen.getByText('Insights is part of our paid plans'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Upgrade on WhatsApp' }))
      .toHaveAttribute('href', 'https://wa.me/251911000000')
    await expect
      .element(screen.getByRole('link', { name: 'Upgrade by email' }))
      .toHaveAttribute('href', 'mailto:sales@menu.et')
    await expect
      .element(screen.getByRole('link', { name: 'See all plans' }))
      .toHaveAttribute('href', '/#pricing')
    expect(fake.calls.some((c) => c.url.includes('insights'))).toBe(false)
  })
})
