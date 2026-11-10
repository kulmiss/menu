import { useState } from 'react'
import { Loader2, Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { weekdayNames } from '../text'
import { type Menu, type Schedule } from '../types'

type Props = {
  menu: Menu
  open: boolean
  onOpenChange: (open: boolean) => void
  onSave: (v: {
    schedule_mode: Menu['schedule_mode']
    schedules: Schedule[]
  }) => Promise<unknown>
}

/** When a menu is shown, e.g. Breakfast 07:00–11:00 on weekdays. */
export function ScheduleDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className='max-h-[90svh] overflow-y-auto sm:max-w-lg'>
        <ScheduleForm {...props} />
      </DialogContent>
    </Dialog>
  )
}

function ScheduleForm({ menu, onOpenChange, onSave }: Props) {
  const { t, i18n } = useTranslation()
  const days = weekdayNames(i18n.language)
  const [mode, setMode] = useState(menu.schedule_mode)
  const [rows, setRows] = useState<Schedule[]>(menu.schedules ?? [])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  const update = (i: number, patch: Partial<Schedule>) =>
    setRows((r) => r.map((row, j) => (j === i ? { ...row, ...patch } : row)))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setPending(true)
    try {
      await onSave({
        schedule_mode: mode,
        schedules: mode === 'always' ? [] : rows,
      })
      onOpenChange(false)
    } catch (error) {
      // Other failures are already shown as a toast (see main.tsx).
      const fields = validationErrors(error)
      if (fields) setErrors(fields)
    } finally {
      setPending(false)
    }
  }

  return (
    <form onSubmit={submit} className='grid gap-4'>
      <DialogHeader className='text-start'>
        <DialogTitle>{t('menu.schedule_title')}</DialogTitle>
        <DialogDescription>{t('menu.schedule_intro')}</DialogDescription>
      </DialogHeader>
      <RadioGroup
        value={mode}
        onValueChange={(v) => setMode(v as Menu['schedule_mode'])}
      >
        <Label className='flex min-h-11 items-center gap-3 font-normal'>
          <RadioGroupItem value='always' /> {t('menu.schedule_always')}
        </Label>
        <Label className='flex min-h-11 items-center gap-3 font-normal'>
          <RadioGroupItem value='scheduled' /> {t('menu.schedule_times')}
        </Label>
      </RadioGroup>

      {mode === 'scheduled' && (
        <div className='grid gap-3'>
          {rows.map((row, i) => (
            <fieldset
              key={i}
              className='grid grid-cols-[1fr_auto] gap-2 rounded-md border p-3'
            >
              <legend className='sr-only'>
                {t('menu.window', { n: i + 1 })}
              </legend>
              <Select
                value={String(row.weekday)}
                onValueChange={(v) => update(i, { weekday: Number(v) })}
              >
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('menu.weekday')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {days.map((d, n) => (
                    <SelectItem key={n} value={String(n)}>
                      {d}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='size-11 sm:size-9'
                aria-label={t('menu.remove_window')}
                onClick={() => setRows((r) => r.filter((_, j) => j !== i))}
              >
                <Trash2 />
              </Button>
              <div className='col-span-2 flex items-center gap-2'>
                <Input
                  type='time'
                  aria-label={t('menu.starts_at')}
                  value={row.starts_at}
                  onChange={(e) => update(i, { starts_at: e.target.value })}
                  className='min-h-11 sm:min-h-9'
                  required
                />
                <span aria-hidden>–</span>
                <Input
                  type='time'
                  aria-label={t('menu.ends_at')}
                  value={row.ends_at}
                  onChange={(e) => update(i, { ends_at: e.target.value })}
                  className='min-h-11 sm:min-h-9'
                  required
                />
              </div>
              {row.ends_at && row.starts_at && row.ends_at < row.starts_at && (
                <p className='col-span-2 text-xs text-muted-foreground'>
                  {t('menu.past_midnight')}
                </p>
              )}
              {(errors[`schedules.${i}.ends_at`] ??
                errors[`schedules.${i}.starts_at`]) && (
                <p className='col-span-2 text-sm text-destructive'>
                  {errors[`schedules.${i}.ends_at`] ??
                    errors[`schedules.${i}.starts_at`]}
                </p>
              )}
            </fieldset>
          ))}
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={() =>
              setRows((r) => [
                ...r,
                r.length > 0
                  ? {
                      ...r[r.length - 1],
                      weekday: (r[r.length - 1].weekday + 1) % 7,
                    }
                  : { weekday: 1, starts_at: '07:00', ends_at: '11:00' },
              ])
            }
          >
            <Plus /> {t('menu.add_window')}
          </Button>
        </div>
      )}
      <DialogFooter>
        <Button
          type='submit'
          className='min-h-11 sm:min-h-9'
          disabled={pending}
        >
          {pending && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </DialogFooter>
    </form>
  )
}
