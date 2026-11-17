import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { LandingSettings, type LandingSettingsData } from './landing-settings'

const initial: LandingSettingsData = {
  brand_color: '#c2410c',
  landing_testimonials: [
    { quote: 'Guests order faster.', name: 'Sara', role: 'Café', real: false },
  ],
  socials: {},
  upgrade_whatsapp: null,
}
let fake: ReturnType<typeof fakeApi>

describe('Landing page → colour, WhatsApp, customers and links', () => {
  afterEach(() => fake.restore())

  it('saves the real-customer tick, and never the texts or sections (they have their own editor)', async () => {
    fake = fakeApi({ 'PUT /super/settings': { data: { data: {} } } })
    const screen = await renderWithQuery(<LandingSettings initial={initial} />)

    const real = screen.getByRole('checkbox', {
      name: 'This is a real customer (they agreed to be shown)',
    })
    await expect.element(real).not.toBeChecked()
    await userEvent.click(real)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        landing_testimonials: [
          {
            quote: 'Guests order faster.',
            name: 'Sara',
            role: 'Café',
            real: true,
          },
        ],
      })
    const body = fake.calls.find((c) => c.method === 'PUT')?.data as object
    expect(body).not.toHaveProperty('landing_sections')
    expect(body).not.toHaveProperty('landing_headline')
  })
})
