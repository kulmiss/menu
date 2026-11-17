import '@/styles/index.css'
import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { page } from 'vitest/browser'
import { PlatformLayout } from './layout'

vi.mock('@tanstack/react-router', async (orig) => ({
  ...(await import('@/test-utils/router-mock')).routerMock(await orig()),
  useLocation: () => '/platform/restaurants',
  Outlet: () => null,
}))

let fake: ReturnType<typeof fakeApi>
afterEach(() => fake.restore())

describe('Super Admin bar', () => {
  it('shows every section on a desktop screen, none hidden off the edge', async () => {
    await page.viewport(1280, 800)
    fake = fakeApi({ 'GET /super/setup': { data: { data: { done: true } } } })
    const screen = await renderWithQuery(<PlatformLayout />, {
      me: meFixture({
        is_super_admin: true,
        two_factor_enabled: true,
        tenants: [],
        onboarding: { setup_needed: false, web_tour: false, app_tour: false },
      }),
    })
    const nav = screen.getByRole('navigation', { name: 'Platform' })
    await expect.element(nav).toBeVisible()
    const links = nav.element().querySelectorAll('a')
    expect(links.length).toBeGreaterThan(10)
    // Wider screens wrap the sections onto two lines instead of scrolling them off the edge
    // (with the app's own font, "Guide" and "Help articles" were hidden at 1280 px).
    expect(getComputedStyle(nav.element()).flexWrap).toBe('wrap')
    for (const a of links) {
      expect(
        a.getBoundingClientRect().right,
        a.textContent ?? ''
      ).toBeLessThanOrEqual(window.innerWidth)
    }
  })
})
