import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import PlatformWidgets from './widgets'

const months = Array.from({ length: 12 }, (_, i) => ({
  month: `2026-${String(i + 1).padStart(2, '0')}`,
  total: i === 11 ? 150000 : i === 10 ? 100000 : 0,
}))
const funnel = (days: number, n: number) => ({
  data: {
    data: {
      days,
      steps: [
        { key: 'signups', count: n, percent: null },
        { key: 'menu', count: n / 2, percent: 50 },
        { key: 'scanned', count: n / 4, percent: 50 },
        { key: 'paying', count: 1, percent: 40 },
      ],
    },
  },
})
const all = {
  'GET /platform/widgets/revenue': {
    data: {
      data: {
        currency: 'ETB',
        this_month: 150000,
        last_month: 100000,
        mrr: 90000,
        months,
      },
    },
  },
  'GET /platform/widgets/expiring': {
    data: {
      data: [
        {
          id: 1,
          name: 'Abebe Cafe',
          slug: 'abebe',
          kind: 'trial',
          days_left: 3,
        },
        {
          id: 2,
          name: 'Lalibela',
          slug: 'lal',
          kind: 'subscription',
          days_left: 12,
        },
      ],
    },
  },
  'GET /platform/widgets/inactive': {
    data: {
      data: [
        { id: 5, name: 'Quiet Place', slug: 'q', last_activity: '2026-10-01' },
      ],
    },
  },
  'GET /platform/widgets/funnel?days=30': funnel(30, 20),
  'GET /platform/widgets/funnel?days=90': funnel(90, 64),
  'GET /platform/widgets/top': {
    data: {
      data: {
        views: [{ id: 1, name: 'Abebe Cafe', total: 1200 }],
        scans: [],
        plans: [
          { id: 2, name: 'Lalibela', plan: { en: 'Pro' }, price_minor: 50000 },
        ],
      },
    },
  },
  'GET /platform/widgets/signups': {
    data: {
      data: [
        {
          id: 9,
          name: 'New Spot',
          slug: 'new',
          created_at: '2026-11-10T10:00:00Z',
          percent: 33,
          steps: {
            logo: true,
            banner: false,
            dishes: true,
            photos: false,
            hours: false,
            qr: false,
          },
        },
      ],
    },
  },
  'GET /platform/widgets/health': {
    data: {
      data: {
        disk_free: 5 * 1024 ** 3,
        disk_total: 100 * 1024 ** 3,
        disk_free_percent: 5,
        photos_bytes: 1024 ** 3,
        photos_count: 420,
        cron_minutes_ago: 25,
        queued: 0,
        failed: 0,
        errors_today: 0,
        php: '8.3.12',
        laravel: '13.1.0',
        warnings: ['cron', 'disk'],
      },
    },
  },
  'GET /platform/widgets/plan-mix': {
    data: {
      data: {
        plans: [
          { id: 1, name: { en: 'Basic' }, total: 120 },
          { id: null, name: null, total: 80 },
        ],
        upgrades: 4,
        downgrades: 1,
      },
    },
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin dashboard widgets', () => {
  afterEach(() => fake.restore())

  it('shows all eight widgets', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect.element(screen.getByText('1,500 ETB')).toBeVisible()
    await expect.element(screen.getByText('+50% vs last month')).toBeVisible()
    await expect
      .element(screen.getByRole('img', { name: /Revenue, last 12 months/ }))
      .toBeVisible()
    await expect.element(screen.getByText('Abebe Cafe').first()).toBeVisible()
    await expect.element(screen.getByText('Quiet Place')).toBeVisible()
    await expect.element(screen.getByText('5 or more dishes')).toBeVisible()
    await expect.element(screen.getByText('Pro')).toBeVisible()
    await expect
      .element(screen.getByRole('progressbar', { name: 'Setup of New Spot' }))
      .toHaveAttribute('aria-valuenow', '33')
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent(/Cron has not run.*Less than 10% disk/)
    await expect.element(screen.getByText('8.3.12')).toBeVisible()
    await expect
      .element(
        screen.getByRole('img', { name: 'Plan mix: Basic 120, No plan 80' })
      )
      .toBeVisible()
    await expect
      .element(screen.getByText('This month: 4 upgrades, 1 downgrades'))
      .toBeVisible()
  })

  it('filters expiring by 7 or 14 days and switches the funnel period', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect.element(screen.getByText(/trial ends in 3 days/)).toBeVisible()
    expect(screen.container.textContent).not.toContain('Lalibela subscription')
    await userEvent.click(screen.getByRole('button', { name: 'Next 14 days' }))
    await expect
      .element(screen.getByText(/subscription ends in 12 days/))
      .toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Last 90 days' }))
    await expect.element(screen.getByText('64')).toBeVisible()
    expect(fake.calls.map((c) => c.url)).toContain(
      '/platform/widgets/funnel?days=90'
    )
  })

  it('sends an edited reminder to the owner', async () => {
    fake = fakeApi({
      ...all,
      'POST /platform/widgets/email/1': { data: { message: 'Email sent.' } },
    })
    const screen = await renderWithQuery(<PlatformWidgets />)

    await userEvent.click(screen.getByRole('button', { name: 'Remind' }))
    const subject = screen.getByLabelText('Subject')
    await expect
      .element(subject)
      .toHaveValue('Your Abebe Cafe menu plan ends soon')
    await expect
      .element(screen.getByLabelText('Message'))
      .toHaveDisplayValue(/ends in 3 days/)
    await userEvent.fill(subject, 'Renew please')
    await userEvent.click(screen.getByRole('button', { name: 'Send' }))

    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('Email sent.')
    const sent = fake.calls.find(
      (c) => c.method === 'POST' && c.url.includes('email')
    )
    expect(sent?.data).toMatchObject({
      subject: 'Renew please',
      reason: 'expiring',
    })
  })

  it('lets one widget fail without breaking the others', async () => {
    fake = fakeApi({
      ...all,
      'GET /platform/widgets/revenue': { status: 500, data: {} },
    })
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect
      .element(screen.getByText('This part could not be loaded.'))
      .toBeVisible()
    await expect.element(screen.getByText('Quiet Place')).toBeVisible()
    await expect.element(screen.getByText('8.3.12')).toBeVisible()
  })
})
