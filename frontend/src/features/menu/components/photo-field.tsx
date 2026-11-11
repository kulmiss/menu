import { useRef, useState } from 'react'
import { ImagePlus, Trash2 } from 'lucide-react'
import { type Area } from 'react-easy-crop'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { ACCEPTED_TYPES, cropToJpeg, isHeic, uploadPhoto } from '../photo'
import { type Media } from '../types'
import { CropDialog } from './crop-dialog'

type Props = {
  tenantId: number
  media: Media | null
  onChange: (media: Media | null) => void
  /** Width ÷ height of the crop frame. */
  aspect?: number
  label: string
  disabled?: boolean
  /** Shown under the buttons, e.g. the recommended size. */
  hint?: string
  purpose?: 'banner'
}

/**
 * Photo picker: choose → crop (and downscale) in the browser → upload.
 * The server makes the sizes the menu needs.
 */
export function PhotoField({
  tenantId,
  media,
  onChange,
  aspect = 4 / 3,
  label,
  disabled,
  hint,
  purpose,
}: Props) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)
  const [source, setSource] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)

  function choose(file: File | undefined) {
    if (!file) return
    setError(null)
    if (isHeic(file)) return setError(t('photo.heic'))
    if (!ACCEPTED_TYPES.includes(file.type)) return setError(t('photo.type'))
    if (file.size > 30 * 1024 * 1024) return setError(t('photo.too_big'))
    setSource(URL.createObjectURL(file))
  }

  function close() {
    if (source) URL.revokeObjectURL(source)
    setSource(null)
    if (input.current) input.current.value = ''
  }

  async function save(area: Area) {
    if (!source) return
    setUploading(true)
    try {
      const blob = await cropToJpeg(source, area)
      onChange(await uploadPhoto(tenantId, blob, purpose))
      close()
    } catch {
      // The server's reason is shown as a toast (see main.tsx).
    } finally {
      setUploading(false)
    }
  }

  const thumb = media?.urls['320'] ?? media?.urls.jpg

  return (
    <div className='grid gap-2'>
      <span className='text-sm font-medium'>{label}</span>
      <div className='flex flex-wrap items-center gap-3'>
        <div
          className='flex h-20 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted'
          style={{
            width: `${Math.round(80 * Math.min(aspect, 2.5))}px`,
            backgroundColor: media?.dominant_color ?? undefined,
          }}
        >
          {thumb ? (
            <img src={thumb} alt='' className='size-full object-cover' />
          ) : (
            <ImagePlus className='size-6 text-muted-foreground' aria-hidden />
          )}
        </div>
        <div className='flex flex-wrap gap-2'>
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={disabled}
            onClick={() => input.current?.click()}
          >
            {media ? t('photo.change') : t('photo.add')}
          </Button>
          {media && (
            <Button
              type='button'
              variant='ghost'
              size='icon'
              className='size-11 sm:size-9'
              aria-label={t('photo.remove')}
              disabled={disabled}
              onClick={() => onChange(null)}
            >
              <Trash2 />
            </Button>
          )}
        </div>
        <input
          ref={input}
          type='file'
          accept={ACCEPTED_TYPES.join(',')}
          className='sr-only'
          aria-label={label}
          tabIndex={-1}
          onChange={(e) => choose(e.target.files?.[0])}
        />
      </div>
      {hint && <p className='text-sm text-muted-foreground'>{hint}</p>}
      {error && (
        <p role='alert' className='text-sm text-destructive'>
          {error}
        </p>
      )}

      <CropDialog
        source={source}
        aspect={aspect}
        busy={uploading}
        onCancel={close}
        onConfirm={save}
      />
    </div>
  )
}
