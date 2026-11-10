import { fakeApi, invalid } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { SignInForm } from './sign-in'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

async function fillAndSubmit(
  screen: Awaited<ReturnType<typeof renderWithQuery>>
) {
  await userEvent.fill(
    screen.getByRole('textbox', { name: 'Email' }),
    'selam@example.com'
  )
  await userEvent.fill(
    screen.getByLabelText('Password'),
    'correct-horse-battery'
  )
  await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))
}

describe('SignInForm', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake?.restore())

  it('asks for an email and a password', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<SignInForm />)

    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    await expect
      .element(screen.getByText('Enter a valid email address.'))
      .toBeVisible()
    await expect.element(screen.getByText('Enter your password.')).toBeVisible()
    expect(fake.apiCalls()).toEqual([])
  })

  it('signs in with a CSRF cookie first and returns to the page asked for', async () => {
    fake = fakeApi({ 'POST /auth/login': { data: { two_factor: false } } })
    const screen = await renderWithQuery(
      <SignInForm redirectTo='/admin/team' />
    )

    await fillAndSubmit(screen)

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        href: '/admin/team',
        replace: true,
      })
    )
    expect(fake.calls[0].url).toBe('/sanctum/csrf-cookie')
    expect(fake.apiCalls()[0].data).toEqual({
      email: 'selam@example.com',
      password: 'correct-horse-battery',
      remember: true,
    })
  })

  it('never redirects to another site', async () => {
    fake = fakeApi({ 'POST /auth/login': { data: { two_factor: false } } })
    const screen = await renderWithQuery(
      <SignInForm redirectTo='https://evil.example' />
    )

    await fillAndSubmit(screen)

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({ href: '/admin', replace: true })
    )
  })

  it('goes to the code step when the account uses two-factor', async () => {
    fake = fakeApi({ 'POST /auth/login': { data: { two_factor: true } } })
    const screen = await renderWithQuery(
      <SignInForm redirectTo='/admin/team' />
    )

    await fillAndSubmit(screen)

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        to: '/auth/two-factor',
        search: { redirect: '/admin/team' },
      })
    )
  })

  it("shows the server's message under the field", async () => {
    fake = fakeApi({
      'POST /auth/login': invalid({
        email: 'These credentials do not match our records.',
      }),
    })
    const screen = await renderWithQuery(<SignInForm />)

    await fillAndSubmit(screen)

    await expect
      .element(screen.getByText('These credentials do not match our records.'))
      .toBeVisible()
    expect(navigate).not.toHaveBeenCalled()
  })

  it('has no social sign-in buttons', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<SignInForm />)

    expect(screen.container.textContent).not.toMatch(/GitHub|Facebook/)
  })
})
