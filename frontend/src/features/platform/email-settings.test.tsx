import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
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

  it('sends the test to the address typed, and shows where it went or the full error', async () => {
    let fail = false
    fake = fakeApi({
      'GET /platform/settings/mail': { data: { data: saved } },
      'POST /platform/settings/mail/test': (req) =>
        fail
          ? {
              status: 422,
              data: {
                message:
                  'The test e-mail to kitchen@example.org could not be sent. The mail server said: Connection could not be established with host "smtp.example.com:587": stream_socket_client(): Unable to connect (Connection refused)',
                code: 'mail.failed',
              },
            }
          : {
              data: {
                data: {
                  sent_to: (JSON.parse(String(req.body)) as { to: string }).to,
                },
                message: `Test e-mail sent to ${(JSON.parse(String(req.body)) as { to: string }).to}. Check the inbox (and the spam folder).`,
              },
            },
    })
    const screen = await renderWithQuery(<EmailSettings />, {
      me: meFixture({ email: 'admin@example.com' }),
    })

    // Pre-filled with my own address.
    const to = screen.getByLabelText('Send test email to')
    await expect.element(to).toHaveValue('admin@example.com')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('Test e-mail sent to admin@example.com.')

    // Another address; Enter sends the test (it doesn't save the settings).
    await userEvent.fill(to, 'kitchen@example.org')
    await userEvent.keyboard('{Enter}')
    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('Test e-mail sent to kitchen@example.org.')
    const posts = fake.calls.filter((c) => c.method === 'POST')
    expect(posts.map((c) => c.data)).toEqual([
      { to: 'admin@example.com' },
      { to: 'kitchen@example.org' },
    ])
    expect(fake.calls.some((c) => c.method === 'PUT')).toBe(false)

    // The whole server error, with the address.
    fail = true
    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent(
        'The test e-mail to kitchen@example.org could not be sent. The mail server said: Connection could not be established with host "smtp.example.com:587": stream_socket_client(): Unable to connect (Connection refused)'
      )

    // A typo is caught before anything is sent.
    await userEvent.fill(to, 'kitchen@example')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Type a valid e-mail address')
    expect(fake.calls.filter((c) => c.method === 'POST')).toHaveLength(3)
  })

  it('says when the limit of 5 tests a minute is reached', async () => {
    fake = fakeApi({
      'GET /platform/settings/mail': { data: { data: saved } },
      'POST /platform/settings/mail/test': {
        status: 429,
        data: { message: 'Too Many Attempts.' },
      },
    })
    const screen = await renderWithQuery(<EmailSettings />, {
      me: meFixture({ email: 'admin@example.com' }),
    })
    await expect
      .element(screen.getByLabelText('Send test email to'))
      .toHaveValue('admin@example.com')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send test email' })
    )
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Too many test e-mails. Wait a minute')
  })
})
