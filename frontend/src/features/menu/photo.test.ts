import { describe, expect, it } from 'vitest'
import { cropToJpeg, fitWithin, isHeic } from './photo'

describe('photo helpers', () => {
  it('downscales to 2048 px on the longest side without enlarging', () => {
    expect(fitWithin(4032, 3024)).toEqual({ width: 2048, height: 1536 })
    expect(fitWithin(3024, 4032)).toEqual({ width: 1536, height: 2048 })
    expect(fitWithin(800, 600)).toEqual({ width: 800, height: 600 })
  })

  it('recognises iPhone HEIC files', () => {
    expect(isHeic(new File([], 'IMG_1.HEIC', { type: '' }))).toBe(true)
    expect(isHeic(new File([], 'a.jpg', { type: 'image/heif' }))).toBe(true)
    expect(isHeic(new File([], 'a.jpg', { type: 'image/jpeg' }))).toBe(false)
  })

  it('crops a region into a JPEG', async () => {
    const canvas = document.createElement('canvas')
    canvas.width = 400
    canvas.height = 300
    canvas.getContext('2d')!.fillRect(0, 0, 400, 300)
    const blob = await cropToJpeg(
      canvas.toDataURL('image/png'),
      { x: 100, y: 50, width: 200, height: 150 },
      100
    )

    expect(blob.type).toBe('image/jpeg')
    const bitmap = await createImageBitmap(blob)
    expect([bitmap.width, bitmap.height]).toEqual([100, 75])
  })
})
