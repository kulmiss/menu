import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { withText } from '../text'
import { type Translations } from '../types'

type Props = {
  label: string
  value: Translations
  onChange: (value: Translations) => void
  /** The language being edited (the restaurant's main language). */
  locale: string
  multiline?: boolean
  maxLength?: number
  error?: string
  autoFocus?: boolean
  placeholder?: string
}

/**
 * Edits one language of a multi-language text and keeps the others.
 * Other languages are filled in on the Translations page (Partition 8).
 */
export function TranslatableInput({
  label,
  value,
  onChange,
  locale,
  multiline,
  maxLength,
  error,
  autoFocus,
  placeholder,
}: Props) {
  const { t } = useTranslation()
  const id = useId()
  const others = Object.keys(value).filter((l) => l !== locale).length
  const Field = multiline ? Textarea : Input

  return (
    <div className='grid gap-2'>
      <div className='flex items-center justify-between gap-2'>
        <Label htmlFor={id}>{label}</Label>
        <span className='text-xs text-muted-foreground'>
          <span className='rounded border px-1 font-mono uppercase'>
            {locale}
          </span>
          {others > 0 && ` +${t('menu.other_languages', { count: others })}`}
        </span>
      </div>
      <Field
        id={id}
        value={value[locale] ?? ''}
        onChange={(e) => onChange(withText(value, locale, e.target.value))}
        maxLength={maxLength}
        autoFocus={autoFocus}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className='min-h-11 sm:min-h-9'
      />
      {error && (
        <p id={`${id}-error`} className='text-sm text-destructive'>
          {error}
        </p>
      )}
    </div>
  )
}
