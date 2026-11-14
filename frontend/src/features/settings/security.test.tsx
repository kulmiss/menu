import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SecuritySettings } from './security'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('Settings → two-factor recovery codes (SECURITY_REPORT.md M5)', () => {
  afterEach(() => fake.restore())

  it('makes a new set (after the password) and shows it once', async () => {
    fake = fakeApi({
      'POST /auth/user/confirm-password': { status: 201 },
      'POST /auth/user/two-factor-recovery-codes': { data: {} },
      'GET /auth/user/two-factor-recovery-codes': {
        data: ['aaaaaaaaaa-bbbbbbbbbb', 'cccccccccc-dddddddddd'],
      },
    })
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture({ two_factor_enabled: true }),
    })

    await screen.getByRole('button', { name: 'New recovery codes' }).click()
    await screen
      .getByRole('dialog')
      .getByLabelText('Password')
      .fill('correct-horse-battery')
    await screen.getByRole('button', { name: 'Continue' }).click()

    await expect
      .element(screen.getByText('aaaaaaaaaa-bbbbbbbbbb'))
      .toBeVisible()
    const urls = fake
      .apiCalls()
      .map((c) => `${c.method.toUpperCase()} ${c.url}`)
    expect(urls).toContain('POST /auth/user/two-factor-recovery-codes')
  })
})

describe('Settings → one-tap two-factor setup (SECURITY_REPORT.md M17)', () => {
  afterEach(() => fake.restore())

  it('asks for the password at once and then shows the QR code', async () => {
    fake = fakeApi({
      'POST /auth/user/confirm-password': { status: 201 },
      'POST /auth/user/two-factor-authentication': { data: {} },
      'GET /auth/user/two-factor-qr-code': { data: { svg: '<svg></svg>' } },
      'GET /auth/user/two-factor-secret-key': {
        data: { secretKey: 'JBSWY3DPEHPK3PXP' },
      },
    })
    const screen = await renderWithQuery(<SecuritySettings startSetup />, {
      me: meFixture({ two_factor_enabled: false }),
    })

    await screen
      .getByRole('dialog')
      .getByLabelText('Password')
      .fill('correct-horse-battery')
    await screen.getByRole('button', { name: 'Continue' }).click()
    await expect.element(screen.getByText(/JBSWY3DPEHPK3PXP/)).toBeVisible()
  })
})

describe('Settings → other browsers (SECURITY_REPORT.md L7)', () => {
  afterEach(() => fake.restore())

  it('signs out of all other browsers after the password', async () => {
    fake = fakeApi({
      'POST /me/sessions/logout-others': { data: { message: 'ok' } },
    })
    const screen = await renderWithQuery(<SecuritySettings />, {
      me: meFixture(),
    })

    await screen
      .getByRole('button', { name: 'Sign out of all other browsers' })
      .click()
    await screen
      .getByRole('dialog')
      .getByLabelText('Password')
      .fill('correct-horse-battery')
    await screen.getByRole('button', { name: 'Continue' }).click()

    await vi.waitFor(() => {
      const call = fake
        .apiCalls()
        .find((c) => c.url === '/me/sessions/logout-others')
      expect(call?.data).toEqual({ password: 'correct-horse-battery' })
    })
  })
})
