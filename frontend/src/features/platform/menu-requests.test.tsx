import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { MenuRequestsPage } from './menu-requests'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Menu requests (QUESTIONS #235)', () => {
  afterEach(() => fake.restore())

  it('shows each request with its photos and note, and moves it on to done', async () => {
    fake = fakeApi({
      'GET /super/menu-requests': {
        data: {
          data: {
            open: 1,
            requests: [
              {
                id: 7,
                status: 'new',
                note: 'Drinks on the back',
                created_at: '2026-11-19T08:00:00+03:00',
                restaurant: { id: 3, name: 'Jebena Corner', slug: 'jebena' },
                user: { name: 'Abebe', email: 'abebe@example.com' },
                files: [
                  { n: 0, name: 'page 1.jpg', mime: 'image/jpeg', bytes: 1000 },
                  {
                    n: 1,
                    name: 'drinks.pdf',
                    mime: 'application/pdf',
                    bytes: 2000,
                  },
                ],
              },
            ],
          },
        },
      },
      'PUT /super/menu-requests/7': { data: { data: {} } },
    })
    const screen = await renderWithQuery(<MenuRequestsPage />, { me: admin })

    await expect.element(screen.getByText('Jebena Corner')).toBeVisible()
    await expect.element(screen.getByText('Drinks on the back')).toBeVisible()
    await expect
      .element(screen.getByRole('img', { name: 'page 1.jpg' }))
      .toHaveAttribute('src', '/api/v1/super/menu-requests/7/files/0')
    await expect
      .element(screen.getByRole('link', { name: /drinks\.pdf/ }))
      .toHaveAttribute('href', '/api/v1/super/menu-requests/7/files/1')
    await userEvent.click(screen.getByRole('button', { name: 'Start' }))
    await expect
      .poll(
        () =>
          fake.calls.find((c) => c.method === 'PUT')?.data as
            { status: string } | undefined
      )
      .toEqual({ status: 'working' })
  })
})
