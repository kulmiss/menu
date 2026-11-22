import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import i18n from 'i18next'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { Landing2EditorPage } from './landing2-editor'
import { fieldLabel, type Landing2Data, SECTIONS } from './landing2-editor-data'

const en: Record<string, string> = {
  'hero.title': 'Menus guests love to scan.',
  'hero.points.0': 'No app for guests',
  'how.steps.0.title': 'Add your dishes',
  'faq.items.free.q': 'Is it really free?',
  'faq.items.app.q': 'Do my customers need an app?',
  'compare.rows.1.0': 'Sold out? Tell every table.',
  'problem.title': 'Paper menus cost you time and money',
}
const am: Record<string, string> = {
  'hero.title': 'እንግዶች መቃኘት የሚወዱት ሜኑ።',
  'hero.points.0': 'ለእንግዶች መተግበሪያ አያስፈልግም',
  'how.steps.0.title': 'ምግቦችዎን ያስገቡ',
  'faq.items.free.q': 'በእርግጥ ነፃ ነው?',
  'faq.items.app.q': 'ደንበኞቼ መተግበሪያ ያስፈልጋቸዋል?',
  'compare.rows.1.0': 'አልቋል?',
  'problem.title': 'የወረቀት ሜኑ',
}
const data: Landing2Data = {
  urls: { en: 'http://x/landing2', am: 'http://x/am/landing2' },
  is_home: false,
  sections: Object.fromEntries(
    SECTIONS.map((s) => [s, true])
  ) as Landing2Data['sections'],
  texts: { en: {}, am: {} },
  defaults: { en, am },
  shared: ['problem.title', 'faq.items.free.q', 'faq.items.app.q'],
  max: Object.fromEntries(Object.keys(en).map((k) => [k, 160])),
  payments: [
    { label_en: 'telebirr', label_am: 'ቴሌብር', icon: 'phone', on: true },
  ],
  payment_icons: ['phone', 'bank', 'card', 'cash'],
  numbers: {
    own: {
      restaurants: { on: true, min: 100 },
      dishes: { on: true, min: 1000 },
      views: { on: true, min: 1000 },
    },
    outside: [
      {
        value: 120,
        label_en: 'reviews',
        label_am: '',
        source: 'Google Play',
        date: '2026-09-01',
        on: true,
        min: 0,
      },
    ],
  },
  counted: {
    at: '2026-11-21T22:40:00+00:00',
    restaurants: 12,
    dishes: 340,
    views: 900,
  },
  stale: 1,
  stale_days: 30,
  today: '2026-11-22',
  stale_before: '2026-10-23',
  restaurants: [],
  min_restaurants: 3,
  video: null,
  footer: {
    texts: ['Our product'],
    am: {},
    defaults: {},
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Landing 2', () => {
  afterEach(() => fake.restore())

  it('switches a part off, changes an Amharic text and saves them together', async () => {
    fake = fakeApi({
      'GET /super/landing2': { data: { data } },
      'PUT /super/landing2': { data: { data } },
    })
    const screen = await renderWithQuery(<Landing2EditorPage />)

    await expect
      .element(screen.getByRole('link', { name: 'Open in Amharic' }))
      .toHaveAttribute('href', 'http://x/am/landing2')
    await userEvent.click(
      screen.getByRole('switch', { name: 'Free QR poster' })
    )
    // Amharic texts: the Amharic default shows in grey.
    await userEvent.click(
      screen.getByRole('button', { name: 'አማርኛ (Amharic)' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Hero' }))
    const title = screen.getByLabelText('Title')
    await expect.element(title).toHaveAttribute('placeholder', am['hero.title'])
    await expect.element(title).toHaveAttribute('lang', 'am')
    await userEvent.fill(title, 'ሜኑዎ በሁሉም ስልክ።')
    await userEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await expect
      .poll(() => fake.calls.filter((c) => c.method === 'PUT').length)
      .toBe(1)
    const body = fake.calls.find((c) => c.method === 'PUT')?.data as {
      sections: Record<string, boolean>
      texts: Record<string, Record<string, string>>
    }
    expect(body.sections.poster).toBe(false)
    expect(body.sections.switcher).toBe(true)
    expect(body.texts.am['hero.title']).toBe('ሜኑዎ በሁሉም ስልክ።')
    expect(body.texts.en).toEqual({})
  })

  it('warns about old outside numbers and shows our counted numbers', async () => {
    fake = fakeApi({ 'GET /super/landing2': { data: { data } } })
    const screen = await renderWithQuery(<Landing2EditorPage />)
    await expect
      .element(screen.getByText(/older than 30 days: 1/))
      .toBeVisible()
    await expect
      .element(screen.getByText('Older than 30 days: please check'))
      .toBeVisible()
    await expect.element(screen.getByText(/Now: 12/)).toBeVisible()
    await expect
      .element(
        screen.getByText('Hidden: 0 restaurants with permission, 3 needed.')
      )
      .toBeVisible()
    await expect
      .element(screen.getByText('No video yet: the video part stays hidden.'))
      .toBeVisible()
  })

  it('names list fields readably', () => {
    const all = Object.keys(en)
    const t = i18n.t.bind(i18n)
    expect(fieldLabel('how.steps.0.title', all, t)).toBe('Step 1 · Title')
    expect(fieldLabel('faq.items.app.q', all, t)).toBe('Item 2 · Question')
    expect(fieldLabel('compare.rows.1.0', all, t)).toBe('Row 1 · Paper menu')
    expect(fieldLabel('hero.points.0', all, t)).toBe('Point 1')
  })
})
