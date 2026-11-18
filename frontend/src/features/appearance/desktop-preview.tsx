import { useEffect, useRef, useState } from 'react'
import { Monitor } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

/** The guest page as a computer shows it: 1280 px wide, scaled down to fit. */
const WIDTH = 1280
const HEIGHT = 800

export function DesktopPreview({ src }: { src: string }) {
  const { t } = useTranslation()
  return (
    <Dialog>
      <DialogTrigger asChild>
        <Button variant='outline' className='mt-2 w-full'>
          <Monitor aria-hidden />
          {t('appearance.preview_desktop')}
        </Button>
      </DialogTrigger>
      <DialogContent className='sm:max-w-[calc(100%-2rem)] xl:max-w-6xl'>
        <DialogHeader>
          <DialogTitle>{t('appearance.preview_desktop')}</DialogTitle>
          <DialogDescription>
            {t('appearance.preview_desktop_hint')}
          </DialogDescription>
        </DialogHeader>
        <ScaledFrame src={src} title={t('appearance.preview_desktop')} />
      </DialogContent>
    </Dialog>
  )
}

function ScaledFrame({ src, title }: { src: string; title: string }) {
  const box = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(0)
  useEffect(() => {
    const el = box.current
    if (!el) return
    const fit = () => setScale(el.clientWidth / WIDTH)
    fit()
    const ro = new ResizeObserver(fit)
    ro.observe(el)
    return () => ro.disconnect()
  }, [])
  return (
    <div
      ref={box}
      className='relative w-full overflow-hidden rounded-md border bg-background'
      style={{ height: HEIGHT * scale }}
    >
      {scale > 0 && (
        <iframe
          title={title}
          src={src}
          className='absolute start-0 top-0 origin-top-left border-0'
          style={{
            width: WIDTH,
            height: HEIGHT,
            transform: `scale(${scale})`,
          }}
        />
      )}
    </div>
  )
}
