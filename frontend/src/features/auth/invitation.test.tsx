import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { InvitationPage } from './invitation'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const preview = {
  data: {
    data: {
      restaurant: 'Sahan Grill',
      email: 'hana@example.com',
      role: 'staff',
      expires_at: '2026-12-01T00:00:00Z',
    },
  },
}

let fake: ReturnType<typeof fakeApi>

describe('InvitationPage', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake.restore())

  it('offers to create an account or sign in when signed out', async () => {
    fake = fakeApi({
      'GET /invitations/tok': preview,
      'GET /me': { status: 401 },
    })
    const screen = await renderWithQuery(<InvitationPage token='tok' />)

    await expect.element(screen.getByText('Join Sahan Grill')).toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Create an account' }))
      .toHaveAttribute('href', '/auth/sign-up?invite=tok')
    await expect
      .element(screen.getByRole('link', { name: 'Sign in' }))
      .toHaveAttribute('href', '/auth/sign-in?redirect=%2Fauth%2Finvite%2Ftok')
  })

  it('accepts for the invited account and opens that restaurant', async () => {
    const joined = tenantFixture({ id: 7, name: 'Sahan Grill' }, 'staff')
    fake = fakeApi({
      'GET /invitations/tok': preview,
      'GET /me': { data: { data: meFixture({ email: 'hana@example.com' }) } },
      'POST /invitations/tok/accept': { data: { data: joined } },
    })
    const screen = await renderWithQuery(<InvitationPage token='tok' />)

    await userEvent.click(
      screen.getByRole('button', { name: 'Accept invitation' })
    )

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({ to: '/admin', replace: true })
    )
    expect(useTenantStore.getState().currentTenantId).toBe(7)
  })

  it('warns when signed in with another account', async () => {
    fake = fakeApi({
      'GET /invitations/tok': preview,
      'GET /me': { data: { data: meFixture() } },
    })
    const screen = await renderWithQuery(<InvitationPage token='tok' />)

    await expect
      .element(
        screen.getByText(
          /sent to hana@example.com, but you are signed in as selam@example.com/
        )
      )
      .toBeVisible()
    expect(screen.container.textContent).not.toContain('Accept invitation')
  })

  it('says so when the invitation is gone', async () => {
    fake = fakeApi({ 'GET /me': { status: 401 } })
    const screen = await renderWithQuery(<InvitationPage token='expired' />)

    await expect.element(screen.getByText(/no longer valid/)).toBeVisible()
  })
})
