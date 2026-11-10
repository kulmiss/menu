import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { moneyInputValue, parseMoney } from '../money'

type Props = {
  label: string
  value: number | null
  /** null = blank; NaN never leaves this component. */
  onChange: (minor: number | null) => void
  currency: string
  error?: string
  disabled?: boolean
  hideLabel?: boolean
  className?: string
}

/** A price typed in major units ("120.50"), stored in minor units (12050). */
export function MoneyInput({
  label,
  value,
  onChange,
  currency,
  error,
  disabled,
  hideLabel,
  className,
}: Props) {
  const { t } = useTranslation()
  const id = useId()
  const [text, setText] = useState(moneyInputValue(value))
  const [invalid, setInvalid] = useState(false)

  const [seen, setSeen] = useState(value)

  // Follow outside changes (e.g. a size's price copied in), not our own typing.
  if (value !== seen) {
    setSeen(value)
    const parsed = parseMoney(text)
    if (Number.isNaN(parsed) || parsed !== value)
      setText(moneyInputValue(value))
  }

  const message = error ?? (invalid ? t('menu.price_invalid') : undefined)

  return (
    <div className={cn('grid gap-2', className)}>
      <Label htmlFor={id} className={hideLabel ? 'sr-only' : undefined}>
        {label}
      </Label>
      <div className='relative'>
        <span className='pointer-events-none absolute inset-y-0 start-3 flex items-center text-sm text-muted-foreground'>
          {currency}
        </span>
        <Input
          id={id}
          dir='ltr'
          inputMode='decimal'
          autoComplete='off'
          disabled={disabled}
          value={text}
          onChange={(e) => {
            setText(e.target.value)
            const parsed = parseMoney(e.target.value)
            setInvalid(Number.isNaN(parsed))
            if (!Number.isNaN(parsed)) onChange(parsed)
          }}
          aria-invalid={message ? true : undefined}
          className='min-h-11 ps-14 text-end tabular-nums sm:min-h-9'
        />
      </div>
      {message && <p className='text-sm text-destructive'>{message}</p>}
    </div>
  )
}
