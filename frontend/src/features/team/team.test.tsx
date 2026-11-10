import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { type Role } from '@/features/auth/api'
import { type Member } from './api'
import { TeamPage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
// The page header (search, theme, profile) is covered elsewhere.
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

const member = (
  id: number,
  userId: number,
  name: string,
  role: Role,
  prices = false
): Member => ({
  id,
  role,
  can_edit_prices: prices,
  user: {
    id: userId,
    name,
    email: `${name.split(' ')[0].toLowerCase()}@example.com`,
  },
  joined_at: '2026-11-01T00:00:00Z',
})

const MEMBERS = [
  member(10, 100, 'Selam Tesfaye', 'owner', true),
  member(11, 101, 'Dawit Bekele', 'manager', true),
  member(12, 102, 'Hana Girma', 'staff'),
]

let fake: ReturnType<typeof fakeApi>

function renderAs(
  role: Role,
  userId = 100,
  extra: Parameters<typeof fakeApi>[0] = {}
) {
  fake = fakeApi({
    'GET /t/1/members': { data: { data: MEMBERS } },
    'GET /t/1/invitations': {
      data: {
        data: [
          {
            id: 5,
            email: 'new@example.com',
            role: 'staff',
            can_edit_prices: false,
            expires_at: '2026-11-17T00:00:00Z',
          },
        ],
      },
    },
    ...extra,
  })
  return renderWithQuery(<TeamPage />, {
    me: meFixture({ id: userId, tenants: [tenantFixture({}, role)] }),
  })
}

describe('TeamPage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('lets the owner change managers and staff, but not themselves', async () => {
    const screen = await renderAs('owner')

    await expect.element(screen.getByText('Dawit Bekele')).toBeVisible()
    await expect
      .element(screen.getByRole('combobox', { name: 'Role of Dawit Bekele' }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('combobox', { name: 'Role of Hana Girma' }))
      .toBeVisible()
    expect(
      screen.container.querySelector('[aria-label="Role of Selam Tesfaye"]')
    ).toBeNull()
    await expect
      .element(screen.getByRole('button', { name: 'Remove Dawit Bekele' }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('button', { name: 'Invite' }))
      .toBeVisible()
  })

  it('lets a manager change staff only', async () => {
    const screen = await renderAs('manager', 101)

    await expect
      .element(screen.getByRole('combobox', { name: 'Role of Hana Girma' }))
      .toBeVisible()
    expect(
      screen.container.querySelector('[aria-label="Role of Selam Tesfaye"]')
    ).toBeNull()
    expect(
      screen.container.querySelector('[aria-label="Remove Selam Tesfaye"]')
    ).toBeNull()
    await expect
      .element(screen.getByRole('button', { name: 'Leave restaurant' }))
      .toBeVisible()
  })

  it('shows staff no team data at all', async () => {
    const screen = await renderAs('staff', 102)

    await expect
      .element(screen.getByText(/cannot open this page/))
      .toBeVisible()
    expect(screen.container.querySelector('button')).toBeNull()
    expect(fake.calls).toEqual([])
  })

  it('switches the price permission for staff', async () => {
    const screen = await renderAs('owner', 100, {
      'PATCH /t/1/members/12': {
        data: { data: { ...MEMBERS[2], can_edit_prices: true } },
      },
    })

    const hana = screen.getByRole('listitem').filter({ hasText: 'Hana Girma' })
    await userEvent.click(hana.getByRole('switch'))

    await vi.waitFor(() =>
      expect(fake.calls.find((c) => c.method === 'PATCH')?.data).toEqual({
        can_edit_prices: true,
      })
    )
    await expect.element(hana.getByRole('switch')).toBeChecked()
  })

  it('removes a member after confirming', async () => {
    const screen = await renderAs('owner', 100, {
      'DELETE /t/1/members/11': { status: 204 },
    })

    await userEvent.click(
      screen.getByRole('button', { name: 'Remove Dawit Bekele' })
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Remove from team' })
    )

    await expect
      .poll(() => screen.container.textContent)
      .not.toContain('Dawit Bekele')
  })

  it('invites by email and shows field errors from the server', async () => {
    let attempt = 0
    const screen = await renderAs('owner', 100, {
      'POST /t/1/invitations': () =>
        ++attempt === 1
          ? invalid({ email: 'This person is already on your team.' })
          : {
              status: 201,
              data: {
                data: {
                  id: 6,
                  email: 'amir@example.com',
                  role: 'manager',
                  can_edit_prices: false,
                  expires_at: '2026-11-17T00:00:00Z',
                },
              },
            },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Invite' }))
    const dialog = screen.getByRole('dialog')
    await userEvent.fill(
      dialog.getByRole('textbox', { name: 'Email' }),
      'dawit@example.com'
    )
    await userEvent.click(
      dialog.getByRole('button', { name: 'Send invitation' })
    )
    await expect
      .element(dialog.getByText('This person is already on your team.'))
      .toBeVisible()

    await userEvent.fill(
      dialog.getByRole('textbox', { name: 'Email' }),
      'amir@example.com'
    )
    await userEvent.click(
      dialog.getByRole('switch', { name: 'Can edit prices' })
    )
    await userEvent.click(
      dialog.getByRole('button', { name: 'Send invitation' })
    )

    await expect
      .poll(() => document.querySelector('[role="dialog"]'))
      .toBeNull()
    const posts = fake.calls.filter((c) => c.method === 'POST')
    expect(posts[1].data).toEqual({
      email: 'amir@example.com',
      role: 'staff',
      can_edit_prices: true,
    })
    // Each attempt is its own action, with its own key.
    expect(posts[0].headers.get('Idempotency-Key')).not.toBe(
      posts[1].headers.get('Idempotency-Key')
    )
  })

  it('lists pending invitations with a cancel button', async () => {
    const screen = await renderAs('owner', 100, {
      'DELETE /t/1/invitations/5': { status: 204 },
    })

    await userEvent.click(
      screen.getByRole('button', {
        name: 'Cancel the invitation for new@example.com',
      })
    )

    await expect
      .poll(() => screen.container.textContent)
      .not.toContain('new@example.com')
  })
})
