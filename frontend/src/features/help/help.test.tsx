import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { type HelpArticle, articleFor, searchArticles } from './api'
import { HelpCentre } from './help-page'
import { HelpSheet } from './help-sheet'

const path = { pathname: '/admin/menu', hash: '' }
vi.mock('@tanstack/react-router', async (orig) => ({
  ...(await import('@/test-utils/router-mock')).routerMock(await orig()),
  useRouterState: ({ select }: { select: (s: unknown) => unknown }) =>
    select({ location: path }),
}))

const art = (over: Partial<HelpArticle>): HelpArticle => ({
  slug: 'x',
  topic: 'menu',
  title: 'X',
  summary: '',
  pages: [],
  app: [],
  keywords: [],
  body: '',
  html: '<p>x</p>',
  updated_at: null,
  ...over,
})

const library = {
  topics: [
    { key: 'menu', title: 'Your menu' },
    { key: 'faq', title: 'FAQ and contact' },
  ],
  articles: [
    art({
      slug: 'add-a-dish',
      title: 'Add a dish',
      summary: 'Name, price, photo',
      pages: ['/admin/menu'],
      keywords: ['item'],
      body: 'Tap Add item. See photos.',
      html: '<ol><li>Tap <strong>Add item</strong>.</li></ol><p>See <a href="help:dish-photos">Dish photos</a>.</p><p><img src="/help/shots/dish-editor-phone.webp" alt="Editor" title="phone"><img src="/help/shots/dish-editor-desktop.webp" alt="Editor" title="desktop"></p>',
    }),
    art({
      slug: 'dish-photos',
      title: 'Dish photos',
      body: 'Up to 5 photos.',
      html: '<p>Up to 5 photos.</p>',
    }),
    art({ slug: 'contact-support', topic: 'faq', title: 'Contact support' }),
  ],
  voted: {},
}

let fake: ReturnType<typeof fakeApi>

describe('Help centre helpers', () => {
  const list = [
    art({ slug: 'menu', pages: ['/admin/menu'] }),
    art({ slug: 'dash', pages: ['/admin'] }),
    art({ slug: 'features', pages: ['/platform/settings#features'] }),
    art({ slug: 'settings', pages: ['/platform/settings'] }),
  ]
  it('finds the article for a screen: the longest page, the #tab when there is one', () => {
    expect(articleFor(list, '/admin/menu')?.slug).toBe('menu')
    expect(articleFor(list, '/admin/menu/')?.slug).toBe('menu')
    expect(articleFor(list, '/admin')?.slug).toBe('dash')
    // "/admin" itself is not a prefix of every admin page.
    expect(articleFor(list, '/admin/team')).toBeUndefined()
    expect(articleFor(list, '/platform/settings', '#features')?.slug).toBe(
      'features'
    )
    expect(articleFor(list, '/platform/settings', '#email')?.slug).toBe(
      'settings'
    )
  })
  it('searches every word, best matches first', () => {
    const found = searchArticles(library.articles, 'photo')
    expect(found.map((a) => a.slug)).toEqual(['dish-photos', 'add-a-dish'])
    expect(searchArticles(library.articles, 'photo nothinglikethis')).toEqual(
      []
    )
    expect(searchArticles(library.articles, '  ')).toHaveLength(3)
  })
})

describe('HelpCentre', () => {
  afterEach(() => fake.restore())

  it('lists articles by topic, searches, opens one and links on to another', async () => {
    fake = fakeApi({ 'GET /t/1/help': { data: { data: library } } })
    const onOpen = vi.fn()
    const screen = await renderWithQuery(
      <HelpCentre
        source={{ audience: 'restaurant', tenantId: 1 }}
        onOpen={onOpen}
      />
    )
    await expect
      .element(screen.getByRole('heading', { name: 'Your menu' }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: /Add a dish/ }))
      .toBeVisible()

    await userEvent.fill(
      screen.getByRole('searchbox', { name: 'Search help' }),
      'zzz'
    )
    await expect
      .element(screen.getByText('Nothing found. Try other words.'))
      .toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Contact support' })
    )
    expect(onOpen).toHaveBeenLastCalledWith('contact-support')

    await userEvent.fill(
      screen.getByRole('searchbox', { name: 'Search help' }),
      'item'
    )
    await expect.element(screen.getByText('1 article found')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: /Add a dish/ }))
    expect(onOpen).toHaveBeenLastCalledWith('add-a-dish')
  })

  it('shows an article with its phone picture, follows help links and asks "Was this helpful?"', async () => {
    fake = fakeApi({
      'GET /t/1/help': { data: { data: library } },
      'POST /help/feedback': { status: 204 },
    })
    const onOpen = vi.fn()
    const screen = await renderWithQuery(
      <HelpCentre
        source={{ audience: 'restaurant', tenantId: 1 }}
        slug='add-a-dish'
        onOpen={onOpen}
      />
    )
    await expect
      .element(screen.getByRole('heading', { name: 'Add a dish', level: 1 }))
      .toBeVisible()
    await userEvent.click(screen.getByRole('link', { name: 'Dish photos' }))
    expect(onOpen).toHaveBeenCalledWith('dish-photos')

    await userEvent.click(screen.getByRole('button', { name: 'Yes' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toEqual({
        audience: 'restaurant',
        slug: 'add-a-dish',
        helpful: true,
      })
    await expect.element(screen.getByText(/Thank you/)).toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: 'Yes' }))
      .toHaveAttribute('aria-pressed', 'true')
  })
})

describe('The "?" sheet', () => {
  afterEach(() => fake.restore())

  it('opens the article of this screen, and articles it links to', async () => {
    fake = fakeApi({ 'GET /t/1/help': { data: { data: library } } })
    const screen = await renderWithQuery(
      <HelpSheet
        source={{ audience: 'restaurant', tenantId: 1 }}
        open
        onOpenChange={vi.fn()}
      />
    )
    await expect
      .element(screen.getByRole('heading', { name: 'Add a dish' }))
      .toBeVisible()
    await userEvent.click(screen.getByRole('link', { name: 'Dish photos' }))
    await expect
      .element(screen.getByRole('heading', { name: 'Dish photos' }))
      .toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Back' }))
    await expect
      .element(screen.getByRole('heading', { name: 'Add a dish' }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: 'Open the Help centre' }))
      .toHaveAttribute('href', '/admin/help?a=add-a-dish')
  })
})
