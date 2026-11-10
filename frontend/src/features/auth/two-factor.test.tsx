import { fakeApi, invalid } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { TwoFactorForm } from './two-factor'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('TwoFactorForm', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake.restore())

  it('sends the 6-digit code and continues', async () => {
    fake = fakeApi({ 'POST /auth/two-factor-challenge': { status: 204 } })
    const screen = await renderWithQuery(
      <TwoFactorForm redirectTo='/admin/team' />
    )

    await userEvent.type(screen.getByRole('textbox'), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        href: '/admin/team',
        replace: true,
      })
    )
    expect(fake.apiCalls()[0].data).toEqual({ code: '123456' })
  })

  it('accepts a recovery code instead', async () => {
    fake = fakeApi({ 'POST /auth/two-factor-challenge': { status: 204 } })
    const screen = await renderWithQuery(<TwoFactorForm />)

    await userEvent.click(
      screen.getByRole('button', { name: 'Use a recovery code instead' })
    )
    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Recovery code' }),
      ' abcd-efgh-ijkl '
    )
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    await vi.waitFor(() => expect(navigate).toHaveBeenCalled())
    expect(fake.apiCalls()[0].data).toEqual({ recovery_code: 'abcd-efgh-ijkl' })
  })

  it('shows a wrong code under the field', async () => {
    fake = fakeApi({
      'POST /auth/two-factor-challenge': invalid({
        code: 'The provided two factor authentication code was invalid.',
      }),
    })
    const screen = await renderWithQuery(<TwoFactorForm />)

    await userEvent.type(screen.getByRole('textbox'), '000000')
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    await expect.element(screen.getByText(/code was invalid/)).toBeVisible()
  })

  it('starts over when the sign-in step expired', async () => {
    fake = fakeApi({ 'POST /auth/two-factor-challenge': { status: 401 } })
    const screen = await renderWithQuery(<TwoFactorForm redirectTo='/admin' />)

    await userEvent.type(screen.getByRole('textbox'), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        to: '/auth/sign-in',
        search: { redirect: '/admin' },
      })
    )
  })
})
