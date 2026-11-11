import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { type Menu } from '../types'
import { PdfMenuDialog } from './pdf-menu-dialog'

const menu = (id: number, name: string): Menu => ({
  id,
  name: { en: name },
  slug: name.toLowerCase(),
  schedule_mode: 'always',
  is_visible: true,
  sort: id,
  schedules: [],
})
const ctx = {
  tenantId: 1,
  locale: 'en',
  currency: 'ETB',
  canWrite: true,
  canManage: true,
  canEditPrices: true,
}
let fake: ReturnType<typeof fakeApi>

describe('Download PDF menu', () => {
  afterEach(() => fake.restore())

  it('builds the download with the chosen menu, branch, photos and columns', async () => {
    fake = fakeApi({
      'GET /t/1/branches': {
        data: {
          data: [
            {
              id: 1,
              slug: 'main',
              name: { en: 'Main' },
              is_default: true,
              is_active: true,
            },
            {
              id: 2,
              slug: 'piassa',
              name: { en: 'Piassa' },
              is_default: false,
              is_active: true,
            },
          ],
        },
      },
    })
    const screen = await renderWithQuery(
      <PdfMenuDialog
        ctx={ctx}
        menus={[menu(5, 'All day'), menu(6, 'Breakfast')]}
        open
        onOpenChange={vi.fn()}
      />
    )
    const link = screen.getByRole('link', { name: 'Download PDF' })
    await expect
      .element(link)
      .toHaveAttribute(
        'href',
        expect.stringContaining('/t/1/menu-pdf?photos=0&columns=2')
      )

    await userEvent.selectOptions(
      screen.getByLabelText('Menu', { exact: true }),
      'Breakfast'
    )
    await userEvent.selectOptions(screen.getByLabelText('Branch'), 'Piassa')
    await userEvent.click(screen.getByRole('switch', { name: 'With photos' }))
    await userEvent.click(screen.getByRole('button', { name: '1 column' }))

    await expect
      .element(link)
      .toHaveAttribute(
        'href',
        expect.stringContaining(
          'menu-pdf?photos=1&columns=1&menu_id=6&branch_id=2'
        )
      )
    await expect.element(link).toHaveAttribute('download')
  })
})
