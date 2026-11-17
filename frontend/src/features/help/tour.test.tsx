import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import WelcomeTour from './tour'
import { tourSlides } from './tour-slides'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('Welcome tour', () => {
  afterEach(() => fake?.restore())

  it('has at most 4 slides, and leaves out what the role or the features rule out', () => {
    expect(tourSlides('owner', true).map((s) => s.key)).toEqual([
      'dashboard',
      'dish',
      'look',
      'qr',
    ])
    expect(tourSlides('manager', false)).toHaveLength(4)
    expect(tourSlides('staff', true).map((s) => s.key)).toEqual([
      'dashboard',
      'sold_out',
    ])
    expect(tourSlides('staff', false).map((s) => s.key)).toEqual(['dashboard'])
  })

  it('walks through the slides, ends on the QR page, and is not shown again', async () => {
    fake = fakeApi({ 'PUT /me/onboarding': { status: 204 } })
    const onClose = vi.fn()
    const screen = await renderWithQuery(
      <WelcomeTour role='owner' soldOut onClose={onClose} />,
      { me: meFixture() }
    )
    await expect
      .element(
        screen.getByRole('heading', { name: 'Welcome! This is your dashboard' })
      )
      .toBeVisible()
    for (const title of [
      'Add your first dish',
      'Choose your menu look',
      'Share your QR code',
    ]) {
      await userEvent.click(screen.getByRole('button', { name: 'Next' }))
      await expect
        .element(screen.getByRole('heading', { name: title }))
        .toBeVisible()
    }
    await userEvent.click(
      screen.getByRole('button', { name: 'Get my QR code' })
    )
    expect(onClose).toHaveBeenCalled()
    expect(navigate).toHaveBeenCalledWith({ to: '/admin/qr-codes' })
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({ tour: 'web_tour', seen: true })
  })

  it('can be skipped on the first slide', async () => {
    fake = fakeApi({ 'PUT /me/onboarding': { status: 204 } })
    const onClose = vi.fn()
    const screen = await renderWithQuery(
      <WelcomeTour role='staff' soldOut={false} onClose={onClose} />,
      { me: meFixture() }
    )
    await expect.element(screen.getByText('1 of 1')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Skip tour' }))
    expect(onClose).toHaveBeenCalled()
    await expect
      .poll(() => fake.calls.filter((c) => c.method === 'PUT').length)
      .toBe(1)
  })
})
