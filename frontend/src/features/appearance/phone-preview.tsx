import { Moon, Sun } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Skeleton } from '@/components/ui/skeleton'
import { type PreviewScheme } from './api'
import { DesktopPreview } from './desktop-preview'

/** A row of buttons above the preview phone, one of them pressed (light/dark, direction…). */
export function PreviewSwitch<V extends string>({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: V
  options: {
    value: V
    label: string
    icon?: React.ReactNode
    disabled?: boolean
    title?: string
  }[]
  onChange: (value: V) => void
}) {
  return (
    <div
      role='group'
      aria-label={label}
      className='flex w-fit gap-1 rounded-full border bg-muted p-1'
    >
      {options.map((o) => (
        <button
          key={o.value}
          type='button'
          aria-pressed={value === o.value}
          disabled={o.disabled}
          title={o.title}
          onClick={() => onChange(o.value)}
          className={cn(
            'flex min-h-9 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
            value === o.value
              ? 'bg-background shadow-sm'
              : 'text-foreground/80 hover:text-foreground'
          )}
        >
          {o.icon}
          {o.label}
        </button>
      ))}
    </div>
  )
}

/** The light/dark switch (the guest's phone setting). */
export function SchemeSwitch({
  value,
  onChange,
}: {
  value: PreviewScheme
  onChange: (scheme: PreviewScheme) => void
}) {
  const { t } = useTranslation()
  return (
    <PreviewSwitch
      label={t('appearance.scheme.label')}
      value={value}
      onChange={onChange}
      options={[
        {
          value: 'light',
          label: t('appearance.scheme.light'),
          icon: <Sun className='size-4' aria-hidden />,
        },
        {
          value: 'dark',
          label: t('appearance.scheme.dark'),
          icon: <Moon className='size-4' aria-hidden />,
        },
      ]}
    />
  )
}

/**
 * The guest menu in a phone frame, as the owner sees it in Appearance and the
 * Super Admin in Themes (owner, 2026-11-21). The switches above the phone are
 * the light/dark one plus any the page adds (`controls`).
 */
export function PhonePreview({
  src,
  loading,
  onLoad,
  scheme,
  onScheme,
  title,
  hint,
  controls,
  frameClassName,
}: {
  src: string
  loading: boolean
  onLoad: () => void
  scheme: PreviewScheme
  onScheme: (scheme: PreviewScheme) => void
  title: string
  hint?: React.ReactNode
  controls?: React.ReactNode
  /** Extra classes for the phone (e.g. to fit a full-screen view). */
  frameClassName?: string
}) {
  return (
    <div>
      <div className='mx-auto mb-3 flex w-fit flex-wrap justify-center gap-2'>
        <SchemeSwitch value={scheme} onChange={onScheme} />
        {controls}
      </div>
      <div
        className={cn(
          'relative mx-auto h-[640px] w-[340px] max-w-full overflow-hidden rounded-[2.25rem] border-[10px] shadow-xl transition-colors',
          scheme === 'dark'
            ? 'border-zinc-700 bg-zinc-900'
            : 'border-zinc-800 bg-background',
          frameClassName
        )}
      >
        {loading && (
          <Skeleton className='absolute inset-0 rounded-none' aria-hidden />
        )}
        <iframe
          title={title}
          src={src}
          onLoad={onLoad}
          className='relative size-full'
        />
      </div>
      {hint && (
        <p className='mt-2 text-center text-xs text-muted-foreground'>{hint}</p>
      )}
      <DesktopPreview src={src} />
    </div>
  )
}
