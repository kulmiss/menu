import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { QrCodesPage } from '@/features/qr'
import { type Branch, type QrCodeRow } from './api'
import { BranchesPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const branch = (
  id: number,
  name: string,
  extra: Partial<Branch> = {}
): Branch => ({
  id,
  slug: name.toLowerCase(),
  name: { en: name },
  address: {},
  phone: null,
  maps_url: null,
  is_default: id === 1,
  is_active: true,
  tables_count: 0,
  qr_code: null,
  menu_ids: [],
  url: `http://localhost/buna-bread${id === 1 ? '' : '/' + name.toLowerCase()}`,
  ...extra,
})

const BRANCHES = [branch(1, 'Main', { tables_count: 2 }), branch(2, 'Piassa')]
const CODES: QrCodeRow[] = [
  {
    id: 7,
    code: 'K7PM3XQ9',
    url: 'http://localhost/q/K7PM3XQ9',
    label: null,
    kind: 'restaurant',
    branch_id: null,
    dining_table_id: null,
    description: 'Whole restaurant',
  },
  {
    id: 8,
    code: 'AB23CD45',
    url: 'http://localhost/q/AB23CD45',
    label: null,
    kind: 'table',
    branch_id: 1,
    dining_table_id: 3,
    description: 'Table 1',
  },
]

let fake: ReturnType<typeof fakeApi>

function render(ui: React.ReactNode) {
  fake = fakeApi({
    'GET /t/1/branches': { data: { data: BRANCHES } },
    'GET /t/1/menus': { data: { data: [] } },
    'GET /t/1/branches/1/tables': { data: { data: [] } },
    'POST /t/1/branches/1/tables': { status: 201, data: { data: [] } },
    'POST /t/1/branches': { status: 201, data: { data: branch(3, 'Bole') } },
    'GET /t/1/qr-codes': { data: { data: CODES } },
  })
  return renderWithQuery(ui, { me: meFixture({ tenants: [tenantFixture()] }) })
}

describe('BranchesPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('lists branches with their addresses and adds a numbered range of tables', async () => {
    const screen = await render(<BranchesPage />)
    await expect
      .element(screen.getByText('localhost/buna-bread/piassa'))
      .toBeVisible()
    await expect
      .element(screen.getByText('Main', { exact: true }).first())
      .toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: '2 tables' }))
    await userEvent.clear(screen.getByLabelText('To'))
    await userEvent.type(screen.getByLabelText('To'), '12')
    await userEvent.click(screen.getByRole('button', { name: 'Add tables' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toEqual({ prefix: 'Table', from: 1, to: 12 })
  })

  it('creates a branch with an address made from its name', async () => {
    const screen = await render(<BranchesPage />)
    await userEvent.click(screen.getByRole('button', { name: 'New branch' }))
    await userEvent.type(screen.getByLabelText('Name'), 'Bole Atlas')
    await expect
      .element(screen.getByLabelText('Web address'))
      .toHaveValue('bole-atlas')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(
        () =>
          fake.calls.find(
            (c) => c.url === '/t/1/branches' && c.method === 'POST'
          )?.data
      )
      .toMatchObject({
        name: { en: 'Bole Atlas' },
        slug: 'bole-atlas',
        is_active: true,
      })
  })
})

describe('QrCodesPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('offers downloads for each code and table cards per branch', async () => {
    const screen = await render(<QrCodesPage />)
    await expect.element(screen.getByText('Whole restaurant')).toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'PDF' }))
      .toHaveAttribute('href', '/api/v1/t/1/qr-codes/7.pdf')
    await expect
      .element(screen.getByRole('link', { name: 'Print table cards' }))
      .toHaveAttribute('href', '/api/v1/t/1/branches/1/table-cards.pdf')
    // Table codes are printed as cards, not listed one by one.
    expect(screen.container.textContent).not.toContain('AB23CD45')
  })
})
