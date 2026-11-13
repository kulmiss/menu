import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { InvoicesPage } from './invoices'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
const invoice = {
  id: 5,
  number: 'INV-2026-0005',
  status: 'paid',
  issued_at: '2026-11-13T09:00:00+03:00',
  tenant: { id: 7, name: 'Sahan Grill' },
  owner_email: 'ayaan@example.com',
  plan: 'Pro',
  period_start: '2026-11-13',
  period_end: '2026-12-13',
  total_minor: 120000,
  currency: 'ETB',
  payment_id: 3,
  void_reason: null,
  replaces_id: null,
  emailed_at: null,
}
let fake: ReturnType<typeof fakeApi>

describe('Invoices', () => {
  afterEach(() => fake.restore())

  it('lists invoices with a PDF link and voids one with a reason', async () => {
    fake = fakeApi({
      'GET /platform/invoices': { data: { data: [invoice] } },
      'GET /platform/invoices?q=sahan': { data: { data: [invoice] } },
      'GET /platform/plans': { data: { data: [] } },
      'POST /platform/invoices/5/void': {
        data: { data: { void: { ...invoice, status: 'void' }, new: null } },
      },
    })
    const screen = await renderWithQuery(<InvoicesPage />, { me: admin })

    const link = screen.getByRole('link', {
      name: /Open invoice INV-2026-0005/,
    })
    await expect
      .element(link)
      .toHaveAttribute('href', '/api/v1/platform/invoices/5/pdf')
    await expect.element(screen.getByText('1,200 ETB')).toBeVisible()
    await userEvent.fill(
      screen.getByRole('searchbox', { name: 'Number, restaurant or e-mail' }),
      'sahan'
    )
    await expect
      .poll(() =>
        fake.calls.some((c) =>
          c.url.startsWith('/platform/invoices?q=sahan&page=1')
        )
      )
      .toBe(true)

    await userEvent.click(screen.getByRole('button', { name: 'Void' }))
    await userEvent.fill(screen.getByLabelText('Reason'), 'Wrong amount')
    await userEvent.click(
      screen.getByRole('dialog').getByRole('button', { name: 'Void' })
    )
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'POST')?.data)
      .toEqual({ reason: 'Wrong amount', reissue: true })
  })
})
