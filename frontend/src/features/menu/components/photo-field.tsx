import { useRef, useState } from 'react'
import { ImagePlus, Loader2, Trash2 } from 'lucide-react'
import Cropper, { type Area } from 'react-easy-crop'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ACCEPTED_TYPES, cropToJpeg, isHeic, uploadPhoto } from '../photo'
import { type Media } from '../types'

type Props = {
  tenantId: number
  media: Media | null
  onChange: (media: Media | null) => void
  /** Width ÷ height of the crop frame. */
  aspect?: number
  label: string
  disabled?: boolean
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
}: Props) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)
  const [source, setSource] = useState<string | null>(null)
  const [crop, setCrop] = useState({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [area, setArea] = useState<Area | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)

  function choose(file: File | undefined) {
    if (!file) return
    setError(null)
    if (isHeic(file)) return setError(t('photo.heic'))
    if (!ACCEPTED_TYPES.includes(file.type)) return setError(t('photo.type'))
    if (file.size > 30 * 1024 * 1024) return setError(t('photo.too_big'))
    setCrop({ x: 0, y: 0 })
    setZoom(1)
    setSource(URL.createObjectURL(file))
  }

  function close() {
    if (source) URL.revokeObjectURL(source)
    setSource(null)
    if (input.current) input.current.value = ''
  }

  async function save() {
    if (!source || !area) return
    setUploading(true)
    try {
      const blob = await cropToJpeg(source, area)
      onChange(await uploadPhoto(tenantId, blob))
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
      <div className='flex items-center gap-3'>
        <div
          className='flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted'
          style={
            media?.dominant_color
              ? { backgroundColor: media.dominant_color }
              : undefined
          }
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
      {error && (
        <p role='alert' className='text-sm text-destructive'>
          {error}
        </p>
      )}

      <Dialog open={source !== null} onOpenChange={(o) => !o && close()}>
        <DialogContent className='sm:max-w-lg'>
          <DialogHeader className='text-start'>
            <DialogTitle>{t('photo.crop_title')}</DialogTitle>
            <DialogDescription>{t('photo.crop_intro')}</DialogDescription>
          </DialogHeader>
          <div className='relative h-72 overflow-hidden rounded-md bg-black'>
            {source && (
              <Cropper
                image={source}
                crop={crop}
                zoom={zoom}
                aspect={aspect}
                onCropChange={setCrop}
                onZoomChange={setZoom}
                onCropComplete={(_, pixels) => setArea(pixels)}
              />
            )}
          </div>
          <label className='grid gap-1 text-sm'>
            {t('photo.zoom')}
            <input
              type='range'
              min={1}
              max={3}
              step={0.05}
              value={zoom}
              onChange={(e) => setZoom(Number(e.target.value))}
              className='h-11 accent-primary'
            />
          </label>
          <DialogFooter>
            <Button
              type='button'
              className='min-h-11 sm:min-h-9'
              disabled={!area || uploading}
              onClick={save}
            >
              {uploading && <Loader2 className='animate-spin' />}
              {t('photo.use')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
