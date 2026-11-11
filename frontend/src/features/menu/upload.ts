import { api, newIdempotencyKey, tenantUrl } from '@/lib/api'
import { type Media } from './types'

/** A refused upload, with the server's reason when there is one. */
export class UploadError extends Error {}

/**
 * Uploads a photo and reports progress (0–1). Uses XMLHttpRequest because
 * fetch can't report upload progress; kept out of `lib/api` so the admin's
 * first download doesn't grow (owner, 2026-11-11).
 */
export function uploadWithProgress(
  tenantId: number,
  blob: Blob,
  onProgress: (fraction: number) => void
): Promise<Media> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${api.defaults.baseURL}${tenantUrl(tenantId, 'media')}`)
    xhr.withCredentials = true
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
    xhr.setRequestHeader('Idempotency-Key', newIdempotencyKey())
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]
    if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(xsrf))
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) onProgress(e.loaded / e.total)
    }
    xhr.onload = () => {
      let body: {
        data?: Media
        message?: string
        errors?: Record<string, string[]>
      } = {}
      try {
        body = JSON.parse(xhr.responseText)
      } catch {
        // not JSON: use the generic message below
      }
      if (xhr.status >= 200 && xhr.status < 300 && body.data) {
        onProgress(1)
        resolve(body.data)
      } else {
        reject(new UploadError(body.errors?.file?.[0] ?? body.message ?? ''))
      }
    }
    xhr.onerror = () => reject(new UploadError(''))
    const form = new FormData()
    form.append('file', blob, 'photo.jpg')
    xhr.send(form)
  })
}
