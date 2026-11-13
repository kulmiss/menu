import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { SidebarProvider } from '@/components/ui/sidebar'
import { TeamSwitcher } from './team-switcher'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const two = meFixture({
  tenants: [
    tenantFixture({ id: 1, name: 'Buna & Bread' }),
    tenantFixture({ id: 2, name: 'Sahan Grill' }, 'staff'),
  ],
})

describe('TeamSwitcher', () => {
  beforeEach(() => {
    navigate.mockClear()
    useTenantStore.setState({ currentTenantId: null })
  })

  it('switches restaurant, remembers it and drops the old data', async () => {
    const screen = await renderWithQuery(
      <SidebarProvider>
        <TeamSwitcher />
      </SidebarProvider>,
      { me: two }
    )
    screen.client.setQueryData(['tenant', 1, 'members'], [])

    await userEvent.click(
      screen.getByRole('button', { name: 'Switch restaurant' })
    )
    await userEvent.click(screen.getByRole('menuitem', { name: /Sahan Grill/ }))

    expect(useTenantStore.getState().currentTenantId).toBe(2)
    expect(screen.client.getQueryData(['tenant', 1, 'members'])).toBeUndefined()
    expect(navigate).toHaveBeenCalledWith({ to: '/admin' })
    await expect.element(screen.getByText('Staff')).toBeVisible()
  })

  it('is a plain label with one restaurant', async () => {
    const screen = await renderWithQuery(
      <SidebarProvider>
        <TeamSwitcher />
      </SidebarProvider>,
      { me: meFixture() }
    )

    await expect.element(screen.getByText('Buna & Bread')).toBeVisible()
    expect(
      screen.container.querySelector('[aria-label="Switch restaurant"]')
    ).toBeNull()
  })
})
