import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { FeatureSwitches } from './feature-switches'

const row = (key: string, label: string, enabled = true, extra = {}) => ({
  key,
  label,
  enabled,
  plan_feature: key !== 'signup',
  plans_off: 0,
  restaurants_off: 0,
  ...extra,
})
const rows = [
  row('sold_out', '“Sold out” marks', true, { plans_off: 1 }),
  row('share', '“Share” button on the menu', false),
  row('promotions', 'Promotions'),
  row('signup', 'Self sign-up for new restaurants'),
]
let fake: ReturnType<typeof fakeApi>

describe('Settings → Features', () => {
  afterEach(() => fake.restore())

  it('lists what is on and off, and saves only the changes', async () => {
    fake = fakeApi({
      'GET /super/settings/features': { data: { data: rows } },
      'PUT /super/settings/features': { data: { data: rows } },
    })
    const screen = await renderWithQuery(<FeatureSwitches />)

    await expect.element(screen.getByText('3 of 4 features on')).toBeVisible()
    await expect.element(screen.getByText('Off for everyone')).toBeVisible()
    await expect.element(screen.getByText('off on 1 plan')).toBeVisible()
    await expect
      .element(screen.getByRole('heading', { name: 'Platform' }))
      .toBeVisible()

    await userEvent.click(screen.getByRole('switch', { name: 'Promotions' }))
    await expect.element(screen.getByText('1 change not saved')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({ features: { promotions: false } })
  })

  it('filters the list with the search box', async () => {
    fake = fakeApi({
      'GET /super/settings/features': { data: { data: rows } },
    })
    const screen = await renderWithQuery(<FeatureSwitches />)
    await expect.element(screen.getByText('Promotions')).toBeVisible()

    const box = screen.getByRole('searchbox', { name: 'Search features…' })
    await userEvent.fill(box, 'promo')
    await expect.element(screen.getByText('Promotions')).toBeVisible()
    await expect
      .element(screen.getByText('“Share” button on the menu'))
      .not.toBeInTheDocument()

    await userEvent.fill(box, 'nothing-matches-this')
    await expect.element(screen.getByText(/No features match/)).toBeVisible()
  })

  // QUESTIONS #236: before saving, how many restaurants a change reaches.
  it('says how many menus lose the + buttons before My order is saved off', async () => {
    fake = fakeApi({
      'GET /super/settings/features': {
        data: {
          data: [...rows, row('order_list', 'My order (add to order buttons)')],
        },
      },
      'GET /super/settings/features/order_list/impact': {
        data: {
          data: {
            key: 'order_list',
            enabled: true,
            restaurants: 7,
            guests_see: 5,
          },
        },
      },
      'GET /super/settings/features/promotions/impact': {
        data: { data: { key: 'promotions', enabled: true, restaurants: 1 } },
      },
    })
    const screen = await renderWithQuery(<FeatureSwitches />)
    const toggle = screen.getByRole('switch', {
      name: 'My order (add to order buttons)',
    })
    await expect.element(toggle).toBeChecked()
    // Nothing asked before a change.
    expect(fake.calls.some((c) => c.url.includes('/impact'))).toBe(false)

    await userEvent.click(toggle)
    await expect
      .element(
        screen.getByText(
          'When you save: the "+" buttons disappear from 5 menus now showing them.'
        )
      )
      .toBeVisible()
    await userEvent.click(screen.getByRole('switch', { name: 'Promotions' }))
    await expect
      .element(
        screen.getByText('When you save: off for 1 restaurant that has it now.')
      )
      .toBeVisible()
    // Back as it was: the note goes.
    await userEvent.click(toggle)
    await expect
      .element(screen.getByText(/buttons disappear/))
      .not.toBeInTheDocument()
  })
})
