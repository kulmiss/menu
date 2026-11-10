import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { ImportExportPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const summary = {
  create: 1,
  update: 1,
  unchanged: 0,
  errors: 0,
  new_menus: 0,
  new_categories: 1,
  new_tags: 0,
}
const rows = [
  {
    line: 2,
    action: 'update',
    menu: 'All day',
    category: 'Coffee',
    item: 'Macchiato',
    changes: ['price'],
    errors: [],
  },
  {
    line: 3,
    action: 'create',
    menu: 'All day',
    category: 'Tea',
    item: 'Mint tea',
    changes: [],
    errors: [],
  },
]
const file = () =>
  new File(['menu,category,item\n'], 'menu.csv', { type: 'text/csv' })
let fake: ReturnType<typeof fakeApi>

describe('ImportExportPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('shows a preview and imports after confirming', async () => {
    fake = fakeApi({
      'POST /t/1/import/preview': {
        data: {
          data: { token: 'x'.repeat(32), summary, rows, expires_at: '' },
        },
      },
      'POST /t/1/import/commit': { data: { data: { summary } } },
    })
    const screen = await renderWithQuery(<ImportExportPage />, {
      me: meFixture(),
    })

    await userEvent.upload(screen.getByLabelText('Choose file'), file())

    await expect.element(screen.getByText('Changes: Price')).toBeVisible()
    await expect
      .element(screen.getByText('All day › Tea › Mint tea'))
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Import' }))

    await vi.waitFor(() =>
      expect(
        fake.apiCalls().find((c) => c.url === '/t/1/import/commit')?.data
      ).toEqual({ token: 'x'.repeat(32) })
    )
    await expect
      .poll(() => screen.container.textContent)
      .not.toContain('Mint tea')
  })

  it('blocks the import while rows have errors and shows them first', async () => {
    fake = fakeApi({
      'POST /t/1/import/preview': {
        data: {
          data: {
            token: 'y'.repeat(32),
            summary: { ...summary, errors: 1 },
            rows: [
              ...rows,
              {
                line: 4,
                action: 'error',
                menu: 'All day',
                category: 'Tea',
                item: 'Chai',
                changes: [],
                errors: ['"abc" is not a price.'],
              },
            ],
            expires_at: '',
          },
        },
      },
    })
    const screen = await renderWithQuery(<ImportExportPage />, {
      me: meFixture(),
    })

    await userEvent.upload(screen.getByLabelText('Choose file'), file())

    await expect
      .element(screen.getByText('"abc" is not a price.'))
      .toBeVisible()
    expect(screen.container.textContent).not.toContain('Mint tea')
    await expect
      .element(screen.getByRole('button', { name: 'Import' }))
      .toBeDisabled()
  })

  it("shows why a file can't be used", async () => {
    fake = fakeApi({
      'POST /t/1/import/preview': invalid({
        file: 'The first row needs these column names: menu.',
      }),
    })
    const screen = await renderWithQuery(<ImportExportPage />, {
      me: meFixture(),
    })

    await userEvent.upload(screen.getByLabelText('Choose file'), file())

    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('column names: menu')
  })
})
