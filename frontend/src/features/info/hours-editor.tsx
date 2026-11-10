import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Copy, Loader2, Plus, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { type Hour, hoursQuery, useSaveHours } from './api'

/** Monday first, as people read a week. */
const DAYS = [1, 2, 3, 4, 5, 6, 0]

type Props = {
  tenantId: number
  branch: number | null
  disabled: boolean
}

/** The week's opening times: several per day (breaks), past midnight allowed. */
export function HoursEditor(props: Props) {
  const hours = useQuery(hoursQuery(props.tenantId, props.branch))
  if (!hours.data) return <Skeleton className='h-64' />
  return <HoursForm key={`${props.branch}`} {...props} initial={hours.data} />
}

function HoursForm({
  tenantId,
  branch,
  disabled,
  initial,
}: Props & { initial: Hour[] }) {
  const { t } = useTranslation()
  const save = useSaveHours(tenantId)
  const [rows, setRows] = useState<Hour[]>(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const dirty = JSON.stringify(rows) !== JSON.stringify(initial)

  const day = (wd: number) =>
    rows.map((r, i) => ({ ...r, i })).filter((r) => r.weekday === wd)
  const update = (i: number, patch: Partial<Hour>) =>
    setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...patch } : r)))

  async function submit() {
    setErrors({})
    const sorted = [...rows].sort(
      (a, b) => a.weekday - b.weekday || a.opens_at.localeCompare(b.opens_at)
    )
    try {
      await save.mutateAsync({ branch, hours: sorted })
      setRows(sorted)
      toast.success(t('info.hours_saved'))
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const error = Object.values(errors)[0]

  return (
    <div className='grid gap-3'>
      {branch !== null && rows.length === 0 && (
        <p className='rounded-md border bg-muted/40 p-3 text-sm'>
          {t('info.uses_restaurant_hours')}
        </p>
      )}
      {DAYS.map((wd) => (
        <div
          key={wd}
          className='grid gap-2 border-b pb-3 last:border-0 sm:grid-cols-[7rem_1fr]'
        >
          <p className='pt-2 text-sm font-medium'>{t(`info.days.${wd}`)}</p>
          <div className='grid gap-2'>
            {day(wd).map((r) => (
              <div key={r.i} className='flex flex-wrap items-center gap-2'>
                <Input
                  type='time'
                  className='w-32'
                  value={r.opens_at}
                  disabled={disabled}
                  aria-label={t('info.opens', { day: t(`info.days.${wd}`) })}
                  onChange={(e) => update(r.i, { opens_at: e.target.value })}
                />
                <span aria-hidden>–</span>
                <Input
                  type='time'
                  className='w-32'
                  value={r.closes_at}
                  disabled={disabled}
                  aria-label={t('info.closes', { day: t(`info.days.${wd}`) })}
                  onChange={(e) => update(r.i, { closes_at: e.target.value })}
                />
                {r.closes_at !== '' && r.closes_at <= r.opens_at && (
                  <span className='text-xs text-muted-foreground'>
                    {t('info.next_day')}
                  </span>
                )}
                {!disabled && (
                  <Button
                    variant='ghost'
                    size='icon'
                    className='size-9'
                    aria-label={t('info.remove_time', {
                      day: t(`info.days.${wd}`),
                    })}
                    onClick={() =>
                      setRows((rs) => rs.filter((_, j) => j !== r.i))
                    }
                  >
                    <X />
                  </Button>
                )}
              </div>
            ))}
            <div className='flex flex-wrap items-center gap-2'>
              {day(wd).length === 0 && (
                <span className='text-sm text-muted-foreground'>
                  {t('info.closed')}
                </span>
              )}
              {!disabled && day(wd).length < 4 && (
                <Button
                  variant='outline'
                  size='sm'
                  onClick={() => {
                    const today = day(wd)
                    const last = today[today.length - 1]
                    setRows((rs) => [
                      ...rs,
                      last
                        ? {
                            weekday: wd,
                            opens_at: last.closes_at,
                            closes_at: '23:00',
                          }
                        : {
                            weekday: wd,
                            opens_at: '08:00',
                            closes_at: '22:00',
                          },
                    ])
                  }}
                >
                  <Plus />{' '}
                  {day(wd).length ? t('info.add_break') : t('info.add_time')}
                </Button>
              )}
              {!disabled && wd === 1 && day(1).length > 0 && (
                <Button
                  variant='ghost'
                  size='sm'
                  onClick={() =>
                    setRows((rs) => [
                      ...rs.filter(
                        (r) =>
                          r.weekday === 1 || r.weekday === 0 || r.weekday === 6
                      ),
                      ...[2, 3, 4, 5].flatMap((w) =>
                        day(1).map((r) => ({
                          weekday: w,
                          opens_at: r.opens_at,
                          closes_at: r.closes_at,
                        }))
                      ),
                    ])
                  }
                >
                  <Copy /> {t('info.copy_weekdays')}
                </Button>
              )}
            </div>
          </div>
        </div>
      ))}
      {error && <p className='text-sm text-destructive'>{error}</p>}
      {!disabled && (
        <div className='flex flex-wrap gap-2'>
          <Button onClick={submit} disabled={!dirty || save.isPending}>
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('info.save_hours')}
          </Button>
          {branch !== null && rows.length > 0 && (
            <Button variant='outline' onClick={() => setRows([])}>
              {t('info.use_restaurant_hours')}
            </Button>
          )}
        </div>
      )}
    </div>
  )
}
