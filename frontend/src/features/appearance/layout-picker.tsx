import { Lock } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { LAYOUTS, type Layout } from './api'

/**
 * Layout picker (QUESTIONS #242): three big cards with a small drawing each.
 * The style's own layout is marked; another one is for Plus and up.
 */
export function LayoutPicker({
  value,
  own,
  allowed,
  disabled,
  onChange,
}: {
  value: Layout
  own: Layout
  allowed: boolean
  disabled: boolean
  onChange: (layout: Layout) => void
}) {
  const { t } = useTranslation()
  return (
    <fieldset className='grid min-w-0 gap-2'>
      <legend className='sr-only'>{t('appearance.layout.title')}</legend>
      <div className='grid grid-cols-3 gap-2 sm:gap-3'>
        {LAYOUTS.map((layout) => {
          const locked = layout !== own && layout !== 'text' && !allowed
          const checked = value === layout
          return (
            <label
              key={layout}
              className={cn(
                'relative flex cursor-pointer flex-col gap-2 rounded-xl border-2 p-2 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring sm:p-3',
                checked
                  ? 'border-primary bg-primary/5'
                  : 'border-transparent bg-muted/50 hover:bg-muted',
                (disabled || locked) && 'cursor-not-allowed opacity-60'
              )}
            >
              <input
                type='radio'
                name='menu_layout'
                value={layout}
                className='sr-only'
                checked={checked}
                disabled={disabled || locked}
                onChange={() => onChange(layout)}
              />
              <LayoutThumb layout={layout} />
              <span className='font-semibold'>
                {t(`appearance.layout.${layout}`)}
              </span>
              <span className='text-xs text-muted-foreground'>
                {locked ? (
                  <span className='inline-flex items-center gap-1'>
                    <Lock className='size-3' aria-hidden />
                    {t('appearance.layout.locked')}
                  </span>
                ) : layout === own ? (
                  t('appearance.layout.own')
                ) : (
                  t(`appearance.layout.${layout}_hint`)
                )}
              </span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}

/** A tiny phone screen in the layout (decorative; the name says it all). */
function LayoutThumb({ layout }: { layout: Layout }) {
  const photo = { fill: 'var(--primary)', fillOpacity: 0.55 }
  const line = { fill: 'var(--muted-foreground)', fillOpacity: 0.45 }
  const card = { fill: 'var(--background)' }
  return (
    <svg
      aria-hidden='true'
      viewBox='0 0 80 64'
      className='h-auto w-full rounded-lg border bg-muted'
    >
      {layout === 'grid' &&
        [6, 42].map((x) =>
          [5, 35].map((y) => (
            <g key={`${x}-${y}`}>
              <rect x={x} y={y} width='32' height='26' rx='3' {...card} />
              <rect x={x} y={y} width='32' height='15' rx='3' {...photo} />
              <rect
                x={x + 3}
                y={y + 18}
                width='18'
                height='3'
                rx='1.5'
                {...line}
              />
            </g>
          ))
        )}
      {layout === 'list' &&
        [5, 24, 43].map((y) => (
          <g key={y}>
            <rect x='6' y={y} width='68' height='16' rx='3' {...card} />
            <rect x='8' y={y + 2} width='12' height='12' rx='2.5' {...photo} />
            <rect x='24' y={y + 4} width='30' height='3' rx='1.5' {...line} />
            <rect x='24' y={y + 9} width='14' height='3' rx='1.5' {...line} />
          </g>
        ))}
      {layout === 'text' &&
        [6, 22, 38].map((y) => (
          <g key={y}>
            <rect x='8' y={y + 2} width='36' height='3.5' rx='1.75' {...line} />
            <rect
              x='60'
              y={y + 2}
              width='12'
              height='3.5'
              rx='1.75'
              {...photo}
            />
            <rect
              x='8'
              y={y + 8}
              width='26'
              height='3'
              rx='1.5'
              {...line}
              opacity='.6'
            />
            <path d={`M8 ${y + 14.5}h64`} stroke='var(--border)' />
          </g>
        ))}
    </svg>
  )
}
