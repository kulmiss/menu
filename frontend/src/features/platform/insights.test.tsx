import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import PlatformInsights from './insights'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const kpi = (value: number, previous: number) => ({ value, previous })
const range = { from: '2026-10-15', to: '2026-11-13' }
const all = {
  'GET /platform/insights/overview': {
    data: {
      data: {
        ...range,
        currency: 'ETB',
        kpis: {
          restaurants: kpi(1500, 1400),
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
            revenue_minor: 4_900_000,
          },
          {
            date: '2026-11-13',
            menu_views: 5000,
            qr_scans: 120,
            new_restaurants: 2,
            revenue_minor: 0,
          },
        ],
      },
    },
  },
  'GET /platform/insights/growth': {
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
  'GET /platform/insights/engagement': {
    data: {
      data: {
        total: 1500,
        edited_7d: 700,
        edited_30d: 1000,
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
  'GET /platform/insights/usage': {
    data: {
      data: {
        total: 1500,
        features: [{ key: 'promotions', count: 300 }],
        styles: [{ style: 'grid', count: 700 }],
        limits: [{ key: 'limit_photos', count: 55 }],
      },
    },
  },
  'GET /platform/insights/top': {
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
  'GET /platform/insights/admins': {
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
      .element(screen.getByRole('img', { name: 'Menu views: Total 9,000' }))
      .toBeVisible()
    // The dashboard's plan and range go to every widget.
    expect(fake.calls.map((c) => c.url)).toContain(
      '/platform/insights/overview?days=30&plan=2'
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
    await userEvent.click(screen.getByRole('button', { name: 'Growth' }))
    await expect.element(screen.getByText('+800')).toBeVisible()
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
      '/platform/insights/growth?from=2026-10-15&to=2026-11-13'
    )
  })

  it('lets one widget fail without breaking the others', async () => {
    fake = fakeApi({
      ...all,
      'GET /platform/insights/usage': { status: 500, data: {} },
    })
    const screen = await renderWithQuery(<PlatformInsights f={{}} />)

    await expect
      .element(screen.getByText('This part could not be loaded.'))
      .toBeVisible()
    await expect.element(screen.getByText('123,456')).toBeVisible()
  })
})
