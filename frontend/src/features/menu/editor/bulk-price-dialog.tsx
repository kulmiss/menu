import { useState } from 'react'
import { Loader2 } from 'lucide-react'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { formatMoney } from '../money'

/** Rounding steps in minor units (0 = to the cent). */
const STEPS = [0, 100, 500, 1000]

type Props = {
  count: number
  currency: string
  open: boolean
  onOpenChange: (open: boolean) => void
  onApply: (v: { percent: number; round_to: number }) => Promise<unknown>
}

/** Change selected prices by a percentage, e.g. +10 % rounded to Br 5. */
export function BulkPriceDialog({
  count,
  currency,
  open,
  onOpenChange,
  onApply,
}: Props) {
  const { t, i18n } = useTranslation()
  const [percent, setPercent] = useState('10')
  const [step, setStep] = useState('100')
  const [pending, setPending] = useState(false)
  const value = Number(percent.replace(',', '.'))
  const valid =
    Number.isFinite(value) && value !== 0 && value >= -90 && value <= 500

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    if (!valid) return
    setPending(true)
    try {
      await onApply({ percent: value, round_to: Number(step) })
      onOpenChange(false)
    } finally {
      setPending(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-sm'>
        <form onSubmit={submit} className='grid gap-4'>
          <DialogHeader className='text-start'>
            <DialogTitle>{t('menu.bulk_price_title')}</DialogTitle>
            <DialogDescription>
              {t('menu.bulk_price_intro', { count })}
            </DialogDescription>
          </DialogHeader>
          <div className='grid gap-2'>
            <Label htmlFor='bulk-percent'>{t('menu.percent_change')}</Label>
            <Input
              id='bulk-percent'
              dir='ltr'
              inputMode='decimal'
              value={percent}
              onChange={(e) => setPercent(e.target.value)}
              aria-invalid={!valid || undefined}
              className='min-h-11 sm:min-h-9'
            />
            <p className='text-xs text-muted-foreground'>
              {t('menu.percent_hint')}
            </p>
          </div>
          <div className='grid gap-2'>
            <Label>{t('menu.round_to')}</Label>
            <Select value={step} onValueChange={setStep}>
              <SelectTrigger
                className='min-h-11 w-full sm:min-h-9'
                aria-label={t('menu.round_to')}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {STEPS.map((s) => (
                  <SelectItem key={s} value={String(s)}>
                    {s === 0
                      ? t('menu.round_none')
                      : formatMoney(s, currency, i18n.language)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button
              type='submit'
              className='min-h-11 sm:min-h-9'
              disabled={!valid || pending}
            >
              {pending && <Loader2 className='animate-spin' />}
              {t('menu.apply')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
