import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { toast } from '@/lib/toast'
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
      'GET /super/backups': {
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
      'GET /super/backups': {
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
      'POST /super/backups/3/link': invalid({
        password: 'The password or code is not correct.',
      }),
      'POST /super/backups': { status: 202, data: { data: {} } },
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
          (c) => c.method === 'POST' && c.url === '/super/backups'
        )
      )
      .toBe(true)
  })

  it('sets up alerts: e-mail, Telegram token (never shown) and chat, and a test alert', async () => {
    fake = fakeApi({
      'GET /super/backups': {
        data: {
          data: {
            backups: [],
            disk_free: null,
            disk_total: null,
            problem: null,
            encryption: 'on',
            settings: {
              backup_enabled: true,
              backup_keep_daily: 7,
              backup_keep_weekly: 4,
              backup_keep_monthly: 3,
              backup_s3_enabled: false,
              backup_s3_endpoint: '',
              backup_s3_region: 'auto',
              backup_s3_bucket: '',
              backup_s3_prefix: '',
              backup_s3_key: '',
              backup_s3_secret_set: false,
              alerts_email: true,
              alerts_telegram_enabled: false,
              alerts_telegram_chat: '',
              alerts_telegram_token_set: false,
            },
          },
        },
      },
      'PUT /super/alerts': { data: { data: {} } },
      'POST /super/alerts/test': {
        data: { data: { email: 2, telegram: true } },
      },
    })
    const screen = await renderWithQuery(<BackupsPage />, { me: admin })

    await expect
      .element(screen.getByText('Alerts', { exact: true }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('switch', { name: 'E-mail every Super Admin' }))
      .toBeChecked()
    await userEvent.click(
      screen.getByRole('switch', {
        name: 'Also send alerts to a Telegram chat',
      })
    )
    await userEvent.fill(
      screen.getByLabelText('Bot token'),
      '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsawQ'
    )
    await userEvent.fill(screen.getByLabelText('Chat ID'), '-1001234567890')
    const saves = screen.getByRole('button', { name: 'Save' }).all()
    await userEvent.click(saves[saves.length - 1])
    await expect
      .poll(() => fake.calls.find((c) => c.url === '/super/alerts')?.data)
      .toEqual({
        alerts_email: true,
        alerts_telegram_enabled: true,
        alerts_telegram_chat: '-1001234567890',
        alerts_telegram_token: '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsawQ',
      })
    await expect.element(screen.getByLabelText('Bot token')).toHaveValue('')

    const success = vi.spyOn(toast, 'success')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send a test alert' })
    )
    await expect
      .poll(() => success.mock.calls[success.mock.calls.length - 1]?.[0])
      .toBe('Test sent by e-mail to 2 Super Admins and to Telegram.')
  })
})
