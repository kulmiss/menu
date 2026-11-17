import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { LandingSettings, type LandingSettingsData } from './landing-settings'

const initial: LandingSettingsData = {
  brand_color: '#c2410c',
  landing_headline: '',
  landing_subline: '',
  // Saved before the new page (owner, 2026-11-17): old keys, no "testimonials".
  landing_sections: { how: true, features: true, pricing: true, faq: false },
  landing_testimonials: [
    { quote: 'Guests order faster.', name: 'Sara', role: 'Café', real: false },
  ],
  socials: {},
  upgrade_whatsapp: null,
}
let fake: ReturnType<typeof fakeApi>

describe('Settings → Landing page', () => {
  afterEach(() => fake.restore())

  it('lists the new sections ("Customers say" off by default, no "Speed") and saves the real-customer tick', async () => {
    fake = fakeApi({ 'PUT /platform/settings': { data: { data: {} } } })
    const screen = await renderWithQuery(<LandingSettings initial={initial} />)

    await expect
      .element(screen.getByRole('checkbox', { name: 'Paper menu vs QR menu' }))
      .toBeChecked()
    await expect
      .element(screen.getByRole('checkbox', { name: 'Customers say' }))
      .not.toBeChecked()
    await expect
      .element(screen.getByRole('checkbox', { name: 'Questions and answers' }))
      .not.toBeChecked()
    expect(screen.container.textContent).not.toContain('Speed')

    const real = screen.getByRole('checkbox', {
      name: 'This is a real customer (they agreed to be shown)',
    })
    await expect.element(real).not.toBeChecked()
    await userEvent.click(real)
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Customers say' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        landing_sections: {
          problem: true,
          demo: true,
          compare: true,
          testimonials: true,
          faq: false,
        },
        landing_testimonials: [
          {
            quote: 'Guests order faster.',
            name: 'Sara',
            role: 'Café',
            real: true,
          },
        ],
      })
  })
})
