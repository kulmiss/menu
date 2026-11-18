import '@/styles/index.css'
import { describe, expect, it } from 'vitest'
import { render } from 'vitest-browser-react'
import { page, userEvent } from 'vitest/browser'
import { DesktopPreview } from './desktop-preview'

// "Preview on a computer": the guest page at 1280 px, scaled down to fit the
// dialog on any screen, never sticking out of it (owner, 2026-11-18).
describe('DesktopPreview', () => {
  for (const width of [390, 1280])
    it(`shows the menu at desktop width, scaled into the dialog (${width} px screen)`, async () => {
      await page.viewport(width, 900)
      const screen = await render(
        <DesktopPreview src='about:blank#style=lounge' />
      )
      await userEvent.click(
        screen.getByRole('button', { name: 'Preview on a computer' })
      )
      const dialog = screen.getByRole('dialog')
      await expect.element(dialog).toBeVisible()
      const frame = dialog.element().querySelector('iframe')!
      expect(frame.getAttribute('src')).toBe('about:blank#style=lounge')
      expect(frame.style.width).toBe('1280px')
      await expect
        .poll(() => {
          const [f, d] = [frame, dialog.element()].map((e) =>
            e.getBoundingClientRect()
          )
          return (
            f.width > 100 && f.left >= d.left - 0.5 && f.right <= d.right + 0.5
          )
        })
        .toBe(true)
    })
})
