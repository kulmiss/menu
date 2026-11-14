import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { CrmSection } from './crm'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

describe('CRM section', () => {
  afterEach(() => fake.restore())

  it('toggles tags, sets a follow-up and adds a note', async () => {
    fake = fakeApi({
      'GET /platform/tags': {
        data: {
          data: [
            { id: 1, name: 'VIP', color: '#dc2626' },
            { id: 2, name: 'At risk', color: '#ca8a04' },
          ],
        },
      },
      'GET /platform/restaurants/7/notes': {
        data: {
          data: [
            {
              id: 3,
              body: 'Called, no answer.',
              author: { id: 9, name: 'Hana' },
              mine: false,
              created_at: '2026-11-12T10:00:00+03:00',
              edited: false,
            },
          ],
        },
      },
      'PUT /platform/restaurants/7/tags': { data: { data: [] } },
      'PUT /platform/restaurants/7/follow-up': { data: { data: {} } },
      'POST /platform/restaurants/7/notes': { status: 201, data: { data: {} } },
    })
    const screen = await renderWithQuery(
      <CrmSection
        id={7}
        tags={[{ id: 1, name: 'VIP', color: '#dc2626' }]}
        followUpOn={null}
        followUpReason={null}
      />,
      { me: admin }
    )

    await expect.element(screen.getByText('Called, no answer.')).toBeVisible()
    // Someone else's note: no edit or delete.
    expect(screen.container.textContent).not.toContain('Edit')

    await userEvent.click(screen.getByRole('button', { name: 'At risk' }))
    await expect
      .poll(
        () =>
          fake.calls.find((c) => c.url.endsWith('/tags') && c.method === 'PUT')
            ?.data
      )
      .toEqual({ tag_ids: [1, 2] })

    await userEvent.fill(screen.getByLabelText('Follow-up date'), '2026-11-20')
    await userEvent.fill(
      screen.getByLabelText('Reason (optional)'),
      'Renewal call'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.url.endsWith('/follow-up'))?.data)
      .toEqual({ date: '2026-11-20', reason: 'Renewal call' })

    await userEvent.fill(
      screen.getByLabelText('Add a note…'),
      'Wants Pro in January.'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Add note' }))
    await expect
      .poll(
        () =>
          fake.calls.find(
            (c) => c.url.endsWith('/notes') && c.method === 'POST'
          )?.data
      )
      .toEqual({ body: 'Wants Pro in January.' })
  })
})
