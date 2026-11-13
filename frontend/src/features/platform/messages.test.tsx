import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { MessagesPage } from './messages'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

describe('Messages', () => {
  afterEach(() => fake.restore())

  it('writes an e-mail with a placeholder, counts recipients, previews, tests and sends', async () => {
    fake = fakeApi({
      'GET /platform/campaigns': { data: { data: [] } },
      'GET /platform/settings': { data: { data: { campaign_rate: 30 } } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/tags': { data: { data: [] } },
      'POST /platform/campaigns/count': { data: { data: { count: 12 } } },
      'POST /platform/campaigns/preview': {
        data: {
          data: {
            subject: 'Hello Buna',
            html: '<p>Hi <strong>Selam</strong></p>',
            sample: 'Buna & Bread',
          },
        },
      },
      'POST /platform/campaigns/test': {
        data: { data: { sent_to: 'me@example.com' } },
      },
      'POST /platform/campaigns': { status: 201, data: { data: { id: 4 } } },
      'GET /platform/campaigns/4': {
        data: {
          data: {
            id: 4,
            subject: 'Hello {restaurant_name}',
            status: 'sending',
            recipients: 12,
            sent: 0,
            failed: 0,
            pending: 12,
            created_at: '2026-11-13T10:00:00+03:00',
            finished_at: null,
            body: 'x',
            filters: {},
            list: [
              {
                id: 1,
                email: 'a@b.co',
                name: 'A',
                restaurant: 'Buna',
                status: 'pending',
                attempts: 0,
                error: null,
                sent_at: null,
              },
            ],
          },
        },
      },
    })
    const screen = await renderWithQuery(<MessagesPage />, { me: admin })

    await userEvent.click(screen.getByRole('button', { name: 'New email' }))
    await userEvent.fill(
      screen.getByLabelText('Subject'),
      'Hello {restaurant_name}'
    )
    await userEvent.fill(screen.getByLabelText('Message'), 'Hi ')
    await userEvent.click(screen.getByRole('button', { name: '{owner_name}' }))
    await expect
      .element(screen.getByLabelText('Message'))
      .toHaveValue('Hi {owner_name}')
    await expect
      .element(screen.getByText('Will be sent to 12 owners.'))
      .toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Preview' }))
    await expect
      .element(screen.getByText('As Buna & Bread will see it.'))
      .toBeVisible()
    await userEvent.click(
      screen.getByRole('dialog').getByRole('button', { name: 'Close' })
    )
    await expect.element(screen.getByRole('dialog')).not.toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('button', { name: 'Send me a test' })
    )
    await expect
      .poll(
        () => fake.calls.find((c) => c.url === '/platform/campaigns/test')?.data
      )
      .toEqual({
        subject: 'Hello {restaurant_name}',
        body: 'Hi {owner_name}',
        filters: {},
      })

    await userEvent.click(
      screen.getByRole('button', { name: 'Send to 12 owners' })
    )
    await userEvent.click(
      screen
        .getByRole('dialog')
        .getByRole('button', { name: 'Send to 12 owners' })
    )
    await expect
      .poll(() =>
        fake.calls.some(
          (c) => c.method === 'POST' && c.url === '/platform/campaigns'
        )
      )
      .toBe(true)
    await expect.element(screen.getByText('a@b.co')).toBeVisible()
  })
})
