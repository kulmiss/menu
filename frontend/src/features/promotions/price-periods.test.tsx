import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { PricePeriodsSection } from './price-periods'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>
const tree = {
  id: 1,
  categories: [
    {
      id: 2,
      name: { en: 'Coffee' },
      items: [
        { id: 7, name: { en: 'Macchiato' }, price_minor: 12000, variants: [] },
        {
          id: 8,
          name: { en: 'Shiro' },
          price_minor: null,
          variants: [
            { id: 81, name: { en: 'Small' }, price_minor: 8000 },
            { id: 82, name: { en: 'Large' }, price_minor: 15000 },
          ],
        },
      ],
    },
  ],
}

describe('Price periods', () => {
  afterEach(() => fake.restore())

  it('creates a happy hour with % off and a new price, and shows what guests see now', async () => {
    fake = fakeApi({
      'GET /t/1/price-periods': { data: { data: [] } },
      'GET /t/1/price-periods/preview': {
        data: {
          data: {
            now: 'Fri 16:30',
            running: [
              {
                id: 3,
                name: 'Happy hour',
                until: '18:00',
                label: 'Happy hour until 18:00',
              },
            ],
            prices: [{ item: 'Macchiato', normal: 'Br 120', now: 'Br 90' }],
          },
        },
      },
      'GET /t/1/branches': { data: { data: [] } },
      'GET /t/1/menus': {
        data: { data: [{ id: 1, name: { en: 'All day' } }] },
      },
      'GET /t/1/menus/1/tree': { data: { data: tree } },
      'POST /t/1/price-periods': { status: 201, data: { data: {} } },
    })
    const screen = await renderWithQuery(
      <PricePeriodsSection
        tenantId={1}
        locale='en'
        currency='Br'
        canEdit
        included
      />,
      { me: meFixture() }
    )

    await expect
      .element(screen.getByText('Happy hour until 18:00'))
      .toBeVisible()
    await expect.element(screen.getByText('Br 90')).toBeVisible()

    await userEvent.click(
      screen.getByRole('button', { name: 'New price period' })
    )
    await userEvent.fill(
      screen.getByLabelText('Name shown to guests'),
      'Happy hour'
    )
    await userEvent.click(screen.getByRole('combobox', { name: 'Add a dish' }))
    await userEvent.click(screen.getByRole('option', { name: 'Shiro' }))
    await expect.element(screen.getByText('Shiro · Large')).toBeVisible()
    await userEvent.click(screen.getByRole('combobox', { name: 'Add a dish' }))
    await userEvent.click(screen.getByRole('option', { name: 'Macchiato' }))
    await userEvent.click(
      screen.getByRole('combobox', { name: 'Kind of price' }).last()
    )
    await userEvent.click(screen.getByRole('option', { name: 'New price' }))
    await userEvent.fill(
      screen.getByRole('spinbutton', { name: 'New price' }),
      '90'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toMatchObject({
        name: { en: 'Happy hour' },
        weekdays: [1, 2, 3, 4, 5],
        starts_at: '16:00',
        ends_at: '18:00',
        items: [
          { item_id: 8, item_variant_id: 81, mode: 'percent', value: 10 },
          { item_id: 8, item_variant_id: 82, mode: 'percent', value: 10 },
          { item_id: 7, item_variant_id: null, mode: 'fixed', value: 9000 },
        ],
      })
  })

  it('explains when the plan does not include it', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(
      <PricePeriodsSection
        tenantId={1}
        locale='en'
        currency='Br'
        canEdit
        included={false}
      />,
      { me: meFixture() }
    )
    await expect
      .element(screen.getByText(/not included in your plan/))
      .toBeVisible()
    expect(screen.container.textContent).not.toContain('New price period')
  })
})
