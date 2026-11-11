import { useRef, useState } from 'react'
import {
  ArrowLeft,
  Camera,
  ImagePlus,
  RefreshCw,
  Star,
  Trash2,
} from 'lucide-react'
import { type Area } from 'react-easy-crop'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { ACCEPTED_TYPES, cropToJpeg, isHeic } from '../photo'
import { type Media } from '../types'
import { uploadWithProgress } from '../upload'
import { CropDialog } from './crop-dialog'

export const MAX_DISH_PHOTOS = 5

type Props = {
  tenantId: number
  photos: Media[]
  onChange: (photos: Media[]) => void
  disabled?: boolean
  /** The upload function (tests pass their own). */
  uploader?: typeof uploadWithProgress
}

/**
 * Up to 5 photos per dish: take one with the camera or pick one, crop it,
 * watch it upload, then reorder (the first is the main photo), replace or
 * remove. The server makes the WebP sizes and the blurred placeholder.
 * Layout values are inline styles: new utility classes would grow the
 * shared stylesheet in the admin's first download.
 */
export function PhotoGallery({
  tenantId,
  photos,
  onChange,
  disabled,
  uploader = uploadWithProgress,
}: Props) {
  const { t } = useTranslation()
  const camera = useRef<HTMLInputElement>(null)
  const library = useRef<HTMLInputElement>(null)
  const [source, setSource] = useState<string | null>(null)
  // Where the cropped photo goes: an index to replace, or null to add.
  const [target, setTarget] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [upload, setUpload] = useState<{
    preview: string
    progress: number
  } | null>(null)
  const full = photos.length >= MAX_DISH_PHOTOS

  function choose(file: File | undefined) {
    if (camera.current) camera.current.value = ''
    if (library.current) library.current.value = ''
    if (!file) return
    setError(null)
    if (isHeic(file)) return setError(t('photo.heic'))
    if (!ACCEPTED_TYPES.includes(file.type)) return setError(t('photo.type'))
    if (file.size > 30 * 1024 * 1024) return setError(t('photo.too_big'))
    setSource(URL.createObjectURL(file))
  }

  function pick(
    input: React.RefObject<HTMLInputElement | null>,
    index: number | null
  ) {
    setTarget(index)
    input.current?.click()
  }

  function cancel() {
    if (source) URL.revokeObjectURL(source)
    setSource(null)
  }

  async function confirm(area: Area) {
    if (!source) return
    const blob = await cropToJpeg(source, area)
    cancel()
    const preview = URL.createObjectURL(blob)
    setUpload({ preview, progress: 0 })
    try {
      const media = await uploader(tenantId, blob, (progress) =>
        setUpload((u) => (u ? { ...u, progress } : u))
      )
      onChange(
        target === null
          ? [...photos, media]
          : photos.map((p, i) => (i === target ? media : p))
      )
    } catch (e) {
      setError((e as Error).message || t('photo.upload_failed'))
    } finally {
      URL.revokeObjectURL(preview)
      setUpload(null)
    }
  }

  const move = (from: number, to: number) => {
    const next = [...photos]
    const [photo] = next.splice(from, 1)
    next.splice(to, 0, photo)
    onChange(next)
  }

  const busy = disabled || upload !== null
  const percent = upload ? Math.round(upload.progress * 100) : 0

  return (
    <div className='grid gap-2'>
      <span className='text-sm font-medium' id='dish-photos'>
        {t('photo.photos_label')}
      </span>
      <ul
        aria-labelledby='dish-photos'
        className='grid grid-cols-2 gap-3 sm:grid-cols-3'
      >
        {photos.map((photo, i) => (
          <li key={photo.id} className='grid gap-1'>
            <div
              className='relative overflow-hidden rounded-md border bg-muted'
              style={{
                aspectRatio: '4 / 3',
                backgroundColor: photo.dominant_color ?? undefined,
              }}
            >
              <img
                src={photo.urls['320'] ?? photo.urls.jpg}
                alt={t('photo.photo_n', { n: i + 1 })}
                className='size-full object-cover'
              />
              {i === 0 && (
                <span
                  className='absolute rounded-full bg-background px-2 py-0.5 text-xs font-medium'
                  style={{ insetInlineStart: 6, top: 6 }}
                >
                  {t('photo.main')}
                </span>
              )}
            </div>
            <div className='flex justify-between'>
              {i > 0 ? (
                <>
                  <Button
                    type='button'
                    variant='ghost'
                    size='icon'
                    className='size-11'
                    aria-label={t('photo.make_main', { n: i + 1 })}
                    disabled={busy}
                    onClick={() => move(i, 0)}
                  >
                    <Star />
                  </Button>
                  {i > 1 && (
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      className='size-11'
                      aria-label={t('photo.move_left', { n: i + 1 })}
                      disabled={busy}
                      onClick={() => move(i, i - 1)}
                    >
                      <ArrowLeft className='rtl:rotate-180' />
                    </Button>
                  )}
                </>
              ) : (
                <span />
              )}
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='size-11'
                aria-label={t('photo.replace', { n: i + 1 })}
                disabled={busy}
                onClick={() => pick(library, i)}
              >
                <RefreshCw />
              </Button>
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='size-11'
                aria-label={t('photo.remove_n', { n: i + 1 })}
                disabled={busy}
                onClick={() => onChange(photos.filter((_, j) => j !== i))}
              >
                <Trash2 />
              </Button>
            </div>
          </li>
        ))}
        {upload && (
          <li className='grid gap-1'>
            <div
              className='relative overflow-hidden rounded-md border'
              style={{ aspectRatio: '4 / 3' }}
            >
              <img
                src={upload.preview}
                alt=''
                className='size-full object-cover opacity-60'
              />
              <div
                role='progressbar'
                aria-label={t('photo.uploading', { percent })}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className='absolute overflow-hidden rounded-full bg-background'
                style={{ insetInline: 8, bottom: 8, height: 8 }}
              >
                <div
                  className='h-full bg-primary transition-[width]'
                  style={{ width: `${percent}%` }}
                />
              </div>
            </div>
            <span className='text-xs text-muted-foreground' aria-live='polite'>
              {t('photo.uploading', { percent })}
            </span>
          </li>
        )}
      </ul>

      {!full && !upload && (
        <div className='flex flex-wrap gap-2'>
          <Button
            type='button'
            variant='outline'
            className='min-h-11'
            disabled={busy}
            onClick={() => pick(camera, null)}
          >
            <Camera /> {t('photo.take')}
          </Button>
          <Button
            type='button'
            variant='outline'
            className='min-h-11'
            disabled={busy}
            onClick={() => pick(library, null)}
          >
            <ImagePlus /> {t('photo.choose')}
          </Button>
        </div>
      )}
      <p className={cn('text-sm text-muted-foreground')}>
        {full
          ? t('photo.full', { max: MAX_DISH_PHOTOS })
          : t('photo.photos_hint', { max: MAX_DISH_PHOTOS })}
      </p>
      {error && (
        <p role='alert' className='text-sm text-destructive'>
          {error}
        </p>
      )}
      {/* The camera input opens the camera on phones; the other the photo library. */}
      <input
        ref={camera}
        type='file'
        accept='image/*'
        capture='environment'
        className='sr-only'
        tabIndex={-1}
        aria-label={t('photo.take')}
        onChange={(e) => choose(e.target.files?.[0])}
      />
      <input
        ref={library}
        type='file'
        accept={ACCEPTED_TYPES.join(',')}
        className='sr-only'
        tabIndex={-1}
        aria-label={t('photo.choose')}
        onChange={(e) => choose(e.target.files?.[0])}
      />
      <CropDialog
        source={source}
        aspect={4 / 3}
        busy={upload !== null}
        onCancel={cancel}
        onConfirm={confirm}
      />
    </div>
  )
}
