import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { type Appearance } from './api'
import { AppearancePage } from './index'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))
// The frame would load the test server's own page; keep the address, load nothing.
vi.mock('./api', async (orig) => {
  const real = await orig<typeof import('./api')>()
  return {
    ...real,
    previewUrl: (...args: Parameters<typeof real.previewUrl>) =>
      'about:blank#' + real.previewUrl(...args).split('?')[1],
  }
})

const APPEARANCE: Appearance = {
  primary_color: '#b4432a',
  font: 'system',
  style: 'grid',
  banner_text: null,
  show_prices: true,
  show_photos: true,
  show_descriptions: true,
  show_calories: true,
  theme_id: null,
  logo: null,
  cover: null,
  warnings: [],
  styles: ['editorial', 'grid', 'bistro'],
  banner: {
    editorial: { ratio: [16, 9], text: 'under' },
    grid: { ratio: [2, 1], text: 'over' },
    bistro: { ratio: [5, 2], text: 'under' },
  },
  themes: [
    {
      id: 1,
      name: 'Classic',
      primary: '#b4432a',
      font: 'system',
      style: 'bistro',
    },
    {
      id: 2,
      name: 'Bistro',
      primary: '#2f5d50',
      font: 'serif',
      style: 'editorial',
    },
  ],
}

let fake: ReturnType<typeof fakeApi>

function render(writable = true) {
  fake = fakeApi({
    'GET /t/1/appearance': { data: { data: APPEARANCE } },
    'PUT /t/1/appearance': (req) => ({
      data: {
        data: {
          ...APPEARANCE,
          ...JSON.parse(String(req.body)),
          logo: null,
          cover: null,
        },
      },
    }),
  })
  return renderWithQuery(<AppearancePage />, {
    me: meFixture({
      tenants: [
        tenantFixture({
          subscription: {
            state: writable ? 'active' : 'read_only',
            allows_writes: writable,
            shows_banner: !writable,
            stage_ends_at: null,
          },
        }),
      ],
    }),
  })
}

const frame = (screen: Awaited<ReturnType<typeof render>>) =>
  screen.container.querySelector('iframe') as HTMLIFrameElement

describe('AppearancePage', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('applies a theme to the live preview and saves it', async () => {
    const screen = await render()
    await expect
      .element(screen.getByRole('button', { name: 'Bistro' }))
      .toBeVisible()
    expect(frame(screen).src).toContain('style=grid')

    await userEvent.click(screen.getByRole('button', { name: 'Bistro' }))
    await expect.poll(() => frame(screen).src).toContain('style=editorial')
    expect(frame(screen).src).toContain('font=serif')
    expect(frame(screen).src).toContain('primary_color=%232f5d50')
    await expect.element(screen.getByText('Unsaved changes')).toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        primary_color: '#2f5d50',
        font: 'serif',
        style: 'editorial',
        theme_id: 2,
      })
    await expect
      .element(screen.getByText('Unsaved changes'))
      .not.toBeInTheDocument()
  })

  it('warns when the colour is hard to read on white', async () => {
    const screen = await render()
    const input = screen.getByRole('textbox', { name: 'Brand colour' })
    await userEvent.clear(input)
    await userEvent.type(input, '#fde047')

    await expect
      .element(screen.getByText(/hard to read on white/))
      .toBeVisible()
  })

  it('switches and the menu style change the preview', async () => {
    const screen = await render()
    await userEvent.click(screen.getByRole('switch', { name: 'Show prices' }))
    await userEvent.click(
      screen.getByRole('radio', { name: /C · Quiet bistro/ })
    )

    await expect.poll(() => frame(screen).src).toContain('show_prices=0')
    expect(frame(screen).src).toContain('style=bistro')
    await expect
      .element(screen.getByRole('radio', { name: /C · Quiet bistro/ }))
      .toBeChecked()
  })

  it('offers only the styles in the plan', async () => {
    APPEARANCE.styles = ['grid', 'bistro']
    const screen = await render()
    await expect
      .element(screen.getByRole('radio', { name: /A · Warm editorial/ }))
      .toBeDisabled()
    await expect
      .element(screen.getByText('Not included in your plan.'))
      .toBeVisible()
    // A theme with a style outside the plan keeps the current style.
    await userEvent.click(screen.getByRole('button', { name: 'Bistro' }))
    await expect.poll(() => frame(screen).src).toContain('font=serif')
    expect(frame(screen).src).toContain('style=grid')
    APPEARANCE.styles = ['editorial', 'grid', 'bistro']
  })

  it('fits the banner to the style and places the name on or below it', async () => {
    const cover = {
      id: 4,
      width: 1600,
      height: 800,
      dominant_color: '#884422',
      urls: { '320': '/media/1/c/320.webp' },
    }
    const original = APPEARANCE.cover
    APPEARANCE.cover = cover
    const screen = await render()

    await expect
      .element(screen.getByText('Recommended: 1600 × 800 px or larger.'))
      .toBeVisible()
    // B · Fresh grid puts the name on the banner by default.
    await expect
      .element(
        screen.getByRole('radio', { name: 'On the banner', exact: true })
      )
      .toBeChecked()

    await userEvent.click(
      screen.getByRole('radio', { name: 'Below the banner', exact: true })
    )
    await expect.poll(() => frame(screen).src).toContain('banner_text=under')

    await userEvent.click(
      screen.getByRole('radio', { name: /C · Quiet bistro/ })
    )
    await expect
      .element(screen.getByText('Recommended: 1600 × 640 px or larger.'))
      .toBeVisible()
    APPEARANCE.cover = original
  })

  it('is read-only when the subscription does not allow changes', async () => {
    const screen = await render(false)
    await expect
      .element(screen.getByRole('button', { name: 'Bistro' }))
      .toBeDisabled()
    await expect
      .element(screen.getByRole('button', { name: 'Save' }))
      .toBeDisabled()
  })
})
