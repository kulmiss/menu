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
    branch: 'Main',
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
    branch: 'Main',
    dining_table_id: 3,
    description: 'Table 1',
  },
]

let fake: ReturnType<typeof fakeApi>

function render(ui: React.ReactNode) {
  fake = fakeApi({
    'GET /t/1/branches': { data: { data: BRANCHES } },
    'GET /t/1/menus': {
      data: {
        data: [
          {
            id: 5,
            name: { en: 'All day' },
            slug: 'all-day',
            schedule_mode: 'always',
            is_visible: true,
            sort: 0,
          },
        ],
      },
    },
    'GET /t/1/branches/1/tables': { data: { data: [] } },
    'POST /t/1/branches/1/tables': { status: 201, data: { data: [] } },
    'POST /t/1/branches': { status: 201, data: { data: branch(3, 'Bole') } },
    'GET /t/1/qr-codes': { data: { data: CODES } },
    'GET /t/1/branches/2/overrides': { data: { data: [] } },
    'PUT /t/1/branches/2/overrides': { data: { data: [] } },
    'GET /t/1/menus/5/tree': {
      data: {
        data: {
          menu: {
            id: 5,
            name: { en: 'All day' },
            slug: 'all-day',
            schedule_mode: 'always',
            is_visible: true,
            sort: 0,
          },
          categories: [
            {
              id: 9,
              menu_id: 5,
              name: { en: 'Drinks' },
              description: {},
              icon: null,
              media_id: null,
              is_visible: true,
              sort: 0,
              items: [
                {
                  id: 40,
                  category_id: 9,
                  name: { en: 'Juice' },
                  summary: {},
                  price_minor: null,
                  from_price_minor: 6000,
                  variants_count: 2,
                  media_id: null,
                  is_sold_out: false,
                  is_visible: true,
                  is_new: false,
                  is_popular: false,
                  sort: 0,
                  variants: [
                    { id: 401, name: { en: 'Small' }, price_minor: 6000 },
                    { id: 402, name: { en: 'Large' }, price_minor: 9000 },
                  ],
                },
              ],
            },
          ],
        },
      },
    },
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

describe('Branch prices', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('sets a branch price for one size and leaves the other blank', async () => {
    const screen = await render(<BranchesPage />)
    await userEvent.click(
      screen.getByRole('button', { name: 'Prices & availability' }).nth(1)
    )
    await userEvent.type(
      screen.getByLabelText('Price at this branch for Juice, Small'),
      '50'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({
        overrides: [
          {
            item_id: 40,
            price_minor: null,
            is_sold_out: null,
            is_hidden: null,
            sizes: { '401': 5000 },
          },
        ],
      })
  })
})

describe('QrCodesPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('offers downloads for each code and table cards per branch', async () => {
    const screen = await render(<QrCodesPage />)
    await expect
      .element(screen.getByText('Whole restaurant', { exact: true }))
      .toBeVisible()
    // One table for every code, tables included, each with its downloads.
    await expect
      .element(screen.getByRole('link', { name: 'PDF · Whole restaurant' }))
      .toHaveAttribute('href', '/api/v1/t/1/qr-codes/7.pdf')
    await expect
      .element(screen.getByRole('link', { name: 'Print table cards' }))
      .toHaveAttribute('href', '/api/v1/t/1/branches/1/table-cards.pdf')
  })
})
