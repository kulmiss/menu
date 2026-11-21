import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ME_KEY, type Role } from '@/features/auth/api'
import { tourSlides } from './tour-slides'

export default function WelcomeTour({
  role,
  soldOut,
  onClose,
}: {
  role: Role | null
  soldOut: boolean
  onClose: () => void
}) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const slides = tourSlides(role, soldOut)
  const [i, setI] = useState(0)
  const slide = slides[i]
  const close = async (to?: string) => {
    onClose()
    if (to) void navigate({ to })
    try {
      await api.put('/me/onboarding', { tour: 'web_tour', seen: true })
      await qc.invalidateQueries({ queryKey: ME_KEY })
    } catch {
      /* it may open once more; nothing else breaks */
    }
  }
  return (
    <Dialog open onOpenChange={(o) => !o && void close()}>
      {/* The landing page's look (QUESTIONS #222): big bold title, soft brand
          glow behind a phone, round buttons. */}
      <DialogContent className='max-h-[94svh] overflow-y-auto rounded-[20px] sm:max-w-md'>
        <DialogHeader>
          <p className='w-fit rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary'>
            {t('tour.step', { n: i + 1, total: slides.length })}
          </p>
          <DialogTitle className='text-2xl font-extrabold tracking-tight text-balance'>
            {t(`tour.${slide.key}.title`)}
          </DialogTitle>
          <DialogDescription className='text-base'>
            {t(`tour.${slide.key}.text`)}
          </DialogDescription>
        </DialogHeader>
        <div className='relative isolate flex justify-center py-2'>
          <div
            aria-hidden
            className='absolute inset-x-8 inset-y-4 -z-10 rounded-full bg-primary/15 blur-2xl'
          />
          <div className='rounded-[30px] bg-[#0b0f19] p-1.5 shadow-xl'>
            <img
              src={`/help/shots/${slide.shot}-phone.webp`}
              alt=''
              className='max-h-[40svh] rounded-[24px] object-contain object-top'
            />
          </div>
        </div>
        <ol className='flex justify-center gap-1.5' aria-hidden>
          {slides.map((s, n) => (
            <li
              key={s.key}
              className={`h-2 rounded-full transition-all ${n === i ? 'w-6 bg-primary' : 'w-2 bg-muted'}`}
            />
          ))}
        </ol>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <Button
            variant='ghost'
            className='min-h-11 rounded-full'
            onClick={() => void close()}
          >
            {t('tour.skip')}
          </Button>
          <div className='flex gap-2'>
            {i > 0 && (
              <Button
                variant='outline'
                className='min-h-11 rounded-full'
                aria-label={t('tour.back')}
                onClick={() => setI(i - 1)}
              >
                <ChevronLeft />
              </Button>
            )}
            {i < slides.length - 1 ? (
              <Button
                className='min-h-11 rounded-full px-5'
                onClick={() => setI(i + 1)}
              >
                {t('tour.next')}
                <ChevronRight />
              </Button>
            ) : (
              <Button
                className='min-h-11 rounded-full px-5'
                onClick={() => void close(slide.to)}
              >
                {t(`tour.${slide.key}.go`)}
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
