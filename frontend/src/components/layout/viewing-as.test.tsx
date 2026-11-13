import { renderWithQuery } from '@/test-utils/render'
import { describe, expect, it, vi } from 'vitest'
import { type Me } from '@/features/auth/api'
import { AdminHeader } from './admin-header'

// The header's other parts need app providers; they are not under test here.
vi.mock('@/components/layout/header', () => ({
  Header: ({ children }: { children: React.ReactNode }) => (
    <header>{children}</header>
  ),
}))
vi.mock('@/components/search', () => ({ Search: () => null }))
vi.mock('@/components/profile-dropdown', () => ({
  ProfileDropdown: () => null,
}))
vi.mock('@/components/layout/lazy-config-drawer', () => ({
  LazyConfigDrawer: () => null,
}))
vi.mock('@/components/lazy-language-switch', () => ({
  LazyLanguageSwitch: () => null,
}))
vi.mock('@/components/theme-switch', () => ({ ThemeSwitch: () => null }))

const me = (impersonation: Me['impersonation']): Me => ({
  id: 1,
  name: 'Sara',
  email: 'owner@example.com',
  locale: 'en',
  email_verified: true,
  two_factor_enabled: false,
  is_super_admin: false,
  impersonation,
  tenants: [],
})

describe('Viewing as (log in as)', () => {
  it('shows a quiet badge with the restaurant and the way back', async () => {
    const screen = await renderWithQuery(<AdminHeader />, {
      me: me({
        tenant_id: 5,
        tenant_name: 'Buna & Bread',
        user_name: 'Sara',
        ends_at: '2026-11-11T13:00:00+00:00',
      }),
    })

    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('Viewing as Buna & Bread')
    await expect
      .element(screen.getByRole('button', { name: 'Back to platform' }))
      .toBeVisible()
    // The old red banner text is gone.
    expect(document.body.textContent).not.toContain('You are logged in as')
  })

  it('shows nothing in a normal session', async () => {
    const screen = await renderWithQuery(<AdminHeader />, { me: me(null) })

    await expect.element(screen.getByRole('banner')).toBeInTheDocument()
    expect(screen.container.querySelector('[role="status"]')).toBeNull()
    expect(document.body.textContent).not.toContain('Viewing as')
  })
})
