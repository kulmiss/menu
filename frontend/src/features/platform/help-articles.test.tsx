import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { HelpArticlesPage } from './help-articles'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const meta = {
  topics: [
    { key: 'menu', title: 'Your menu' },
    { key: 'faq', title: 'FAQ and contact' },
  ],
  features: ['sold_out', 'branches'],
}
const article = {
  slug: 'sold-out',
  title: 'Sold out',
  topic: 'menu',
  summary: 'Show guests a dish is finished.',
  body: 'Up to {{photos_per_dish}} photos.',
  features: ['sold_out'],
  roles: [],
  pages: ['/admin/dishes'],
  app: [],
  keywords: [],
  order: 60,
  hidden: false,
  source: 'default',
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Help articles', () => {
  afterEach(() => fake.restore())

  it('shows the "Was this helpful?" counts, edits with a live preview and saves', async () => {
    fake = fakeApi({
      'GET /super/help-articles?audience=restaurant': {
        data: {
          data: [
            {
              slug: 'sold-out',
              title: 'Sold out',
              topic: 'menu',
              topic_title: 'Your menu',
              features: ['sold_out'],
              roles: [],
              source: 'default',
              hidden: false,
              yes: 12,
              no: 3,
            },
          ],
          meta,
        },
      },
      'GET /super/help-articles/restaurant/sold-out': {
        data: { data: { ...article, default: article } },
      },
      'POST /super/help-articles/preview': {
        data: {
          data: {
            body: 'Up to 5 photos.',
            html: '<p>Up to 5 photos.</p>',
            unknown: [],
          },
        },
      },
      'PUT /super/help-articles/restaurant/sold-out': {
        data: { data: article },
      },
    })
    const screen = await renderWithQuery(<HelpArticlesPage />)
    await expect.element(screen.getByLabelText('12 yes, 3 no')).toBeVisible()
    await expect
      .element(screen.getByText('Only when on: sold_out'))
      .toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Sold out' }))
    await expect
      .element(screen.getByLabelText('Text', { exact: true }))
      .toHaveValue('Up to {{photos_per_dish}} photos.')
    await expect.element(screen.getByText('Up to 5 photos.')).toBeVisible()

    await userEvent.fill(screen.getByLabelText('Title'), 'Sold out today')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        title: 'Sold out today',
        features: ['sold_out'],
        pages: ['/admin/dishes'],
      })
  })
})
