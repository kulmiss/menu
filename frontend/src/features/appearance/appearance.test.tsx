import { axeViolations } from '@/test-utils/axe'
import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { page, userEvent } from 'vitest/browser'
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
  menu_layout: null,
  show_descriptions: true,
  show_calories: true,
  show_share: true,
  show_pdf_link: false,
  show_other_branches: true,
  show_logo: true,
  show_banner: true,
  show_order_list: true,
  menu_direction: 'auto',
  show_halal: false,
  ramadan_mode: false,
  photos_greyscale: false,
  mono_accent: false,
  show_decoration: true,
  theme_id: null,
  logo: null,
  cover: null,
  warnings: [],
  styles: [
    'editorial',
    'grid',
    'bistro',
    'minimal',
    'mesob',
    'lounge',
    'board',
    'arabic',
    'islamic',
    'somali',
    'mono',
    'colour',
    'coffee',
    'grill',
    'garden',
    'coastal',
    'luxe',
    'burger',
    'injera',
    'juice',
  ],
  banner: {
    editorial: { ratio: [16, 9], text: 'under' },
    grid: { ratio: [2, 1], text: 'over' },
    bistro: { ratio: [5, 2], text: 'under' },
    minimal: { ratio: [5, 2], text: 'under' },
    mesob: { ratio: [5, 2], text: 'under' },
    lounge: { ratio: [2, 1], text: 'over' },
    board: { ratio: [2, 1], text: 'under' },
    arabic: { ratio: [5, 2], text: 'under' },
    islamic: { ratio: [5, 2], text: 'under' },
    somali: { ratio: [2, 1], text: 'under' },
    mono: { ratio: [2, 1], text: 'under' },
    colour: { ratio: [5, 2], text: 'under' },
    coffee: { ratio: [5, 2], text: 'under' },
    grill: { ratio: [2, 1], text: 'under' },
    garden: { ratio: [5, 2], text: 'under' },
    coastal: { ratio: [2, 1], text: 'under' },
    luxe: { ratio: [2, 1], text: 'under' },
    burger: { ratio: [2, 1], text: 'under' },
    injera: { ratio: [5, 2], text: 'under' },
    juice: { ratio: [2, 1], text: 'under' },
  },
  style_layouts: {
    editorial: 'grid',
    grid: 'grid',
    bistro: 'list',
    minimal: 'text',
    mesob: 'list',
    lounge: 'list',
    board: 'list',
    arabic: 'list',
    islamic: 'grid',
    somali: 'list',
    mono: 'list',
    colour: 'list',
    coffee: 'list',
    grill: 'list',
    garden: 'list',
    coastal: 'list',
    luxe: 'list',
    burger: 'grid',
    injera: 'list',
    juice: 'grid',
  },
  layouts: true,
  layout: 'grid',
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
const MY_ORDER = /Show "Add to my order" \(\+\) buttons on my menu/

function render(
  writable = true,
  extra: Partial<Appearance> = {},
  putReply?: ReturnType<typeof invalid>
) {
  fake = fakeApi({
    'GET /restaurant/1/appearance': {
      data: { data: { ...APPEARANCE, ...extra } },
    },
    'PUT /restaurant/1/appearance':
      putReply ??
      ((req) => ({
        data: {
          data: {
            ...APPEARANCE,
            ...extra,
            ...JSON.parse(String(req.body)),
            logo: null,
            cover: null,
          },
        },
      })),
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

/** Open a tab — its controls are only in the DOM while it is the active tab. */
const goTo = (screen: Awaited<ReturnType<typeof render>>, name: string) =>
  userEvent.click(screen.getByRole('tab', { name }))

describe('AppearancePage', () => {
  beforeEach(() => {
    useTenantStore.setState({ currentTenantId: 1 })
  })
  afterEach(() => {
    fake.restore()
    window.history.replaceState(null, '', window.location.pathname)
  })

  it('applies a theme to the live preview and saves it', async () => {
    const screen = await render()
    await goTo(screen, 'Colours & font')
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

  // Chrome warns in the console about a sandbox with both allow-scripts and
  // allow-same-origin (it can remove itself), and allow-scripts alone breaks the
  // menu (no service worker, no storage). The preview is our own page: no sandbox.
  it('shows the preview without a sandbox that makes a console warning', async () => {
    const screen = await render()
    await goTo(screen, 'Colours & font')
    await expect
      .element(screen.getByRole('button', { name: 'Bistro' }))
      .toBeVisible()
    expect(frame(screen).hasAttribute('sandbox')).toBe(false)
  })

  it('warns when the colour is hard to read on white', async () => {
    const screen = await render()
    await goTo(screen, 'Colours & font')
    const input = screen.getByRole('textbox', { name: 'Brand colour' })
    await userEvent.clear(input)
    await userEvent.type(input, '#fde047')

    await expect
      .element(screen.getByText(/hard to read on white/))
      .toBeVisible()
  })

  it('switches and the menu style change the preview', async () => {
    const screen = await render()
    await goTo(screen, 'What guests see')
    await userEvent.click(screen.getByRole('switch', { name: 'Show prices' }))
    await goTo(screen, 'Look')
    await userEvent.click(
      screen.getByRole('radio', { name: /C · Quiet bistro/ })
    )

    await expect.poll(() => frame(screen).src).toContain('show_prices=0')
    expect(frame(screen).src).toContain('style=bistro')
    await expect
      .element(screen.getByRole('radio', { name: /C · Quiet bistro/ }))
      .toBeChecked()
  })

  it('offers E · Mesob, F · Night Lounge and G · Street Board, and each changes the preview', async () => {
    const screen = await render()
    for (const [name, style, hint] of [
      ['E · Mesob', 'mesob', /woven bands and round photos/],
      ['F · Night Lounge', 'lounge', /Always dark, with a soft glow/],
      ['G · Street Board', 'board', /A clean chalkboard with price tags/],
    ] as const) {
      await userEvent.click(
        screen.getByRole('radio', { name: new RegExp(name) })
      )
      await expect.poll(() => frame(screen).src).toContain(`style=${style}`)
      await expect.element(screen.getByText(hint)).toBeVisible()
    }
  })

  it('offers H · Arabic to L · Colourful, and each changes the preview', async () => {
    const screen = await render()
    for (const [name, style, hint] of [
      ['H · Arabic', 'arabic', /Right-to-left for Arabic menus/],
      ['I · Islamic', 'islamic', /Optional Halal badge and Ramadan mode/],
      ['J · Somali', 'somali', /woven-basket bands and waves/],
      ['K · Black & White', 'mono', /Only black, white and greys/],
      ['L · Colourful', 'colour', /A bright colour for each category/],
    ] as const) {
      await userEvent.click(
        screen.getByRole('radio', { name: new RegExp(name) })
      )
      await expect.poll(() => frame(screen).src).toContain(`style=${style}`)
      await expect.element(screen.getByText(hint)).toBeVisible()
    }
  })

  it('shows each style option only with its style, and the preview follows it', async () => {
    const screen = await render()
    // Style B has no options.
    await expect
      .element(screen.getByText('Menu direction'))
      .not.toBeInTheDocument()
    await expect
      .element(screen.getByRole('switch', { name: /Show a "Halal" badge/ }))
      .not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: /H · Arabic/ }))
    await expect
      .element(screen.getByRole('radio', { name: /Automatic/ }))
      .toBeChecked()
    await userEvent.click(screen.getByRole('radio', { name: 'Right to left' }))
    await expect.poll(() => frame(screen).src).toContain('menu_direction=rtl')

    await userEvent.click(screen.getByRole('radio', { name: /I · Islamic/ }))
    await expect
      .element(screen.getByText('Menu direction'))
      .not.toBeInTheDocument()
    await userEvent.click(
      screen.getByRole('switch', { name: /Show a "Halal" badge/ })
    )
    await userEvent.click(screen.getByRole('switch', { name: /Ramadan mode/ }))
    await expect.poll(() => frame(screen).src).toContain('show_halal=1')
    await expect.poll(() => frame(screen).src).toContain('ramadan_mode=1')

    await userEvent.click(
      screen.getByRole('radio', { name: /K · Black & White/ })
    )
    await expect
      .element(screen.getByRole('switch', { name: /Ramadan mode/ }))
      .not.toBeInTheDocument()
    await userEvent.click(
      screen.getByRole('switch', { name: /Black-and-white photos/ })
    )
    await userEvent.click(
      screen.getByRole('switch', { name: /Use your brand colour/ })
    )
    await expect.poll(() => frame(screen).src).toContain('photos_greyscale=1')
    await expect.poll(() => frame(screen).src).toContain('mono_accent=1')
  })

  it('offers the styles M–Q with a switch for their pictures and icons', async () => {
    const screen = await render()
    for (const name of [
      /M · Coffee House/,
      /N · Grill & BBQ/,
      /O · Garden Fresh/,
      /P · Coastal/,
      /Q · Fine Dining/,
    ])
      await expect.element(screen.getByRole('radio', { name })).toBeVisible()
    await expect
      .element(
        screen.getByRole('switch', { name: /Show the style's pictures/ })
      )
      .not.toBeInTheDocument()
    await expect
      .element(screen.getByText('Menu direction'))
      .not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: /P · Coastal/ }))
    await expect.poll(() => frame(screen).src).toContain('style=coastal')
    // Menu direction, for Arabic menus (H and M–Q).
    await userEvent.click(screen.getByRole('radio', { name: 'Right to left' }))
    await expect.poll(() => frame(screen).src).toContain('menu_direction=rtl')
    const deco = screen.getByRole('switch', {
      name: /Show the style's pictures and icons/,
    })
    await expect.element(deco).toBeChecked()
    await userEvent.click(deco)
    await expect.poll(() => frame(screen).src).toContain('show_decoration=0')
    // The choice is kept when moving to another style of M–Q.
    await userEvent.click(
      screen.getByRole('radio', { name: /Q · Fine Dining/ })
    )
    await expect
      .element(
        screen.getByRole('switch', { name: /Show the style's pictures/ })
      )
      .not.toBeChecked()
  })

  it('offers D · Minimal next to A, B and C, with logo and banner switches', async () => {
    const screen = await render()
    await userEvent.click(screen.getByRole('radio', { name: /D · Minimal/ }))
    await expect.poll(() => frame(screen).src).toContain('style=minimal')
    await expect
      .element(screen.getByText(/Text only: names, short descriptions/))
      .toBeVisible()
    await goTo(screen, 'Logo & banner')
    await userEvent.click(
      screen.getByRole('switch', { name: 'Show the banner' })
    )
    await expect.poll(() => frame(screen).src).toContain('show_banner=0')
    await expect
      .element(screen.getByRole('switch', { name: 'Show the logo' }))
      .toBeChecked()
  })

  it('offers only the styles in the plan', async () => {
    APPEARANCE.styles = ['grid', 'bistro']
    const screen = await render()
    await expect
      .element(screen.getByRole('radio', { name: /A · Warm editorial/ }))
      .toBeDisabled()
    await expect
      .element(screen.getByText('Not included in your plan.').first())
      .toBeVisible()
    // A theme with a style outside the plan keeps the current style.
    await goTo(screen, 'Colours & font')
    await userEvent.click(screen.getByRole('button', { name: 'Bistro' }))
    await expect.poll(() => frame(screen).src).toContain('font=serif')
    expect(frame(screen).src).toContain('style=grid')
    APPEARANCE.styles = ['editorial', 'grid', 'bistro', 'minimal']
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
    await goTo(screen, 'Logo & banner')

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

    await goTo(screen, 'Look')
    await userEvent.click(
      screen.getByRole('radio', { name: /C · Quiet bistro/ })
    )
    await goTo(screen, 'Logo & banner')
    await expect
      .element(screen.getByText('Recommended: 1600 × 640 px or larger.'))
      .toBeVisible()
    APPEARANCE.cover = original
  })

  it('switches the "Share menu" button', async () => {
    const screen = await render()
    await goTo(screen, 'What guests see')
    await userEvent.click(
      screen.getByRole('switch', { name: 'Show "Share menu" button' })
    )
    await expect.poll(() => frame(screen).src).toContain('show_share=0')
    await userEvent.click(screen.getByRole('switch', { name: MY_ORDER }))
    await expect.poll(() => frame(screen).src).toContain('show_order_list=0')
  })

  it('is read-only when the subscription does not allow changes', async () => {
    const screen = await render(false)
    await goTo(screen, 'Colours & font')
    await expect
      .element(screen.getByRole('button', { name: 'Bistro' }))
      .toBeDisabled()
    await expect
      .element(screen.getByRole('button', { name: 'Save' }))
      .toBeDisabled()
  })

  // "My order" switch (QUESTIONS #236): the owner's own switch, or greyed out with the reason.
  const ON = { enabled: true, available: true, restaurant: true, reason: null }

  it('lets the owner turn the + buttons off, with an easy hint, and the preview follows at once', async () => {
    const screen = await render(true, { my_order: ON })
    await goTo(screen, 'What guests see')
    const toggle = screen.getByRole('switch', { name: MY_ORDER })
    await expect.element(toggle).toBeChecked()
    await expect.element(toggle).toBeEnabled()
    await expect
      .element(screen.getByText(/Guests tap \+ to note dishes/))
      .toBeVisible()
    await userEvent.click(toggle)
    await expect.poll(() => frame(screen).src).toContain('show_order_list=0')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(
        () => fake.calls.find((c) => c.method === 'PUT')?.data?.show_order_list
      )
      .toBe(false)
  })

  for (const [reason, text] of [
    ['platform', 'Turned off by the platform.'],
    ['plan', 'Not in your plan.'],
  ] as const) {
    it(`greys the switch out with the reason when it is off for the ${reason}`, async () => {
      const screen = await render(true, {
        my_order: {
          enabled: false,
          available: false,
          restaurant: true,
          reason,
        },
      })
      await goTo(screen, 'What guests see')
      const toggle = screen.getByRole('switch', { name: MY_ORDER })
      await expect.element(toggle).toBeDisabled()
      await expect.element(toggle).not.toBeChecked()
      await expect.element(screen.getByText(text)).toBeVisible()
      // Saving something else keeps the owner's own choice for when it comes back.
      await userEvent.click(screen.getByRole('switch', { name: 'Show prices' }))
      await userEvent.click(screen.getByRole('button', { name: 'Save' }))
      await expect
        .poll(
          () =>
            fake.calls.find((c) => c.method === 'PUT')?.data?.show_order_list
        )
        .toBe(true)
    })
  }

  it('shows the switch off (not greyed) when only the restaurant turned it off', async () => {
    const screen = await render(true, {
      show_order_list: false,
      my_order: {
        enabled: false,
        available: true,
        restaurant: false,
        reason: 'restaurant',
      },
    })
    await goTo(screen, 'What guests see')
    const toggle = screen.getByRole('switch', { name: MY_ORDER })
    await expect.element(toggle).toBeEnabled()
    await expect.element(toggle).not.toBeChecked()
    expect(frame(screen).src).toContain('show_order_list=0')
  })

  // The new page (owner, 2026-11-26): five tabs, a layout picker, the phone in light or dark.
  it('shows the five sections as tabs and opens the one you pick', async () => {
    const screen = await render()
    for (const name of [
      'Look',
      'Layout',
      'Colours & font',
      'Logo & banner',
      'What guests see',
    ])
      await expect.element(screen.getByRole('tab', { name })).toBeVisible()
    // Only the open tab's controls are on the page.
    await goTo(screen, 'Colours & font')
    await expect
      .element(screen.getByRole('textbox', { name: 'Brand colour' }))
      .toBeVisible()
    await goTo(screen, 'What guests see')
    await expect
      .element(screen.getByRole('switch', { name: 'Show prices' }))
      .toBeVisible()
  })

  it("picks a layout: the style's own, the other one or text, and the preview follows", async () => {
    const screen = await render()
    await goTo(screen, 'Layout')
    const grid = screen.getByRole('radio', { name: /Grid/ })
    const list = screen.getByRole('radio', { name: /List/ })
    const text = screen.getByRole('radio', { name: /^Text/ })
    // B's own layout is the grid: picked, and marked as the style's own.
    await expect.element(grid).toBeChecked()
    await expect.element(screen.getByText("This style's own")).toBeVisible()
    expect(frame(screen).src).toContain('menu_layout=&')

    await userEvent.click(list)
    await expect.poll(() => frame(screen).src).toContain('menu_layout=list&')
    expect(frame(screen).src).toContain('show_photos=1')

    await userEvent.click(text)
    await expect.poll(() => frame(screen).src).toContain('show_photos=0')
    await expect.element(screen.getByText(/Your photos are kept/)).toBeVisible()

    // Back to the style's own: no setting needed, photos back on.
    await userEvent.click(grid)
    await expect.poll(() => frame(screen).src).toContain('show_photos=1')
    expect(frame(screen).src).toContain('menu_layout=&')

    await userEvent.click(list)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({ menu_layout: 'list', show_photos: true })
  })

  it('keeps the other layout for Plus and up, with text on every plan', async () => {
    const screen = await render(true, { layouts: false })
    await goTo(screen, 'Layout')
    await expect
      .element(screen.getByRole('radio', { name: /List/ }))
      .toBeDisabled()
    await expect.element(screen.getByText('Plus and up')).toBeVisible()
    await expect
      .element(screen.getByRole('radio', { name: /^Text/ }))
      .toBeEnabled()
    await expect
      .element(screen.getByRole('radio', { name: /Grid/ }))
      .toBeEnabled()
  })

  it('lets style D, text by itself, show photos in a grid or a list', async () => {
    const screen = await render(true, { style: 'minimal' })
    await goTo(screen, 'Layout')
    await expect
      .element(screen.getByRole('radio', { name: /^Text/ }))
      .toBeChecked()
    await userEvent.click(screen.getByRole('radio', { name: /Grid/ }))
    await expect.poll(() => frame(screen).src).toContain('menu_layout=grid&')
    expect(frame(screen).src).toContain('show_photos=1')
    await userEvent.click(screen.getByRole('radio', { name: /^Text/ }))
    await expect.poll(() => frame(screen).src).toContain('menu_layout=&')
  })

  it('shows the phone in light or dark mode', async () => {
    const screen = await render()
    const modes = screen.getByRole('group', { name: 'Phone colour mode' })
    await expect
      .element(modes.getByRole('button', { name: 'Light' }))
      .toHaveAttribute('aria-pressed', 'true')
    expect(frame(screen).src).toContain('scheme=light')

    await userEvent.click(modes.getByRole('button', { name: 'Dark' }))
    await expect.poll(() => frame(screen).src).toContain('scheme=dark')
    await userEvent.click(modes.getByRole('button', { name: 'Light' }))
    await expect.poll(() => frame(screen).src).toContain('scheme=light')
  })

  it('filters the styles by kind of place, keeping the picked one in view', async () => {
    const screen = await render()
    await userEvent.click(
      screen.getByRole('button', { name: 'Fast food & grill' })
    )
    await expect
      .element(screen.getByRole('radio', { name: /R · Burger & Pizza/ }))
      .toBeVisible()
    await expect
      .element(screen.getByRole('radio', { name: /Q · Fine Dining/ }))
      .not.toBeInTheDocument()
    await userEvent.click(
      screen.getByRole('button', { name: 'Ethiopian & Somali' })
    )
    await expect
      .element(screen.getByRole('radio', { name: /S · Injera & Tibs/ }))
      .toBeVisible()
    // B is picked: it stays, whatever the filter.
    await expect
      .element(screen.getByRole('radio', { name: /B · Fresh grid/ }))
      .toBeChecked()
    await userEvent.click(
      screen.getByRole('button', { name: 'All', exact: true })
    )
    await expect
      .element(screen.getByRole('radio', { name: /Q · Fine Dining/ }))
      .toBeVisible()
  })

  // Bug (2026-11-21): an empty value (no logo, no banner, the style's own layout) was left out of
  // the preview address, so the preview showed the saved one instead.
  it('sends empty values to the preview, so a removed logo or the own layout shows at once', async () => {
    const screen = await render()
    await goTo(screen, 'Layout')
    await expect
      .element(screen.getByRole('radio', { name: /Grid/ }))
      .toBeChecked()
    expect(frame(screen).src).toContain('logo_media_id=&')
    expect(frame(screen).src).toContain('cover_media_id=&')
    expect(frame(screen).src).toContain('banner_text=&')
  })
})

describe('AppearancePage tabs', () => {
  beforeEach(() => {
    useTenantStore.setState({ currentTenantId: 1 })
  })
  afterEach(() => {
    fake.restore()
    vi.restoreAllMocks()
    window.history.replaceState(null, '', window.location.pathname)
  })

  it("opens the Look tab first and shows only its tab's controls", async () => {
    const screen = await render()
    await expect
      .element(screen.getByRole('tab', { name: 'Look' }))
      .toHaveAttribute('aria-selected', 'true')
    // A colours control is not on the page until its tab is open.
    expect(screen.container.querySelector('#primary')).toBeNull()
    await goTo(screen, 'Colours & font')
    await expect.element(screen.getByLabelText('Brand colour')).toBeVisible()
    expect(screen.container.querySelector('#primary')).not.toBeNull()
  })

  it('marks a tab with unsaved changes, and the mark goes after saving', async () => {
    const screen = await render()
    await goTo(screen, 'Colours & font')
    await userEvent.click(screen.getByRole('button', { name: 'Bistro' }))
    // The theme changed the colours and the style, so both tabs are marked.
    await expect
      .element(screen.getByRole('tab', { name: 'Colours & font' }))
      .toHaveAttribute('data-changed', 'true')
    await expect
      .element(screen.getByRole('tab', { name: 'Look' }))
      .toHaveAttribute('data-changed', 'true')

    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .element(screen.getByRole('tab', { name: 'Colours & font' }))
      .not.toHaveAttribute('data-changed')
  })

  it('opens the tab with an error and shows the message', async () => {
    const screen = await render(
      true,
      {},
      invalid({ primary_color: 'Pick a darker colour.' })
    )
    await goTo(screen, 'Colours & font')
    await userEvent.click(screen.getByRole('button', { name: 'Bistro' }))
    // Move away, then save: the errored tab opens again.
    await goTo(screen, 'Look')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .element(screen.getByRole('tab', { name: 'Colours & font' }))
      .toHaveAttribute('aria-selected', 'true')
    // A field error shows inline at the field (not as a tab-header mark).
    await expect
      .element(screen.getByText('Pick a darker colour.'))
      .toBeVisible()
  })

  it('lists an error with no field of its own at the top of its tab', async () => {
    const screen = await render(
      true,
      {},
      invalid({ show_prices: 'Not allowed.' })
    )
    await goTo(screen, 'What guests see')
    await userEvent.click(screen.getByRole('switch', { name: /Show prices/ }))
    await goTo(screen, 'Look')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    await expect
      .element(screen.getByRole('tab', { name: 'What guests see' }))
      .toHaveAttribute('aria-selected', 'true')
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Not allowed.')
  })

  it('keeps the live preview working whatever tab is open', async () => {
    const screen = await render()
    await goTo(screen, 'What guests see')
    expect(frame(screen).src).toContain('style=grid')
    await userEvent.click(screen.getByRole('button', { name: 'Dark' }))
    await expect.poll(() => frame(screen).src).toContain('scheme=dark')
  })

  it('pins the preview and fits the phone to the screen on a computer', async () => {
    await page.viewport(1280, 900)
    const screen = await render()
    await expect
      .element(screen.getByRole('tab', { name: 'Look' }))
      .toBeVisible()
    const phone = frame(screen).parentElement as HTMLElement
    // On a computer the phone caps its height to the viewport, so the whole
    // preview stays in view instead of scrolling off the bottom.
    expect(phone.className).toContain('100dvh-12rem')
    // Its column sticks beside the settings rather than scrolling away.
    const column = phone.closest('[class*="lg:sticky"]')
    expect(column).not.toBeNull()
    expect(column?.className).toContain('lg:self-start')
  })

  it('has no accessibility problems on a tab', async () => {
    const screen = await render()
    await expect
      .element(screen.getByRole('tab', { name: 'Look' }))
      .toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
    await goTo(screen, 'Colours & font')
    await expect.element(screen.getByLabelText('Brand colour')).toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
  })
})
