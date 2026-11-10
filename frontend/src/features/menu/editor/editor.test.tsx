import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { type Role } from '@/features/auth/api'
import { type Category, type ItemSummary, type Menu } from '../types'
import { MenuEditorPage } from './index'
import { ItemSheet } from './item-sheet'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const menu: Menu = {
  id: 1,
  name: { en: 'All day' },
  slug: 'all-day',
  schedule_mode: 'always',
  is_visible: true,
  sort: 0,
  schedules: [],
}
const item = (
  id: number,
  name: string,
  extra: Partial<ItemSummary> = {}
): ItemSummary => ({
  id,
  category_id: 10,
  name: { en: name },
  summary: {},
  price_minor: 12000,
  from_price_minor: 12000,
  variants_count: 0,
  media_id: null,
  is_sold_out: false,
  is_visible: true,
  is_new: false,
  is_popular: false,
  sort: id,
  ...extra,
})
const coffee: Category = {
  id: 10,
  menu_id: 1,
  name: { en: 'Coffee' },
  description: {},
  icon: null,
  media_id: null,
  is_visible: true,
  sort: 0,
  items: [
    item(100, 'Macchiato'),
    item(101, 'Latte', {
      variants_count: 2,
      price_minor: null,
      from_price_minor: 9000,
    }),
  ],
}

let fake: ReturnType<typeof fakeApi>

function renderEditor(
  role: Role,
  routes: Parameters<typeof fakeApi>[0] = {},
  canEditPrices?: boolean
) {
  fake = fakeApi({
    'GET /t/1/menus': { data: { data: [menu] } },
    'GET /t/1/menus/1/tree': { data: { data: { menu, categories: [coffee] } } },
    ...routes,
  })
  const tenant = tenantFixture({}, role)
  if (canEditPrices !== undefined && tenant.membership)
    tenant.membership.can_edit_prices = canEditPrices
  return renderWithQuery(
    <MenuEditorPage onSelectMenu={vi.fn()} onOpenItem={vi.fn()} />,
    { me: meFixture({ tenants: [tenant] }) }
  )
}

describe('MenuEditorPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('shows categories with prices, "from" for sizes', async () => {
    const screen = await renderEditor('owner')

    await expect
      .element(screen.getByRole('heading', { name: 'Coffee' }))
      .toBeVisible()
    await expect.element(screen.getByText('Br 120')).toBeVisible()
    await expect.element(screen.getByText('from Br 90')).toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: 'Move Macchiato' }))
      .toBeVisible()
  })

  it('lets staff mark sold out in one tap, but not edit the menu', async () => {
    const screen = await renderEditor('staff', {
      'PATCH /t/1/items/100/sold-out': { status: 200 },
    })

    await userEvent.click(
      screen.getByRole('switch', { name: 'Macchiato available' })
    )

    await vi.waitFor(() =>
      expect(fake.apiCalls().find((c) => c.method === 'PATCH')?.data).toEqual({
        is_sold_out: true,
      })
    )
    expect(
      screen.container.querySelector('[aria-label="Move Macchiato"]')
    ).toBeNull()
    expect(screen.container.textContent).not.toContain('Add item')
    expect(screen.container.textContent).not.toContain('Add category')
  })

  it('undoes the switch when saving fails', async () => {
    const screen = await renderEditor('staff', {
      'PATCH /t/1/items/100/sold-out': { status: 500 },
    })
    const toggle = screen.getByRole('switch', { name: 'Macchiato available' })

    await userEvent.click(toggle)

    await expect.element(toggle).toBeChecked()
  })

  it('filters items by search', async () => {
    const screen = await renderEditor('owner')

    await userEvent.fill(
      screen.getByRole('searchbox', { name: 'Search items' }),
      'lat'
    )

    await expect.element(screen.getByText('Latte')).toBeVisible()
    expect(screen.container.textContent).not.toContain('Macchiato')
  })

  it('marks many items sold out at once', async () => {
    const screen = await renderEditor('manager', {
      'POST /t/1/items/bulk': { status: 204 },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Select' }))
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Select Macchiato' })
    )
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Select Latte' })
    )
    await userEvent.click(
      screen.getByRole('toolbar').getByRole('button', { name: 'Sold out' })
    )

    await vi.waitFor(() =>
      expect(fake.apiCalls().find((c) => c.method === 'POST')?.data).toEqual({
        ids: [100, 101],
        action: 'sold_out',
      })
    )
  })

  it("adds a category in the restaurant's language", async () => {
    const screen = await renderEditor('owner', {
      'POST /t/1/categories': {
        status: 201,
        data: { data: { ...coffee, id: 11 } },
      },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Add category' }))
    await userEvent.fill(
      screen.getByRole('dialog').getByRole('textbox', { name: 'Name' }),
      'Tea'
    )
    await userEvent.click(
      screen.getByRole('dialog').getByRole('button', { name: 'Save' })
    )

    await vi.waitFor(() =>
      expect(fake.apiCalls().find((c) => c.method === 'POST')?.data).toEqual({
        menu_id: 1,
        name: { en: 'Tea' },
        description: {},
        is_visible: true,
        media_id: null,
      })
    )
  })

  it('offers to create the first menu when there is none', async () => {
    fake = fakeApi({ 'GET /t/1/menus': { data: { data: [] } } })
    const screen = await renderWithQuery(
      <MenuEditorPage onSelectMenu={vi.fn()} onOpenItem={vi.fn()} />,
      { me: meFixture() }
    )

    await expect
      .element(screen.getByRole('button', { name: 'Create your first menu' }))
      .toBeVisible()
  })
})

describe('ItemSheet', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  const ctxFor = (role: Role, prices: boolean) => ({
    tenantId: 1,
    locale: 'en',
    currency: 'ETB',
    canWrite: true,
    canManage: role !== 'staff',
    canEditPrices: prices,
  })

  it('creates an item with sizes', async () => {
    fake = fakeApi({
      'GET /t/1/addon-groups': { data: { data: [] } },
      'GET /t/1/tags': { data: { data: [] } },
      'POST /t/1/items': { status: 201, data: { data: { id: 5 } } },
    })
    const onCreated = vi.fn()
    const screen = await renderWithQuery(
      <ItemSheet
        ctx={ctxFor('owner', true)}
        itemId={null}
        newInCategory={10}
        categories={[coffee]}
        open
        onOpenChange={vi.fn()}
        onCreated={onCreated}
      />,
      { me: meFixture() }
    )

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Name' }),
      'Cappuccino'
    )
    await userEvent.click(screen.getByRole('tab', { name: 'Prices & sizes' }))
    await userEvent.fill(screen.getByRole('textbox', { name: 'Price' }), '95')
    await userEvent.click(screen.getByRole('button', { name: 'Add a size' }))
    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Size name' }),
      'Small'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await vi.waitFor(() => expect(onCreated).toHaveBeenCalledWith(5))
    expect(
      fake.apiCalls().find((c) => c.method === 'POST')?.data
    ).toMatchObject({
      category_id: 10,
      name: { en: 'Cappuccino' },
      price_minor: 9500,
      variants: [
        { name: { en: 'Small' }, price_minor: 9500, is_default: false },
      ],
    })
  })

  it('lets staff with price rights change only the price', async () => {
    fake = fakeApi({
      'GET /t/1/items/100': {
        data: {
          data: {
            ...item(100, 'Macchiato'),
            description: {},
            calories: null,
            prep_minutes: null,
            new_until: null,
            variants: [],
            addon_group_ids: [],
            tag_ids: [],
            allergens: [],
            menu_id: 1,
          },
        },
      },
      'PATCH /t/1/items/100/price': { data: { data: {} } },
    })
    const screen = await renderWithQuery(
      <ItemSheet
        ctx={ctxFor('staff', true)}
        itemId={100}
        newInCategory={null}
        categories={[coffee]}
        open
        onOpenChange={vi.fn()}
      />,
      { me: meFixture() }
    )

    await userEvent.fill(
      screen.getByRole('textbox', { name: 'Price' }),
      '130.50'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await vi.waitFor(() =>
      expect(fake.apiCalls().find((c) => c.method === 'PATCH')).toMatchObject({
        url: '/t/1/items/100/price',
        data: { price_minor: 13050 },
      })
    )
    expect(screen.container.textContent).not.toContain('Details')
  })
})
