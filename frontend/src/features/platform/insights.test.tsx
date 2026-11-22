import { axeViolations } from '@/test-utils/axe'
import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { page, userEvent } from 'vitest/browser'
import PlatformInsights from './insights'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const kpi = (value: number, previous: number | null) => ({ value, previous })
const range = { from: '2026-10-15', to: '2026-11-13' }
const all = {
  'GET /super/insights/overview': {
    data: {
      data: {
        ...range,
        currency: 'ETB',
        demo: false,
        has_payments: true,
        kpis: {
          restaurants: kpi(1500, 1400),
          activated: kpi(30, 30),
          paused: kpi(12, null),
          deleted_restaurants: kpi(3, 3),
          upgrades: kpi(5, 5),
          downgrades: kpi(1, 1),
          cancellations: kpi(2, 2),
          arppu_minor: kpi(4_900_000, 4_900_000),
          dish_views: kpi(50_000, 50_000),
          dishes_added: kpi(700, 700),
          menu_changes: kpi(2_100, 2_100),
          order_adds: kpi(900, 900),
          order_lists: kpi(250, 250),
          active: kpi(900, 1000),
          new_restaurants: kpi(42, 0),
          paying: kpi(300, 300),
          free: kpi(1200, 1100),
          mrr_minor: kpi(1_470_000_00, 1_400_000_00),
          revenue_minor: kpi(1_500_000_00, 1_000_000_00),
          menu_views: kpi(123_456, 100_000),
          qr_scans: kpi(4_000, 5_000),
        },
        series: [
          {
            date: '2026-11-12',
            menu_views: 4000,
            qr_scans: 100,
            new_restaurants: 1,
            activated: 1,
            revenue_minor: 4_900_000,
            dishes_added: 300,
            menu_changes: 1000,
            order_lists: 100,
          },
          {
            date: '2026-11-13',
            menu_views: 5000,
            qr_scans: 120,
            new_restaurants: 2,
            activated: 0,
            revenue_minor: 0,
            dishes_added: 400,
            menu_changes: 1100,
            order_lists: 150,
          },
        ],
      },
    },
  },
  'GET /super/insights/growth': {
    data: {
      data: {
        ...range,
        weeks: [{ week: '2026-11-09', count: 12 }],
        funnel: [
          { key: 'signed_up', count: 42, percent: 100 },
          { key: 'paid', count: 6, percent: 14 },
        ],
        conversion: { signed_up: 42, paid: 6, percent: 14.3 },
        churn: { paying_at_start: 300, churned: 9, percent: 3 },
        cohorts: [{ month: '2026-10', signed_up: 40, active: 30, paying: 8 }],
      },
    },
  },
  'GET /super/insights/engagement': {
    data: {
      data: {
        total: 1500,
        edited_7d: 700,
        edited_30d: 1000,
        signed_in_7d: 800,
        signed_in_30d: 1150,
        last_sign_in_at: '2026-11-13T10:00:00+03:00',
        active_7d: 1100,
        active_30d: 1300,
        last_edit_at: '2026-11-13T09:00:00+03:00',
        edits_per_week: 3500.5,
        setup_avg: 64,
        setup_done: 420,
        bands: { healthy: 900, at_risk: 400, inactive: 200 },
        lists: {
          healthy: [],
          at_risk: [
            {
              id: 7,
              name: 'Sahan Grill',
              slug: 'sahan',
              score: 41,
              setup_pct: 43,
              views_30d: 12,
              edits_30d: 0,
              last_edit_at: null,
            },
          ],
          inactive: [
            {
              id: 9,
              name: 'Quiet Place',
              slug: 'quiet',
              score: 20,
              setup_pct: 14,
              views_30d: 0,
              edits_30d: 0,
              last_edit_at: null,
            },
          ],
        },
      },
    },
  },
  'GET /super/insights/usage': {
    data: {
      data: {
        total: 1500,
        features: [{ key: 'promotions', count: 300 }],
        styles: [{ style: 'grid', count: 700 }],
        limits: [{ key: 'limit_photos', count: 55 }],
      },
    },
  },
  'GET /super/insights/top': {
    data: {
      data: {
        currency: 'ETB',
        windows: { views: '30d', scans: '30d', revenue: '30d' },
        views: [{ id: 3, name: 'Abebe Cafe', total: 9000 }],
        scans: [],
        growth: [{ id: 4, name: 'Rising Star', total: 800 }],
        revenue: [],
      },
    },
  },
  'GET /super/insights/mix': {
    data: {
      data: {
        plans: [
          { id: 1, name: { en: 'Free' }, paid: false, count: 1200 },
          { id: 2, name: { en: 'Pro' }, paid: true, count: 300 },
        ],
        changes: [
          { week: '2026-11-02', upgrades: 3, downgrades: 1, cancellations: 0 },
          { week: '2026-11-09', upgrades: 2, downgrades: 0, cancellations: 2 },
        ],
      },
    },
  },
  'GET /super/insights/reach': {
    data: {
      data: {
        total: 1500,
        styles: [
          { key: 'grid', count: 700 },
          { key: 'juice', count: 80 },
        ],
        languages: [
          { key: 'en', count: 1000 },
          { key: 'am', count: 500 },
        ],
        places: [{ key: 'Addis Ababa', count: 1400 }],
      },
    },
  },
  'GET /super/insights/activation': {
    data: {
      data: {
        ...range,
        steps: [
          { key: 'signed_up', count: 42, percent: 100 },
          { key: 'dishes', count: 30, percent: 71 },
          { key: 'style', count: 21, percent: 50 },
          { key: 'published', count: 15, percent: 36 },
          { key: 'views_100', count: 4, percent: 10 },
        ],
      },
    },
  },
  'GET /super/insights/admins': {
    data: {
      data: {
        members: 1800,
        owners: 1500,
        managers: 200,
        staff: 100,
        with_2fa: 360,
        percent_2fa: 20,
        owners_percent_2fa: 18,
        active_7d: 650,
        active_30d: 1000,
        signed_in_7d: 500,
        signed_in_30d: 900,
      },
    },
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin insights', () => {
  afterEach(() => fake.restore())

  it('shows KPIs with trends, each linking to the matching table', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformInsights f={{ plan: 2 }} />)

    await expect.element(screen.getByText('123,456')).toBeVisible()
    await expect.element(screen.getByText('+23% vs previous')).toBeVisible()
    await expect.element(screen.getByText('-20% vs previous')).toBeVisible()
    await expect.element(screen.getByText('none before')).toBeVisible()
    await expect.element(screen.getByText('1,470,000 ETB')).toBeVisible()
    const paying = screen.getByRole('link', { name: /Paying\s*300/ })
    await expect
      .element(paying)
      .toHaveAttribute('href', '/platform/$section?plan=2&usage=paying')
    await expect
      .element(screen.getByRole('link', { name: /New restaurants/ }))
      .toHaveAttribute(
        'href',
        '/platform/$section?plan=2&from=2026-10-15&to=2026-11-13'
      )
    await expect
      .element(
        screen.getByRole('img', {
          name: 'Menu views and QR scans: Menu views 9,000, QR scans 220',
        })
      )
      .toBeVisible()
    // The dashboard's plan and range go to every widget.
    expect(fake.calls.map((c) => c.url)).toContain(
      '/super/insights/overview?days=30&plan=2'
    )
  })

  it('shows growth, health bands, usage, top and teams', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(
      <PlatformInsights f={{ from: '2026-10-15', to: '2026-11-13' }} />
    )

    await expect.element(screen.getByText('14.3%')).toBeVisible()
    await expect
      .element(screen.getByText('9 of 300 paying did not renew'))
      .toBeVisible()
    await expect.element(screen.getByText('Sahan Grill')).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: /Inactive\s*200/ })
    )
    await expect
      .element(screen.getByRole('link', { name: /Quiet Place/ }))
      .toHaveAttribute('href', '/platform/$section?open=9')
    await expect
      .element(screen.getByRole('link', { name: /Promotions/ }))
      .toHaveAttribute('href', '/platform/$section?usage=promotions')
    await expect.element(screen.getByText('Photo limit reached')).toBeVisible()
    await expect.element(screen.getByText('Abebe Cafe')).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Growth', exact: true })
    )
    await expect.element(screen.getByText('+800')).toBeVisible()
    await expect
      .element(screen.getByText('Teams that signed in, 7 days'))
      .toBeVisible()
    await expect.element(screen.getByText('1,150')).toBeVisible()
    await expect
      .element(screen.getByText('Signed in, 30 days', { exact: true }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('progressbar', { name: 'Two-factor on' }))
      .toHaveAttribute('aria-valuenow', '20')
    await expect
      .element(screen.getByRole('link', { name: /Oct 2026/ }))
      .toHaveAttribute(
        'href',
        '/platform/$section?from=2026-10-01&to=2026-10-31'
      )
    expect(fake.calls.map((c) => c.url)).toContain(
      '/super/insights/growth?from=2026-10-15&to=2026-11-13'
    )
  })

  it('lets one widget fail without breaking the others', async () => {
    fake = fakeApi({
      ...all,
      'GET /super/insights/usage': { status: 500, data: {} },
    })
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)

    await expect
      .element(screen.getByText('This part could not be loaded.'))
      .toBeVisible()
    await expect.element(screen.getByText('123,456')).toBeVisible()
  })
})

describe('Super Admin insights, more numbers (owner, 2026-11-22)', () => {
  afterEach(() => {
    fake.restore()
    vi.restoreAllMocks()
  })
  const overview = all['GET /super/insights/overview'].data.data

  it('groups the numbers: restaurants, plans, money, guests, engagement', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    for (const name of [
      'Restaurants',
      'Plans',
      'Money',
      'Guests',
      'Engagement',
    ])
      await expect
        .element(screen.getByRole('heading', { name, exact: true, level: 3 }))
        .toBeVisible()
    // "Paused now" has no history: no change shown.
    const paused = screen.getByRole('link', { name: /Paused now/ })
    await expect.element(paused).toHaveTextContent(/^Paused now12$/)
    await expect
      .element(paused)
      .toHaveAttribute(
        'href',
        '/platform/$section?state=read_only%2Cunavailable%2Csuspended'
      )
    await expect
      .element(
        screen.getByRole('link', { name: /^Per paying restaurant49,000 ETB/ })
      )
      .toBeVisible()
    await expect
      .element(
        screen.getByText(
          'Unique visitors and guest devices are not collected (no tracking of guests).'
        )
      )
      .toBeVisible()
    await expect
      .element(screen.getByText('"My order" lists shown'))
      .toBeVisible()
  })

  it('hides the changes when "Compare" is off', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(
      <PlatformInsights f={{ compare: false }} />
    )
    await expect.element(screen.getByText('123,456')).toBeVisible()
    expect(screen.container.textContent).not.toContain('vs previous')
    expect(screen.container.textContent).not.toContain('none before')
  })

  it('asks every card for the demo restaurants and says so when included', async () => {
    fake = fakeApi({
      ...all,
      'GET /super/insights/overview': {
        data: { data: { ...overview, demo: true } },
      },
    })
    const screen = await renderWithQuery(
      <PlatformInsights f={{ demo: true }} />
    )
    await expect
      .element(
        screen.getByText(
          'Demo data included: the website demo restaurants are counted.'
        )
      )
      .toBeVisible()
    const urls = fake.calls.map((c) => c.url)
    for (const w of ['overview', 'growth', 'mix', 'reach', 'activation', 'top'])
      expect(urls).toContain(`/super/insights/${w}?days=30&demo=1`)
  })

  it('says "No payments yet" instead of zeros', async () => {
    fake = fakeApi({
      ...all,
      'GET /super/insights/overview': {
        data: { data: { ...overview, has_payments: false } },
      },
    })
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    await expect.element(screen.getByText(/^No payments yet\./)).toBeVisible()
    expect(screen.container.textContent).not.toContain('1,470,000 ETB')
    expect(screen.container.textContent).not.toContain('Revenue per month')
  })

  it('shows activation, plans, styles, languages and places', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    await expect
      .element(screen.getByText('Published (first guest view)'))
      .toBeVisible()
    await expect
      .element(screen.getByText('100 guest views', { exact: true }))
      .toBeVisible()
    await expect.element(screen.getByText('Pro', { exact: true })).toBeVisible()
    await expect.element(screen.getByText('Free · free')).toBeVisible()
    await expect
      .element(screen.getByText('T · Juice & Drinks', { exact: true }))
      .toBeVisible()
    await expect.element(screen.getByText('Amharic')).toBeVisible()
    await expect.element(screen.getByText('Addis Ababa')).toBeVisible()
    await expect
      .element(
        screen.getByRole('img', {
          name: 'Plan changes per week: Upgrades 5, Downgrades 1, Cancellations 2',
        })
      )
      .toBeVisible()
  })

  it('shows a small line for each top restaurant', async () => {
    const top = all['GET /super/insights/top'].data.data
    fake = fakeApi({
      ...all,
      'GET /super/insights/top': {
        data: {
          data: {
            ...top,
            views: [
              { id: 3, name: 'Abebe Cafe', total: 9000, spark: [1, 5, 3] },
            ],
          },
        },
      },
    })
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    await expect
      .element(
        screen.getByRole('img', { name: 'Menu views per day for Abebe Cafe' })
      )
      .toBeVisible()
  })

  it('says why a card is empty', async () => {
    const act = all['GET /super/insights/activation'].data.data
    fake = fakeApi({
      ...all,
      'GET /super/insights/activation': {
        data: {
          data: {
            ...act,
            steps: act.steps.map((s) => ({ ...s, count: 0, percent: 0 })),
          },
        },
      },
    })
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    await expect
      .element(screen.getByText('No restaurant signed up in this period.'))
      .toBeVisible()
  })

  it('explains each card with an "i" button and exports its data as CSV', async () => {
    fake = fakeApi(all)
    let blob: Blob | null = null
    vi.spyOn(URL, 'createObjectURL').mockImplementation((b) => {
      blob = b as Blob
      return 'blob:test'
    })
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)

    await userEvent.click(
      screen.getByRole('button', { name: 'About Activation' })
    )
    await expect
      .element(
        page.getByText(/^Restaurants that signed up in the chosen period/)
      )
      .toBeVisible()
    await userEvent.keyboard('{Escape}')

    await userEvent.click(
      screen.getByRole('button', { name: 'Export Activation as CSV' })
    )
    await expect.poll(() => blob).not.toBeNull()
    // A byte-order mark first, so Excel reads it as UTF-8.
    const bytes = new Uint8Array(await (blob as unknown as Blob).arrayBuffer())
    expect([...bytes.slice(0, 3)]).toEqual([0xef, 0xbb, 0xbf])
    const text = new TextDecoder().decode(bytes)
    expect(text).toBe(
      'Step,Restaurants,%\r\nSigned up,42,100\r\nAdded dishes,30,71\r\nChose a style,21,50\r\nPublished (first guest view),15,36\r\n100 guest views,4,10\r\n'
    )

    // Every card has both.
    for (const title of [
      'Overview',
      'Plans',
      'Growth',
      'Feature use',
      'Top restaurants',
    ])
      await expect
        .element(screen.getByRole('button', { name: `About ${title}` }))
        .toBeVisible()
  })

  it('has no accessibility problems', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)
    await expect.element(screen.getByText('Addis Ababa')).toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
  })
})
