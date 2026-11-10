import { fakeApi, invalid } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { SignUpForm, SignUpPage } from './sign-up'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

type Screen = Awaited<ReturnType<typeof renderWithQuery>>

async function fillPerson(screen: Screen, email = true) {
  await userEvent.fill(
    screen.getByRole('textbox', { name: 'Your name' }),
    'Selam Tesfaye'
  )
  if (email)
    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Email' }),
      'selam@example.com'
    )
  await userEvent.fill(
    screen.getByLabelText('Password', { exact: true }),
    'correct-horse-battery'
  )
  await userEvent.fill(
    screen.getByLabelText('Confirm password'),
    'correct-horse-battery'
  )
}

describe('SignUpForm', () => {
  beforeEach(() => navigate.mockClear())
  afterEach(() => fake?.restore())

  it('fills the menu address from the restaurant name until it is edited', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<SignUpForm />)
    const name = screen.getByRole('textbox', { name: 'Restaurant name' })
    const slug = screen.getByRole('textbox', { name: 'Menu address' })

    await userEvent.fill(name, 'Buna & Bread Café')
    await expect.element(slug).toHaveValue('buna-bread-cafe')

    await userEvent.fill(slug, 'buna')
    await userEvent.fill(name, 'Something Else')
    await expect.element(slug).toHaveValue('buna')
  })

  it('creates the account and restaurant, then opens the admin', async () => {
    fake = fakeApi({ 'POST /auth/register': { status: 201 } })
    const screen = await renderWithQuery(<SignUpForm />)

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Restaurant name' }),
      'Buna & Bread'
    )
    await fillPerson(screen)
    await userEvent.click(
      screen.getByRole('button', { name: 'Create account' })
    )

    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({ href: '/admin', replace: true })
    )
    expect(fake.apiCalls()[0].data).toEqual({
      name: 'Selam Tesfaye',
      email: 'selam@example.com',
      password: 'correct-horse-battery',
      password_confirmation: 'correct-horse-battery',
      restaurant_name: 'Buna & Bread',
      slug: 'buna-bread',
      locale: 'en',
    })
  })

  it('checks the address before sending and shows server errors on it', async () => {
    fake = fakeApi({
      'POST /auth/register': invalid({
        slug: 'This address is already taken.',
      }),
    })
    const screen = await renderWithQuery(<SignUpForm />)

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Restaurant name' }),
      'ቡና'
    )
    await fillPerson(screen)
    await userEvent.click(
      screen.getByRole('button', { name: 'Create account' })
    )
    await expect
      .element(screen.getByText(/Use 3–50 lower-case letters/))
      .toBeVisible()
    expect(fake.apiCalls()).toEqual([])

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Menu address' }),
      'taken'
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Create account' })
    )
    await expect
      .element(screen.getByText('This address is already taken.'))
      .toBeVisible()
  })

  it('joins the inviting restaurant without asking for a new one', async () => {
    fake = fakeApi({ 'POST /auth/register': { status: 201 } })
    const screen = await renderWithQuery(
      <SignUpForm
        invite={{ token: 'tok-123', email: 'hana@example.com' }}
        redirectTo='/admin'
      />
    )

    expect(screen.container.textContent).not.toContain('Restaurant name')
    await expect
      .element(screen.getByRole('textbox', { name: 'Email' }))
      .toHaveValue('hana@example.com')

    await fillPerson(screen, false)
    await userEvent.click(
      screen.getByRole('button', { name: 'Create account and join' })
    )

    await vi.waitFor(() => expect(navigate).toHaveBeenCalled())
    expect(fake.apiCalls()[0].data).toMatchObject({
      email: 'hana@example.com',
      invitation_token: 'tok-123',
    })
    expect(fake.apiCalls()[0].data).not.toHaveProperty('restaurant_name')
  })
})

describe('SignUpPage with an invitation', () => {
  afterEach(() => fake.restore())

  it('shows which restaurant the person joins', async () => {
    fake = fakeApi({
      'GET /invitations/tok-123': {
        data: {
          data: {
            restaurant: 'Sahan Grill',
            email: 'hana@example.com',
            role: 'staff',
            expires_at: '2026-12-01T00:00:00Z',
          },
        },
      },
    })
    const screen = await renderWithQuery(<SignUpPage invite='tok-123' />)

    await expect.element(screen.getByText('Join Sahan Grill')).toBeVisible()
  })
})
