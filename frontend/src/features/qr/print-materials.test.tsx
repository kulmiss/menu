import { fakeApi } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { useTenantStore } from '@/stores/tenant-store'
import { PrintMaterialsPage } from './print-materials'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)
vi.mock('@/components/layout/admin-header', () => ({ AdminHeader: () => null }))

let fake: ReturnType<typeof fakeApi>
const code = (
  id: number,
  kind: string,
  description: string,
  branch = 'Bole'
) => ({
  id,
  code: `C${id}`,
  url: `https://menu.test/q/C${id}`,
  label: null,
  kind,
  branch_id: 1,
  branch,
  dining_table_id: kind === 'table' ? id : null,
  description,
})

function setup() {
  fake = fakeApi({
    'GET /t/1/qr-codes': {
      data: {
        data: [
          code(1, 'restaurant', 'Whole restaurant'),
          code(2, 'table', 'Table 1'),
          code(3, 'table', 'Table 2'),
        ],
      },
    },
    'GET /t/1/print-materials': {
      data: {
        data: {
          name: 'Buna Café',
          colour: '#b4441c',
          logo: null,
          headline: 'Scan for our menu',
          scan: 'Point your phone camera at the code.',
          branding: 'Menu by Menu',
        },
      },
    },
  })
  return renderWithQuery(<PrintMaterialsPage />, {
    me: meFixture({ tenants: [tenantFixture()] }),
  })
}

const pdfLink = (screen: Awaited<ReturnType<typeof setup>>) =>
  new URL(
    screen
      .getByRole('link', { name: 'Download PDF' })
      .element()
      .getAttribute('href') ?? '',
    location.href
  )

describe('Print materials', () => {
  beforeEach(() => useTenantStore.setState({ currentTenantId: 1 }))
  afterEach(() => fake.restore())

  it('builds the PDF link from the choices', async () => {
    const screen = await setup()
    await expect.element(screen.getByText('Scan for our menu')).toBeVisible()
    let url = pdfLink(screen)
    expect(url.pathname).toBe('/api/v1/t/1/print-materials.pdf')
    expect(Object.fromEntries(url.searchParams)).toEqual({
      material: 'tent',
      design: 'classic',
      theme: 'light',
      codes: '1',
    })

    await userEvent.click(screen.getByRole('button', { name: /Poster A4/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Bold' }))
    await userEvent.click(screen.getByRole('button', { name: 'Dark' }))
    await userEvent.fill(screen.getByLabelText('Headline'), 'Happy hour inside')
    await userEvent.click(
      screen.getByLabelText('For a print shop (3 mm bleed)')
    )
    await userEvent.click(screen.getByRole('button', { name: 'Choose all' }))
    await expect.element(screen.getByText('3 codes chosen')).toBeVisible()
    await expect.element(screen.getByText('Happy hour inside')).toBeVisible()

    url = pdfLink(screen)
    expect(Object.fromEntries(url.searchParams)).toEqual({
      material: 'poster_a4',
      design: 'bold',
      theme: 'dark',
      codes: '1,2,3',
      headline: 'Happy hour inside',
      bleed: '1',
    })
    const open = new URL(
      screen
        .getByRole('link', { name: 'Open the PDF' })
        .element()
        .getAttribute('href') ?? '',
      location.href
    )
    expect(open.searchParams.get('inline')).toBe('1')

    // Stickers are cut by hand: no bleed option.
    await userEvent.click(
      screen.getByRole('button', { name: /Round stickers/ })
    )
    expect(screen.container.textContent).not.toContain('3 mm bleed')
    expect(pdfLink(screen).searchParams.has('bleed')).toBe(false)

    // Nothing chosen: no download.
    await userEvent.click(screen.getByRole('button', { name: 'Clear' }))
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Whole restaurant' })
    )
    await expect
      .element(screen.getByText('Choose at least one QR code.'))
      .toBeVisible()
    expect(screen.container.textContent).not.toContain('Download PDF')
  })

  it('draws a square picture for social media in the browser', async () => {
    const created: Blob[] = []
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockImplementation((b) => (created.push(b as Blob), 'blob:x'))
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {})
    const screen = await setup()
    await expect.element(screen.getByText('Scan for our menu')).toBeVisible()

    await userEvent.click(
      screen.getByRole('button', { name: 'Square post (1080 × 1080)' })
    )
    await expect.poll(() => created.length).toBe(1)
    expect(created[0].type).toBe('image/png')
    const bitmap = await createImageBitmap(created[0])
    expect([bitmap.width, bitmap.height]).toEqual([1080, 1080])
    expect(click).toHaveBeenCalled()

    await userEvent.click(
      screen.getByRole('button', { name: 'Story (1080 × 1920)' })
    )
    await expect.poll(() => created.length).toBe(2)
    const story = await createImageBitmap(created[1])
    expect([story.width, story.height]).toEqual([1080, 1920])
    createObjectURL.mockRestore()
    click.mockRestore()
  })
})
