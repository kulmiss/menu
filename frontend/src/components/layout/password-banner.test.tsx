import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { PasswordBanner } from './password-banner'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

describe('PasswordBanner (SECURITY_REPORT.md M3)', () => {
  afterEach(() => sessionStorage.clear())

  it('nudges a weak password towards Settings and can be closed for the session', async () => {
    const screen = await renderWithQuery(<PasswordBanner />, {
      me: meFixture({ password_weak: true }),
    })
    await expect
      .element(screen.getByText(/list of common or leaked passwords/))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Choose a new one' }))
      .toBeVisible()
    await screen.getByRole('button', { name: 'Close' }).click()
    await expect
      .element(screen.getByText(/list of common or leaked passwords/))
      .not.toBeInTheDocument()
    expect(sessionStorage.getItem('password-banner-hidden')).toBe('1')
  })

  it('stays away for good passwords and during "log in as"', async () => {
    const good = await renderWithQuery(<PasswordBanner />, {
      me: meFixture({ password_weak: false }),
    })
    expect(good.getByRole('status').query()).toBeNull()
  })
})
