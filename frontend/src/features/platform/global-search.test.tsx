import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { navigate } from '@/test-utils/router-mock'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import GlobalSearch from './global-search'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

describe('Global search', () => {
  afterEach(() => fake.restore())

  it('shows grouped results and opens the restaurant with the keyboard', async () => {
    fake = fakeApi({
      'GET /platform/search?q=kaldi': {
        data: {
          data: {
            restaurants: [
              {
                id: 4,
                title: 'Kaldi Corner',
                subtitle: '/kaldi-corner',
                tenant_id: 4,
              },
            ],
            owners: [
              {
                id: 9,
                title: 'Hana Kaldi',
                subtitle: 'hana@kaldi.et · Kaldi Corner',
                tenant_id: 4,
              },
            ],
            invoices: [],
            payments: [],
            tags: [
              {
                id: 2,
                title: 'Kaldi network',
                subtitle: null,
                tenant_id: null,
                color: '#dc2626',
              },
            ],
          },
        },
      },
    })
    const onOpenChange = vi.fn()
    const screen = await renderWithQuery(
      <GlobalSearch open onOpenChange={onOpenChange} />,
      { me: admin }
    )

    await userEvent.fill(screen.getByRole('combobox'), 'kaldi')
    await expect.element(screen.getByText('Restaurants')).toBeVisible()
    await expect
      .element(screen.getByText('hana@kaldi.et · Kaldi Corner'))
      .toBeVisible()
    await expect
      .element(screen.getByText('Tags', { exact: true }).first())
      .toBeVisible()

    await userEvent.keyboard('{Enter}')
    await vi.waitFor(() =>
      expect(navigate).toHaveBeenCalledWith({
        to: '/platform/$section',
        params: { section: 'restaurants' },
        search: { open: 4 },
      })
    )
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
