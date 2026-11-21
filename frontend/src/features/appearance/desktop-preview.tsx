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
          {t('common.preview.desktop')}
        </Button>
      </DialogTrigger>
      <DialogContent className='sm:max-w-[calc(100%-2rem)] xl:max-w-6xl'>
        <DialogHeader>
          <DialogTitle>{t('common.preview.desktop')}</DialogTitle>
          <DialogDescription>
            {t('common.preview.desktop_hint')}
          </DialogDescription>
        </DialogHeader>
        <ScaledFrame src={src} title={t('common.preview.desktop')} />
      </DialogContent>
    </Dialog>
  )
}

/** A page at a fixed width (a computer's 1280 px by default), scaled down to fit its box. */
export function ScaledFrame({
  src,
  title,
  width = WIDTH,
  height = HEIGHT,
}: {
  src: string
  title: string
  width?: number
  height?: number
}) {
  const box = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(0)
  useEffect(() => {
    const el = box.current
    if (!el) return
    const fit = () => setScale(Math.min(1, el.clientWidth / width))
    fit()
    const ro = new ResizeObserver(fit)
    ro.observe(el)
    return () => ro.disconnect()
  }, [width])
  return (
    <div
      ref={box}
      className='relative w-full overflow-hidden rounded-md border bg-background'
      style={{ height: height * scale }}
    >
      {scale > 0 && (
        <iframe
          title={title}
          src={src}
          className='absolute start-0 top-0 origin-top-left border-0'
          style={{
            width,
            height,
            transform: `scale(${scale})`,
          }}
        />
      )}
    </div>
  )
}
