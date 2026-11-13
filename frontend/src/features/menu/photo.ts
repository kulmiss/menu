import { api, tenantUrl } from '@/lib/api'
import { type Media } from './types'

/** Longest side sent to the server (QUESTIONS #45): keeps uploads small. */
export const MAX_UPLOAD_SIDE = 2048

export const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

/** Size that fits inside max×max without enlarging. */
export function fitWithin(
  width: number,
  height: number,
  max = MAX_UPLOAD_SIDE
) {
  const scale = Math.min(1, max / Math.max(width, height))
  return {
    width: Math.max(1, Math.round(width * scale)),
    height: Math.max(1, Math.round(height * scale)),
  }
}

/** HEIC (iPhone default) cannot be read by the server or most browsers. */
export function isHeic(file: File) {
  return /hei[cf]/i.test(file.type) || /\.hei[cf]$/i.test(file.name)
}

export type CropArea = { x: number; y: number; width: number; height: number }

/** Crops and downscales in the browser, returning a JPEG ready to upload. */
export async function cropToJpeg(
  src: string,
  area: CropArea,
  max = MAX_UPLOAD_SIDE
): Promise<Blob> {
  const image = new Image()
  image.src = src
  await image.decode()
  const size = fitWithin(area.width, area.height, max)
  const canvas = document.createElement('canvas')
  canvas.width = size.width
  canvas.height = size.height
  const context = canvas.getContext('2d')
  if (!context) throw new Error('Canvas is not available.')
  context.fillStyle = '#fff'
  context.fillRect(0, 0, size.width, size.height)
  context.drawImage(
    image,
    area.x,
    area.y,
    area.width,
    area.height,
    0,
    0,
    size.width,
    size.height
  )
  return new Promise((resolve, reject) =>
    canvas.toBlob(
      (blob) =>
        blob ? resolve(blob) : reject(new Error('Could not encode the photo.')),
      'image/jpeg',
      0.9
    )
  )
}

export async function uploadPhoto(
  tenantId: number,
  blob: Blob,
  /** "banner": saved a little lighter, it is the menu's largest paint. */
  purpose?: 'banner'
): Promise<Media> {
  const form = new FormData()
  form.append('file', blob, 'photo.jpg')
  if (purpose) form.append('purpose', purpose)
  const { data } = await api.post<{ data: Media }>(
    tenantUrl(tenantId, 'media'),
    form
  )
  return data.data
}
