import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { BackupsPage } from './backups'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

describe('Backups', () => {
  afterEach(() => fake.restore())

  it('warns while backups are not encrypted (SECURITY_REPORT.md H4)', async () => {
    fake = fakeApi({
      'GET /platform/backups': {
        data: {
          data: {
            backups: [],
            disk_free: null,
            disk_total: null,
            problem: null,
            encryption: 'off',
            settings: {
              backup_enabled: true,
              backup_keep_daily: 7,
              backup_keep_weekly: 4,
              backup_keep_monthly: 3,
              backup_s3_enabled: true,
              backup_s3_endpoint: '',
              backup_s3_region: 'auto',
              backup_s3_bucket: '',
              backup_s3_prefix: '',
              backup_s3_key: '',
              backup_s3_secret_set: false,
            },
          },
        },
      },
    })
    const screen = await renderWithQuery(<BackupsPage />, { me: admin })

    await expect
      .element(screen.getByText(/Backups are not encrypted/))
      .toBeVisible()
    await expect.element(screen.getByText(/backup:key --write/)).toBeVisible()
  })

  it('lists backups, warns about problems and asks for the password before downloading', async () => {
    fake = fakeApi({
      'GET /platform/backups': {
        data: {
          data: {
            backups: [
              {
                id: 3,
                filename: 'b.zip',
                size: 61_440_000,
                kind: 'scheduled',
                status: 'ok',
                offsite: 'uploaded',
                error: null,
                created_at: '2026-11-13T02:30:00+03:00',
                finished_at: null,
              },
              {
                id: 2,
                filename: null,
                size: 0,
                kind: 'manual',
                status: 'failed',
                offsite: null,
                error: 'Disk full',
                created_at: '2026-11-12T02:30:00+03:00',
                finished_at: null,
              },
            ],
            disk_free: 10 * 1024 ** 3,
            disk_total: 40 * 1024 ** 3,
            problem: 'The last backup failed: Disk full',
            settings: {
              backup_enabled: true,
              backup_keep_daily: 7,
              backup_keep_weekly: 4,
              backup_keep_monthly: 3,
              backup_s3_enabled: false,
              backup_s3_endpoint: '',
              backup_s3_region: 'auto',
              backup_s3_bucket: '',
              backup_s3_prefix: 'menu-backups',
              backup_s3_key: '',
              backup_s3_secret_set: false,
            },
          },
        },
      },
      'POST /platform/backups/3/link': invalid({
        password: 'The password or code is not correct.',
      }),
      'POST /platform/backups': { status: 202, data: { data: {} } },
    })
    const screen = await renderWithQuery(<BackupsPage />, { me: admin })

    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Disk full')
    await expect
      .element(screen.getByText('Free disk space: 10.0 GB of 40.0 GB.'))
      .toBeVisible()
    await expect.element(screen.getByText('58.6 MB')).toBeVisible()
    await expect.element(screen.getByText('Server + off-site')).toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Download' }))
    await userEvent.fill(screen.getByLabelText('Password'), 'wrong')
    await userEvent.click(
      screen.getByRole('dialog').getByRole('button', { name: 'Download' })
    )
    await expect
      .element(screen.getByText('The password or code is not correct.'))
      .toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Use a two-factor code instead' })
    )
    await expect.element(screen.getByLabelText('Two-factor code')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await userEvent.click(screen.getByRole('button', { name: 'Back up now' }))
    await expect
      .poll(() =>
        fake.calls.some(
          (c) => c.method === 'POST' && c.url === '/platform/backups'
        )
      )
      .toBe(true)
  })
})
