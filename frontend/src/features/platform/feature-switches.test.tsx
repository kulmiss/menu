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
      'GET /platform/settings/features': { data: { data: rows } },
      'PUT /platform/settings/features': { data: { data: rows } },
    })
    const screen = await renderWithQuery(<FeatureSwitches />)

    await expect.element(screen.getByText('3 of 4 features on')).toBeVisible()
    await expect.element(screen.getByText('Off for everyone')).toBeVisible()
    await expect.element(screen.getByText('off on 1 plan')).toBeVisible()
    await expect.element(screen.getByRole('heading', { name: 'Platform' })).toBeVisible()

    await userEvent.click(screen.getByRole('switch', { name: 'Promotions' }))
    await expect.element(screen.getByText('1 change not saved')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({ features: { promotions: false } })
  })
})
