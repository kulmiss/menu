import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import { PhotoField } from './photo-field'

const media = {
  id: 7,
  width: 800,
  height: 600,
  dominant_color: '#aa5500',
  urls: { '320': '/media/1/x/320.webp' },
}
let fake: ReturnType<typeof fakeApi> | undefined

async function pngFile(name = 'dish.png') {
  const canvas = document.createElement('canvas')
  canvas.width = 400
  canvas.height = 300
  canvas.getContext('2d')!.fillRect(0, 0, 400, 300)
  const blob = await new Promise<Blob>((r) =>
    canvas.toBlob((b) => r(b!), 'image/png')
  )
  return new File([blob], name, { type: 'image/png' })
}

describe('PhotoField', () => {
  afterEach(() => fake?.restore())

  it('refuses iPhone HEIC files with advice', async () => {
    const screen = await renderWithQuery(
      <PhotoField tenantId={1} media={null} onChange={vi.fn()} label='Photo' />
    )

    await userEvent.upload(
      screen.getByLabelText('Photo', { exact: true }),
      new File(['x'], 'IMG_1.HEIC', { type: 'image/heic' })
    )

    await expect.element(screen.getByRole('alert')).toHaveTextContent(/HEIC/)
  })

  it('crops, uploads and reports the new photo', async () => {
    fake = fakeApi({
      'POST /t/1/media': { status: 201, data: { data: media } },
    })
    const onChange = vi.fn()
    const screen = await renderWithQuery(
      <PhotoField tenantId={1} media={null} onChange={onChange} label='Photo' />
    )

    await userEvent.upload(
      screen.getByLabelText('Photo', { exact: true }),
      await pngFile()
    )
    const use = screen.getByRole('button', { name: 'Use photo' })
    await expect.element(use).toBeEnabled()
    // No stylesheet in tests: the dialog overlay sits on top, so click the element itself.
    ;(use.element() as HTMLButtonElement).click()

    await vi.waitFor(() => expect(onChange).toHaveBeenCalledWith(media))
    expect(fake.apiCalls()[0].url).toBe('/t/1/media')
  })

  it('removes the photo', async () => {
    const onChange = vi.fn()
    const screen = await renderWithQuery(
      <PhotoField
        tenantId={1}
        media={media}
        onChange={onChange}
        label='Photo'
      />
    )

    await userEvent.click(screen.getByRole('button', { name: 'Remove photo' }))

    expect(onChange).toHaveBeenCalledWith(null)
  })
})
