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
      <DialogContent className='max-h-[94svh] overflow-y-auto sm:max-w-md'>
        <DialogHeader>
          <p className='text-sm text-muted-foreground'>
            {t('tour.step', { n: i + 1, total: slides.length })}
          </p>
          <DialogTitle>{t(`tour.${slide.key}.title`)}</DialogTitle>
          <DialogDescription>{t(`tour.${slide.key}.text`)}</DialogDescription>
        </DialogHeader>
        <img
          src={`/help/shots/${slide.shot}-phone.webp`}
          alt=''
          className='mx-auto max-h-[42svh] rounded-lg border object-contain object-top shadow-sm'
        />
        <ol className='flex justify-center gap-1.5' aria-hidden>
          {slides.map((s, n) => (
            <li
              key={s.key}
              className={`size-2 rounded-full ${n === i ? 'bg-primary' : 'bg-muted'}`}
            />
          ))}
        </ol>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <Button
            variant='ghost'
            className='min-h-11'
            onClick={() => void close()}
          >
            {t('tour.skip')}
          </Button>
          <div className='flex gap-2'>
            {i > 0 && (
              <Button
                variant='outline'
                className='min-h-11'
                aria-label={t('tour.back')}
                onClick={() => setI(i - 1)}
              >
                <ChevronLeft />
              </Button>
            )}
            {i < slides.length - 1 ? (
              <Button className='min-h-11' onClick={() => setI(i + 1)}>
                {t('tour.next')}
                <ChevronRight />
              </Button>
            ) : (
              <Button className='min-h-11' onClick={() => void close(slide.to)}>
                {t(`tour.${slide.key}.go`)}
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
