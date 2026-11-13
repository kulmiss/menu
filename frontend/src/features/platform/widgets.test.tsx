import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import PlatformWidgets from './widgets'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const all = {
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
  'GET /platform/widgets/follow-ups': {
    data: {
      data: [
        {
          id: 4,
          name: 'Kaldi Corner',
          date: '2026-11-10',
          reason: 'Renewal call',
          overdue: true,
          tags: [{ id: 1, name: 'At risk', color: '#ca8a04' }],
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

  it('shows the to-do widgets', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect.element(screen.getByText('Abebe Cafe').first()).toBeVisible()
    await expect.element(screen.getByText('Quiet Place')).toBeVisible()
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

  it('filters expiring by 7 or 14 days', async () => {
    fake = fakeApi(all)
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect.element(screen.getByText(/trial ends in 3 days/)).toBeVisible()
    expect(screen.container.textContent).not.toContain('Lalibela subscription')
    await userEvent.click(screen.getByRole('button', { name: 'Next 14 days' }))
    await expect
      .element(screen.getByText(/subscription ends in 12 days/))
      .toBeVisible()
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
      'GET /platform/widgets/plan-mix': { status: 500, data: {} },
    })
    const screen = await renderWithQuery(<PlatformWidgets />)

    await expect
      .element(screen.getByText('This part could not be loaded.'))
      .toBeVisible()
    await expect.element(screen.getByText('Quiet Place')).toBeVisible()
    await expect.element(screen.getByText('8.3.12')).toBeVisible()
    // CRM follow-ups (owner, 2026-11-13)
    await expect.element(screen.getByText('Follow-ups due')).toBeVisible()
    await expect
      .element(screen.getByText('overdue since 2026-11-10'))
      .toBeVisible()
  })
})
