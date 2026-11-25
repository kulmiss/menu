import { renderWithQuery } from '@/test-utils/render'
import { expect, it, vi } from 'vitest'
import { RestaurantBlocked } from '@/features/errors/restaurant-blocked'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

// Blocked by the Super Admin (owner, 2026-11-25): a card with the reason, and a way out.
it('shows the block reason and a sign-out button', async () => {
  const screen = await renderWithQuery(
    <RestaurantBlocked reason='Fraud report (demo)' />
  )
  await expect
    .element(screen.getByText('Your restaurant is blocked'))
    .toBeVisible()
  await expect.element(screen.getByText(/Fraud report \(demo\)/)).toBeVisible()
  await expect
    .element(screen.getByRole('button', { name: 'Sign out' }))
    .toBeVisible()
})

it('omits the reason box when there is no reason', async () => {
  const screen = await renderWithQuery(<RestaurantBlocked reason={null} />)
  await expect
    .element(screen.getByText('Your restaurant is blocked'))
    .toBeVisible()
  await expect.element(screen.getByText('Reason:')).not.toBeInTheDocument()
})
