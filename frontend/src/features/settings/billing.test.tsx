import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { BillingSettings } from './billing'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>

describe('Settings → Billing', () => {
  afterEach(() => fake.restore())

  it('shows the plan, paid-until date and invoices to download', async () => {
    fake = fakeApi({
      'GET /t/1/billing': {
        data: {
          data: {
            plan: {
              name: 'Pro',
              price_minor: 120000,
              currency: 'ETB',
              interval: 'month',
            },
            paid_until: '2026-12-13',
            trial_ends_at: null,
            state: 'active',
            invoices: [
              {
                id: 5,
                number: 'INV-2026-0005',
                status: 'paid',
                issued_at: '2026-11-13T09:00:00+03:00',
                period_start: '2026-11-13',
                period_end: '2026-12-13',
                total_minor: 120000,
                currency: 'ETB',
              },
            ],
          },
        },
      },
    })
    const screen = await renderWithQuery(<BillingSettings />, {
      me: meFixture(),
    })

    await expect.element(screen.getByText('Pro')).toBeVisible()
    await expect.element(screen.getByText('Paid until')).toBeVisible()
    await expect
      .element(screen.getByRole('link', { name: /INV-2026-0005/ }))
      .toHaveAttribute('href', '/api/v1/t/1/billing/invoices/5/pdf')
  })
})
