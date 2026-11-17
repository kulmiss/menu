import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import i18n from 'i18next'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { LandingEditorPage } from './landing-editor'
import { fieldLabel, type LandingData, SECTIONS } from './landing-editor-data'

const defaults: Record<string, string> = {
  'hero.headline': 'Your menu on every phone. Free to start.',
  'hero.subline': 'Guests scan a QR code and see your menu.',
  'problem.title': 'Paper menus cost you time and money',
  'problem.items.print.title': 'You print again and again',
  'problem.items.old.title': 'Guests see old prices',
  'how.title': 'Ready in 3 easy steps',
  'faq.items.free.q': 'Is it really free?',
  'faq.items.app.q': 'Do my customers need an app?',
  'faq.help_whatsapp': 'Chat with us on WhatsApp',
  'compare.rows.0.0': 'A new price means a new print.',
  'compare.rows.0.1': 'Change a price in seconds.',
  'footer.about': 'QR code menus for restaurants and cafés.',
}
const data: LandingData = {
  url: 'http://localhost',
  texts: { 'how.title': 'Three steps' },
  defaults,
  max: Object.fromEntries(
    Object.keys(defaults).map((k) => [k, k.endsWith('subline') ? 400 : 160])
  ),
  sections: Object.fromEntries(
    SECTIONS.map((s) => [s, s !== 'testimonials'])
  ) as LandingData['sections'],
  order: [...SECTIONS],
  images: {
    hero: { url: '/landing/hero-600.webp', custom: false },
    hero_dark: { url: '/landing/hero-dark-600.webp', custom: false },
    demo: { url: '/media/landing/demo-x/600.webp', custom: true },
    demo_dark: { url: '/landing/demo-dark-600.webp', custom: false },
    og: { url: '/landing/og.jpg', custom: false },
  },
  clicks: {
    days: [
      { day: '2026-11-16', signup: 3, google: 1, demo: 4 },
      { day: '2026-11-17', signup: 9, google: 2, demo: 5 },
    ],
    totals: { signup: 12, google: 3, demo: 9 },
    all_time: { signup: 40, google: 3, demo: 21 },
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Landing page', () => {
  afterEach(() => fake.restore())

  it('shows the button clicks of the last 30 days', async () => {
    fake = fakeApi({
      'GET /platform/landing': { data: { data } },
      'GET /platform/settings': { data: { data: {} } },
    })
    const screen = await renderWithQuery(<LandingEditorPage />)

    await expect.element(screen.getByText('Button clicks')).toBeVisible()
    await expect.element(screen.getByText('12', { exact: true })).toBeVisible()
    await expect.element(screen.getByText('40 in total')).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Clicks per day (last 14 days)' })
    )
    await expect
      .element(screen.getByRole('cell', { name: '9', exact: true }).first())
      .toBeVisible()
  })

  it('changes a text, switches a section off, moves one and saves them together', async () => {
    fake = fakeApi({
      'GET /platform/landing': { data: { data } },
      'GET /platform/settings': { data: { data: {} } },
      'PUT /platform/landing': { data: { data } },
    })
    const screen = await renderWithQuery(<LandingEditorPage />)

    await userEvent.click(
      screen.getByRole('button', { name: /^Problem → solution/ })
    )
    const title = screen.getByLabelText('Headline', { exact: true }).first()
    await expect
      .element(title)
      .toHaveAttribute('placeholder', 'Paper menus cost you time and money')
    await userEvent.type(title, 'Paper costs money')
    await userEvent.click(
      screen.getByRole('switch', { name: 'Show “Paper menu vs QR menu”' })
    )
    await userEvent.click(
      screen.getByRole('button', {
        name: 'Move “See it on your phone (live demo)” up',
      })
    )
    // A changed text can go back to the default.
    await expect.element(screen.getByText('1 changed').first()).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Save texts and sections' })
    )

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        texts: {
          'how.title': 'Three steps',
          'problem.title': 'Paper costs money',
        },
        sections: { compare: false, problem: true, testimonials: false },
        order: [
          'demo',
          'problem',
          'how',
          'features',
          'compare',
          'pricing',
          'testimonials',
          'faq',
          'cta',
        ],
      })
  })

  it('shows each picture, built-in or uploaded, with "Use the built-in one" only for uploaded ones', async () => {
    fake = fakeApi({
      'GET /platform/landing': { data: { data } },
      'GET /platform/settings': { data: { data: {} } },
    })
    const screen = await renderWithQuery(<LandingEditorPage />)

    await expect.element(screen.getByText('Demo phone (light)')).toBeVisible()
    await expect.element(screen.getByText('Your picture.')).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Use the built-in one' }).all()
    ).toHaveLength(1)
  })
})

describe('fieldLabel', () => {
  const all = Object.keys(defaults)
  const t = i18n.t.bind(i18n)
  it('names list texts by their place', () => {
    expect(fieldLabel('problem.items.old.title', all, t)).toBe(
      'Card 2 · Headline'
    )
    expect(fieldLabel('faq.items.app.q', all, t)).toBe('Question 2')
    expect(fieldLabel('compare.rows.0.1', all, t)).toBe('Row 1 · QR menu')
    expect(fieldLabel('hero.subline', all, t)).toBe('Line under the headline')
    expect(fieldLabel('footer.about', all, t)).toBe('About line')
  })
})
