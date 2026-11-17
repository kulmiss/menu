// The crop step before a photo upload. Bug (2026-11-17): a file the browser
// can't open (damaged, a renamed text file, or a 30 000 × 30 000 PNG) showed
// an empty black frame and a greyed-out "Use photo" with no explanation.
import '@/styles/index.css'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { render } from 'vitest-browser-react'
import { CropDialog } from './crop-dialog'

const urls: string[] = []
const url = (blob: Blob) => {
  const u = URL.createObjectURL(blob)
  urls.push(u)
  return u
}
afterEach(() => urls.splice(0).forEach((u) => URL.revokeObjectURL(u)))

// A 2 × 2 red PNG.
const PNG = Uint8Array.from(
  atob(
    'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGP4z8DAwMDAwMDAAAANHQEDasKb6QAAAABJRU5ErkJggg=='
  ),
  (c) => c.charCodeAt(0)
)

describe('CropDialog', () => {
  it('explains when the file cannot be opened as a photo', async () => {
    const screen = await render(
      <CropDialog
        source={url(new Blob(['not a photo'], { type: 'image/jpeg' }))}
        aspect={4 / 3}
        busy={false}
        onCancel={vi.fn()}
        onConfirm={vi.fn()}
      />
    )
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent("This photo can't be opened.")
    await expect
      .element(screen.getByRole('button', { name: 'Use photo' }))
      .toBeDisabled()
  })

  it('a good photo can be used, with no warning', async () => {
    const onConfirm = vi.fn()
    const screen = await render(
      <CropDialog
        source={url(new Blob([PNG], { type: 'image/png' }))}
        aspect={1}
        busy={false}
        onCancel={vi.fn()}
        onConfirm={onConfirm}
      />
    )
    const use = screen.getByRole('button', { name: 'Use photo' })
    await expect.element(use).toBeEnabled()
    await use.click()
    expect(onConfirm).toHaveBeenCalled()
    expect(screen.getByRole('alert').query()).toBeNull()
  })
})
