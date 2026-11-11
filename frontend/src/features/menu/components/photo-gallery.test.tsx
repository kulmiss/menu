import { renderWithQuery } from '@/test-utils/render'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { type Media } from '../types'
import { UploadError } from '../upload'
import { PhotoGallery } from './photo-gallery'

const upload = { uploadWithProgress: vi.fn() }

const photo = (id: number): Media => ({
  id,
  width: 800,
  height: 600,
  dominant_color: '#aa5500',
  urls: { '320': `/media/1/${id}/320.webp` },
})

async function pngFile() {
  const canvas = document.createElement('canvas')
  canvas.width = 400
  canvas.height = 300
  canvas.getContext('2d')!.fillRect(0, 0, 400, 300)
  const blob = await new Promise<Blob>((r) =>
    canvas.toBlob((b) => r(b!), 'image/png')
  )
  return new File([blob], 'dish.png', { type: 'image/png' })
}

async function cropAndUse(screen: Awaited<ReturnType<typeof renderWithQuery>>) {
  const use = screen.getByRole('button', { name: 'Use photo' })
  await expect.element(use).toBeEnabled()
  // No stylesheet in tests: the dialog overlay sits on top, so click the element itself.
  ;(use.element() as HTMLButtonElement).click()
}

describe('PhotoGallery', () => {
  beforeEach(() => {
    upload.uploadWithProgress.mockReset()
  })

  it('marks the first photo as main and can make another one main', async () => {
    const onChange = vi.fn()
    const screen = await renderWithQuery(
      <PhotoGallery
        tenantId={1}
        photos={[photo(1), photo(2), photo(3)]}
        onChange={onChange}
      />
    )

    await expect
      .element(screen.getByText('Main', { exact: true }))
      .toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Make photo 3 the main photo' })
    )
    expect(onChange).toHaveBeenLastCalledWith([photo(3), photo(1), photo(2)])

    await userEvent.click(
      screen.getByRole('button', { name: 'Move photo 3 earlier' })
    )
    expect(onChange).toHaveBeenLastCalledWith([photo(1), photo(3), photo(2)])

    await userEvent.click(
      screen.getByRole('button', { name: 'Remove photo 1' })
    )
    expect(onChange).toHaveBeenLastCalledWith([photo(2), photo(3)])
  })

  it('adds a cropped photo and shows the upload progress', async () => {
    let finish: (m: Media) => void = () => {}
    upload.uploadWithProgress.mockImplementation((...args) => {
      ;(args[2] as (p: number) => void)(0.4)
      return new Promise((resolve) => (finish = resolve))
    })
    const onChange = vi.fn()
    const screen = await renderWithQuery(
      <PhotoGallery
        tenantId={1}
        photos={[photo(1)]}
        onChange={onChange}
        uploader={upload.uploadWithProgress}
      />
    )

    await userEvent.upload(
      screen.getByLabelText('Choose photo', { exact: true }),
      await pngFile()
    )
    await cropAndUse(screen)

    await expect
      .element(screen.getByRole('progressbar'))
      .toHaveAttribute('aria-valuenow', '40')
    await expect.element(screen.getByText('Uploading… 40%')).toBeVisible()
    finish(photo(9))
    await vi.waitFor(() =>
      expect(onChange).toHaveBeenCalledWith([photo(1), photo(9)])
    )
    expect(upload.uploadWithProgress.mock.calls[0][0]).toBe(1)
  })

  it('replaces a photo in place', async () => {
    upload.uploadWithProgress.mockResolvedValue(photo(7))
    const onChange = vi.fn()
    const screen = await renderWithQuery(
      <PhotoGallery
        tenantId={1}
        photos={[photo(1), photo(2)]}
        onChange={onChange}
        uploader={upload.uploadWithProgress}
      />
    )

    await userEvent.click(
      screen.getByRole('button', { name: 'Replace photo 2' })
    )
    await userEvent.upload(
      screen.getByLabelText('Choose photo', { exact: true }),
      await pngFile()
    )
    await cropAndUse(screen)

    await vi.waitFor(() =>
      expect(onChange).toHaveBeenCalledWith([photo(1), photo(7)])
    )
  })

  it('shows the server reason when an upload is refused', async () => {
    upload.uploadWithProgress.mockImplementation(() =>
      Promise.reject(new UploadError('Your plan allows 50 photos.'))
    )
    const screen = await renderWithQuery(
      <PhotoGallery
        tenantId={1}
        photos={[]}
        onChange={vi.fn()}
        uploader={upload.uploadWithProgress}
      />
    )

    await userEvent.upload(
      screen.getByLabelText('Take photo', { exact: true }),
      await pngFile()
    )
    await cropAndUse(screen)

    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Your plan allows 50 photos.')
  })

  it('stops adding at 5 photos', async () => {
    const screen = await renderWithQuery(
      <PhotoGallery
        tenantId={1}
        photos={[1, 2, 3, 4, 5].map(photo)}
        onChange={vi.fn()}
      />
    )

    await expect.element(screen.getByText(/has 5 photos/)).toBeVisible()
    await expect.element(screen.getByText('Take photo')).not.toBeInTheDocument()
  })
})
