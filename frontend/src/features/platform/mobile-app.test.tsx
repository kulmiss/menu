import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { MobileAppPage } from './mobile-app'

const settings = {
  restaurant_sign_in: true,
  super_admin_sign_in: true,
  sign_in_message: '',
  maintenance: false,
  maintenance_message: '',
  latest_version: '1.2.0',
  release_notes: '',
  min_version: '1.0.0',
  recommended_version: '',
  banner: {
    text: '',
    link: '',
    starts_at: null,
    ends_at: null,
    audience: 'all',
  },
  app_features: {
    biometric_lock: true,
    push: true,
    analytics: true,
    crash_reports: true,
  },
  links: {
    play_store: '',
    app_store: '',
    privacy: '',
    terms: '',
    support_whatsapp: '',
  },
}
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Mobile app', () => {
  afterEach(() => {
    fake.restore()
    window.history.replaceState(null, '', '#')
  })

  it('turns on maintenance and saves only the access fields', async () => {
    fake = fakeApi({
      'GET /platform/mobile-app': { data: { data: settings } },
      'PUT /platform/mobile-app': { data: { data: settings } },
    })
    const screen = await renderWithQuery(<MobileAppPage />)

    await userEvent.click(
      screen.getByRole('switch', { name: /Maintenance mode/ })
    )
    await expect
      .element(screen.getByText(/Maintenance mode is on/))
      .toBeVisible()
    await userEvent.fill(
      screen.getByLabelText('Maintenance message'),
      'Back at 10:00'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({
        restaurant_sign_in: true,
        super_admin_sign_in: true,
        sign_in_message: '',
        maintenance: true,
        maintenance_message: 'Back at 10:00',
      })
  })

  it('saves the minimum version for forced updates', async () => {
    fake = fakeApi({
      'GET /platform/mobile-app': { data: { data: settings } },
      'PUT /platform/mobile-app': { data: { data: settings } },
    })
    const screen = await renderWithQuery(<MobileAppPage />)
    await userEvent.click(screen.getByRole('tab', { name: 'Versions' }))
    await userEvent.fill(
      screen.getByLabelText('Minimum supported version'),
      '1.1.0'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({ min_version: '1.1.0', latest_version: '1.2.0' })
  })

  it('counts recipients before sending a push', async () => {
    fake = fakeApi({
      'GET /platform/mobile-app': { data: { data: settings } },
      'GET /platform/plans': { data: { data: [] } },
      'GET /platform/mobile-app/pushes': { data: { data: [] } },
      'POST /platform/mobile-app/pushes': {
        data: { data: { recipients: 4, with_app: 3 } },
      },
    })
    const screen = await renderWithQuery(<MobileAppPage />)
    await userEvent.click(screen.getByRole('tab', { name: 'Push' }))
    await expect.element(screen.getByText('Nothing sent yet.')).toBeVisible()
    await userEvent.fill(screen.getByLabelText('Title'), 'New feature')
    await userEvent.fill(
      screen.getByLabelText('Message'),
      'Try the new menu styles'
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Review and send' })
    )
    await expect.element(screen.getByText(/It goes to 4 people/)).toBeVisible()
    expect(fake.calls.find((c) => c.method === 'POST')?.data).toMatchObject({
      dry_run: true,
      audience: { to: 'all' },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Send now' }))
    await expect
      .poll(() => fake.calls.filter((c) => c.method === 'POST').length)
      .toBe(2)
    expect(
      fake.calls.filter((c) => c.method === 'POST')[1].data
    ).not.toHaveProperty('dry_run')
  })
})
