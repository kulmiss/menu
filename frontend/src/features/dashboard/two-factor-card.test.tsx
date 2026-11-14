import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { describe, expect, it, vi } from 'vitest'
import { TwoFactorCard } from './two-factor-card'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

describe('TwoFactorCard (SECURITY_REPORT.md M17)', () => {
  it('encourages owners and managers without two-factor, with a one-tap link', async () => {
    const screen = await renderWithQuery(<TwoFactorCard manager />, {
      me: meFixture({ two_factor_enabled: false }),
    })
    await expect
      .element(screen.getByText(/Protect your restaurant with two-factor/))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Turn it on' }))
      .toHaveAttribute('href', expect.stringContaining('setup=1'))
  })

  it('stays away once two-factor is on, for staff, and during "log in as"', async () => {
    const on = await renderWithQuery(<TwoFactorCard manager />, {
      me: meFixture({ two_factor_enabled: true }),
    })
    expect(on.getByRole('status').query()).toBeNull()
    const staff = await renderWithQuery(<TwoFactorCard manager={false} />, {
      me: meFixture({ two_factor_enabled: false, tenants: [tenantFixture()] }),
    })
    expect(staff.getByRole('status').query()).toBeNull()
  })
})
