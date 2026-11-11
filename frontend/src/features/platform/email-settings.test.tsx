import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { type MailSettings } from './api'
import { EmailSettings } from './email-settings'

const saved: MailSettings = {
  source: 'platform',
  host: 'smtp.example.com',
  port: 587,
  encryption: 'tls',
  username: 'menu@example.com',
  has_password: true,
  from_address: 'hello@example.com',
  from_name: 'Menu Hub',
  env_host: 'mail',
  env_mailer: 'smtp',
}
let fake: ReturnType<typeof fakeApi>

describe('Settings → Email', () => {
  afterEach(() => fake.restore())

  it('says when the .env settings are used', async () => {
    fake = fakeApi({
      'GET /platform/settings/mail': {
        data: {
          data: { ...saved, source: 'env', host: '', has_password: false },
        },
      },
    })
    const screen = await renderWithQuery(<EmailSettings />)

    await expect
      .element(screen.getByText(/use the server's \.env settings \(mail\)/))
      .toBeVisible()
  })

  it('never shows the saved password and keeps it when the field stays empty', async () => {
    fake = fakeApi({
      'GET /platform/settings/mail': { data: { data: saved } },
      'PUT /platform/settings/mail': (req) => ({
        data: { data: { ...saved, ...JSON.parse(String(req.body)) } },
      }),
    })
    const screen = await renderWithQuery(<EmailSettings />)

    const password = screen.getByLabelText('Password')
    await expect.element(password).toHaveValue('')
    await expect.element(password).toHaveAttribute('placeholder', '••••••••')
    await expect.element(screen.getByText(/A password is saved/)).toBeVisible()

    await userEvent.clear(screen.getByLabelText('Port'))
    await userEvent.type(screen.getByLabelText('Port'), '2525')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({ host: 'smtp.example.com', port: 2525, password: null })
  })

  it('shows a clear success or error message for the test e-mail', async () => {
    let fail = false
    fake = fakeApi({
      'GET /platform/settings/mail': { data: { data: saved } },
      'POST /platform/settings/mail/test': () =>
        fail
          ? {
              status: 422,
              data: {
                message:
                  'The test e-mail could not be sent: Connection could not be established with host "smtp.example.com:587".',
                code: 'mail.failed',
              },
            }
          : {
              data: {
                data: { sent_to: 'admin@example.com' },
                message:
                  'Test e-mail sent to admin@example.com. Check the inbox (and the spam folder).',
              },
            },
    })
    const screen = await renderWithQuery(<EmailSettings />)

    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('Test e-mail sent to admin@example.com.')

    fail = true
    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Connection could not be established')
  })
})
