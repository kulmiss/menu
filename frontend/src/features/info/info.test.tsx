import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { InfoPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const PROFILE = {
  description: {},
  address: {},
  lat: null,
  lng: null,
  maps_url: null,
  phone: null,
  whatsapp: null,
  telegram: null,
  email: null,
  socials: {},
  wifi_name: null,
  wifi_password: null,
  show_wifi: false,
}

let fake: ReturnType<typeof fakeApi>

async function render() {
  fake = fakeApi({
    'GET /t/1/profile': { data: { data: PROFILE } },
    'PUT /t/1/profile': { data: { data: PROFILE } },
    'GET /t/1/branches': { data: { data: [] } },
    'GET /t/1/hours': { data: { data: [] } },
    'PUT /t/1/hours': { data: { data: [] } },
    'GET /t/1/closures': { data: { data: [] } },
  })
  return renderWithQuery(<InfoPage />, {
    me: meFixture({ tenants: [tenantFixture()] }),
  })
}

describe('InfoPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('builds a week: Monday hours copied to Tuesday–Friday, then saved', async () => {
    const screen = await render()
    await userEvent.click(
      screen.getByRole('button', { name: 'Add hours' }).first()
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Copy to Tuesday–Friday' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save hours' }))

    await expect
      .poll(
        () =>
          fake.calls.find((c) => c.method === 'PUT' && c.url === '/t/1/hours')
            ?.data
      )
      .toEqual({
        hours: [1, 2, 3, 4, 5].map((weekday) => ({
          weekday,
          opens_at: '08:00',
          closes_at: '22:00',
        })),
      })
  })

  it('marks a closing time after midnight as the next day', async () => {
    const screen = await render()
    await userEvent.click(
      screen.getByRole('button', { name: 'Add hours' }).first()
    )
    await userEvent.fill(screen.getByLabelText('Monday closes'), '02:00')

    await expect.element(screen.getByText('next day')).toBeVisible()
  })

  it('sends empty contact fields as blank, not as empty text', async () => {
    const screen = await render()
    await userEvent.type(screen.getByLabelText('Phone'), '+251 11 555 0100')
    await userEvent.click(
      screen.getByRole('button', { name: 'Save', exact: true })
    )

    await expect
      .poll(
        () =>
          fake.calls.find((c) => c.method === 'PUT' && c.url === '/t/1/profile')
            ?.data
      )
      .toMatchObject({ phone: '+251 11 555 0100', email: null, socials: {} })
  })
})
