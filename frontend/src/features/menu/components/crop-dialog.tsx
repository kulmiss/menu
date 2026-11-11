import { useState } from 'react'
import { Loader2 } from 'lucide-react'
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

/** Crop frame with zoom, shared by the single photo field and the dish gallery. */
export function CropDialog({
  source,
  aspect,
  busy,
  onCancel,
  onConfirm,
}: {
  /** Object URL of the chosen file; null keeps the dialog closed. */
  source: string | null
  /** Width ÷ height of the crop frame. */
  aspect: number
  busy: boolean
  onCancel: () => void
  onConfirm: (area: Area) => void
}) {
  const { t } = useTranslation()

  return (
    <Dialog open={source !== null} onOpenChange={(o) => !o && onCancel()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader className='text-start'>
          <DialogTitle>{t('photo.crop_title')}</DialogTitle>
          <DialogDescription>{t('photo.crop_intro')}</DialogDescription>
        </DialogHeader>
        {/* Keyed by the photo: each new photo starts centred and not zoomed. */}
        <CropBody
          key={source}
          source={source}
          aspect={aspect}
          busy={busy}
          onConfirm={onConfirm}
        />
      </DialogContent>
    </Dialog>
  )
}

function CropBody({
  source,
  aspect,
  busy,
  onConfirm,
}: {
  source: string | null
  aspect: number
  busy: boolean
  onConfirm: (area: Area) => void
}) {
  const { t } = useTranslation()
  const [crop, setCrop] = useState({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [area, setArea] = useState<Area | null>(null)

  return (
    <>
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
          disabled={!area || busy}
          onClick={() => area && onConfirm(area)}
        >
          {busy && <Loader2 className='animate-spin' />}
          {t('photo.use')}
        </Button>
      </DialogFooter>
    </>
  )
}
