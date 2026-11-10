import { fakeApi, invalid } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import {
  ForgotPasswordForm,
  ResetPasswordForm,
  ResetPasswordPage,
} from './password-reset'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('password reset', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake.restore())

  it('asks for a link without revealing whether the account exists', async () => {
    fake = fakeApi({
      'POST /auth/forgot-password': { data: { message: 'sent' } },
    })
    const screen = await renderWithQuery(<ForgotPasswordForm />)

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Email' }),
      'selam@example.com'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Send link' }))

    await expect
      .element(screen.getByText(/If an account exists for selam@example.com/))
      .toBeVisible()
    expect(fake.apiCalls()[0].data).toEqual({ email: 'selam@example.com' })
  })

  it('saves the new password with the token from the email', async () => {
    fake = fakeApi({ 'POST /auth/reset-password': { data: { message: 'ok' } } })
    const screen = await renderWithQuery(
      <ResetPasswordForm token='tok' email='selam@example.com' />
    )

    await userEvent.fill(
      screen.getByLabelText('New password'),
      'another-long-secret'
    )
    await userEvent.fill(
      screen.getByLabelText('Confirm password'),
      'another-long-secret'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save password' }))

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        to: '/auth/sign-in',
        replace: true,
      })
    )
    expect(fake.apiCalls()[0].data).toEqual({
      token: 'tok',
      email: 'selam@example.com',
      password: 'another-long-secret',
      password_confirmation: 'another-long-secret',
    })
  })

  it('explains an expired link and offers a new one', async () => {
    fake = fakeApi({
      'POST /auth/reset-password': invalid({
        email: 'This password reset token is invalid.',
      }),
    })
    const screen = await renderWithQuery(
      <ResetPasswordForm token='old' email='selam@example.com' />
    )

    await userEvent.fill(
      screen.getByLabelText('New password'),
      'another-long-secret'
    )
    await userEvent.fill(
      screen.getByLabelText('Confirm password'),
      'another-long-secret'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save password' }))

    await expect
      .element(screen.getByText('This password reset token is invalid.'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Request a new link' }))
      .toBeVisible()
  })

  it('handles a link without token', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(
      <ResetPasswordPage email='x@example.com' />
    )

    await expect
      .element(screen.getByText(/incomplete or has expired/))
      .toBeVisible()
  })
})
