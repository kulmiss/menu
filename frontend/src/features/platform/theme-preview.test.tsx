import '@/styles/index.css'
import { axeViolations } from '@/test-utils/axe'
import { fakeApi } from '@/test-utils/fake-api'
import { meFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { page, userEvent } from 'vitest/browser'
import { MENU_STYLES } from '@/features/appearance/api'
import { ThemesPage } from './screens'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
// The frame would load the test server's own page; keep the address, load nothing.
vi.mock('./api', async (orig) => {
  const real = await orig<typeof import('./api')>()
  return {
    ...real,
    themePreviewUrl: (...args: Parameters<typeof real.themePreviewUrl>) =>
      'about:blank#' + real.themePreviewUrl(...args).split('?')[1],
  }
})

const admin = meFixture({
  is_super_admin: true,
  two_factor_enabled: true,
  tenants: [],
})
let fake: ReturnType<typeof fakeApi>

function render() {
  fake = fakeApi({
    'GET /super/themes': {
      data: {
        data: [
          {
            id: 1,
            name: { en: 'Classic' },
            primary: '#b4432a',
            font: 'system',
            style: 'bistro',
            is_active: true,
            sort: 1,
          },
        ],
      },
    },
  })
  return renderWithQuery(<ThemesPage />, { me: admin })
}

const frame = () =>
  document.querySelector<HTMLIFrameElement>(
    'iframe[title="Preview on a demo menu"]'
  )
const src = () => new URLSearchParams(frame()?.src.split('#')[1] ?? '')

describe('Super Admin → Themes: phone preview', () => {
  afterEach(async () => {
    fake.restore()
    await page.viewport(414, 896)
  })

  it('shows the new theme beside the form on a computer and follows every choice before saving', async () => {
    await page.viewport(1280, 900)
    const screen = await render()
    const aside = screen.getByRole('complementary', {
      name: 'Preview on a demo menu',
    })
    await expect.element(aside).toBeVisible()
    expect(Object.fromEntries(src())).toEqual({
      style: 'grid',
      primary: '#b4432a',
      font: 'system',
      scheme: 'light',
      dir: 'ltr',
      photos: '1',
    })
    // Sticky while the page scrolls.
    expect(getComputedStyle(aside.element()).position).toBe('sticky')

    await userEvent.click(screen.getByRole('combobox', { name: 'Menu style' }))
    await userEvent.click(
      page.getByRole('option', { name: 'M · Coffee House' })
    )
    await expect.poll(() => src().get('style')).toBe('coffee')
    await userEvent.click(screen.getByRole('combobox', { name: 'Font' }))
    await userEvent.click(page.getByRole('option', { name: 'Classic serif' }))
    await expect.poll(() => src().get('font')).toBe('serif')

    // A typed colour shows after a short pause.
    await userEvent.fill(
      screen.getByLabelText('Colour', { exact: true }),
      '#2f5d50'
    )
    await expect.poll(() => src().get('primary')).toBe('#2f5d50')

    await userEvent.click(screen.getByRole('button', { name: 'Dark' }))
    await expect.poll(() => src().get('scheme')).toBe('dark')
    await userEvent.click(
      screen.getByRole('button', { name: 'Right to left (Arabic)' })
    )
    await expect.poll(() => src().get('dir')).toBe('rtl')
    await userEvent.click(screen.getByRole('button', { name: 'No photos' }))
    await expect.poll(() => src().get('photos')).toBe('0')

    // Previewing never saves anything.
    expect(fake.calls.filter((c) => c.method !== 'GET')).toEqual([])
  })

  it('offers right to left only for the styles that have it', async () => {
    await page.viewport(1280, 900)
    const screen = await render()
    const rtl = screen.getByRole('button', { name: 'Right to left (Arabic)' })
    // B · Fresh grid (the default) is always left to right.
    await expect.element(rtl).toBeDisabled()
    await expect
      .element(screen.getByText(/This style is always left to right\./))
      .toBeVisible()

    await userEvent.click(screen.getByRole('combobox', { name: 'Menu style' }))
    await userEvent.click(page.getByRole('option', { name: 'H · Arabic' }))
    await expect.element(rtl).toBeEnabled()
    await userEvent.click(rtl)
    await expect.poll(() => src().get('dir')).toBe('rtl')

    // Back to a style without it: the phone goes back to left to right.
    await userEvent.click(screen.getByRole('combobox', { name: 'Menu style' }))
    await userEvent.click(
      page.getByRole('option', { name: 'C · Quiet bistro' })
    )
    await expect.poll(() => src().get('dir')).toBe('ltr')
    await expect.element(rtl).toBeDisabled()
  })

  it('names every menu style A–T in the style list (M–T showed raw keys before)', async () => {
    await page.viewport(1280, 900)
    const screen = await render()
    await userEvent.click(screen.getByRole('combobox', { name: 'Menu style' }))
    const names = page
      .getByRole('option')
      .elements()
      .map((o) => o.textContent)
    expect(names).toHaveLength(MENU_STYLES.length)
    expect(names.at(-1)).toBe('T · Juice & Drinks')
    for (const name of names) expect(name).toMatch(/^[A-T] · /)
  })

  it('opens full screen from a Preview button on a phone, with a close button', async () => {
    await page.viewport(390, 844)
    const screen = await render()
    await expect
      .element(screen.getByRole('button', { name: 'Preview' }))
      .toBeVisible()
    // Nothing loads until it is opened.
    expect(frame()).toBeNull()

    await userEvent.click(screen.getByRole('button', { name: 'Preview' }))
    const dialog = page.getByRole('dialog', { name: 'Preview on a demo menu' })
    await expect.element(dialog).toBeVisible()
    await expect.poll(() => src().get('style')).toBe('grid')
    // Full screen: the whole width and height of the phone.
    const box = () => dialog.element().getBoundingClientRect()
    // (after its short opening animation)
    await expect.poll(() => box().left).toBe(0)
    expect(box().top).toBe(0)
    expect(box().width).toBeGreaterThanOrEqual(
      document.documentElement.clientWidth
    )
    expect(box().height).toBe(window.innerHeight)

    await userEvent.click(dialog.getByRole('button', { name: 'Close' }).last())
    await expect.element(dialog).not.toBeInTheDocument()
    expect(frame()).toBeNull()
  })

  it('has no accessibility problems', async () => {
    await page.viewport(1280, 900)
    const screen = await render()
    await expect
      .element(
        screen.getByRole('complementary', { name: 'Preview on a demo menu' })
      )
      .toBeVisible()
    expect(await axeViolations(screen.container)).toEqual([])
  })
})
