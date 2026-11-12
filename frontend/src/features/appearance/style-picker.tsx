import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { type MenuStyle } from './api'

const MENU_STYLES: MenuStyle[] = ['editorial', 'grid', 'bistro', 'minimal']

/**
 * "Menu style" picker: four cards with a small drawing of each style.
 * Styles the plan doesn't include stay visible but can't be picked.
 */
export function StylePicker({
  value,
  allowed,
  disabled,
  onChange,
}: {
  value: MenuStyle
  allowed: MenuStyle[]
  disabled: boolean
  onChange: (style: MenuStyle) => void
}) {
  const { t } = useTranslation()

  return (
    <fieldset className='grid gap-2'>
      <legend className='mb-2 text-sm font-medium'>
        {t('appearance.menu_style')}
      </legend>
      <div className='grid grid-cols-2 gap-2 lg:grid-cols-4'>
        {MENU_STYLES.map((style) => {
          const locked = !allowed.includes(style)
          const checked = value === style
          return (
            <label
              key={style}
              className={cn(
                'flex cursor-pointer flex-col gap-2 rounded-lg border p-2 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                checked && 'border-primary bg-primary/5 ring-2 ring-primary/30',
                (disabled || locked) && 'cursor-not-allowed opacity-60'
              )}
            >
              <input
                type='radio'
                name='style'
                value={style}
                className='sr-only'
                checked={checked}
                disabled={disabled || locked}
                onChange={() => onChange(style)}
              />
              <StyleThumb style={style} />
              <span className='font-medium'>
                {t(`appearance.styles.${style}.name`)}
              </span>
              <span className='text-xs text-muted-foreground'>
                {locked
                  ? t('appearance.styles.locked')
                  : t(`appearance.styles.${style}.hint`)}
              </span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}

/** A tiny phone sketch of the style (decorative; the name says it all). */
function StyleThumb({ style }: { style: MenuStyle }) {
  // Plain SVG colours (no new utility classes in the shared stylesheet).
  const photo = { fill: '#b45309', fillOpacity: 0.6 }
  const line = { fill: 'var(--muted-foreground)', fillOpacity: 0.35 }
  const bg = { fill: 'var(--muted)' }
  const accent = { fill: 'var(--primary)' }
  const ink = { fill: 'var(--foreground)', fillOpacity: 0.7 }
  return (
    <svg
      aria-hidden='true'
      viewBox='0 0 120 90'
      className='h-auto w-full rounded-md border bg-background'
    >
      {style === 'editorial' && (
        <>
          <rect width='120' height='30' {...photo} />
          <circle cx='16' cy='30' r='8' {...accent} />
          <rect x='8' y='42' width='46' height='5' rx='2' {...ink} />
          <rect x='8' y='53' width='104' height='26' rx='5' {...photo} />
          <rect x='8' y='83' width='40' height='4' rx='2' {...line} />
          <rect x='94' y='83' width='18' height='4' rx='2' {...accent} />
        </>
      )}
      {style === 'grid' && (
        <>
          <rect x='5' y='4' width='110' height='28' rx='7' {...photo} />
          <rect x='12' y='22' width='38' height='5' rx='2' fill='#fff' />
          <rect x='5' y='37' width='22' height='7' rx='3.5' {...accent} />
          <rect x='30' y='37' width='22' height='7' rx='3.5' {...bg} />
          <rect x='55' y='37' width='22' height='7' rx='3.5' {...bg} />
          <rect x='5' y='49' width='53' height='38' rx='6' {...bg} />
          <rect x='5' y='49' width='53' height='22' rx='6' {...photo} />
          <rect x='62' y='49' width='53' height='38' rx='6' {...bg} />
          <rect x='62' y='49' width='53' height='22' rx='6' {...photo} />
          <rect x='9' y='75' width='30' height='4' rx='2' {...line} />
          <rect x='66' y='75' width='30' height='4' rx='2' {...line} />
        </>
      )}
      {style === 'bistro' && (
        <>
          <rect width='120' height='18' {...photo} />
          <rect x='36' y='24' width='48' height='5' rx='2' {...ink} />
          <rect x='0' y='35' width='120' height='1' {...line} />
          {[40, 57, 74].map((y) => (
            <g key={y}>
              <rect x='8' y={y + 2} width='50' height='4' rx='2' {...ink} />
              <rect
                x='8'
                y={y + 9}
                width='22'
                height='3'
                rx='1.5'
                {...accent}
              />
              <rect x='96' y={y} width='16' height='14' rx='3' {...photo} />
              <rect x='8' y={y + 16} width='104' height='0.6' {...line} />
            </g>
          ))}
        </>
      )}
      {style === 'minimal' && (
        <>
          <rect width='120' height='18' {...photo} />
          <rect x='38' y='23' width='44' height='5' rx='2' {...ink} />
          <rect x='8' y='35' width='30' height='3' rx='1.5' {...ink} />
          <rect x='8' y='40' width='104' height='0.8' {...ink} />
          {[45, 60, 75].map((y) => (
            <g key={y}>
              <rect x='8' y={y + 2} width='40' height='4' rx='2' {...ink} />
              <rect x='92' y={y + 2} width='20' height='4' rx='2' {...ink} />
              <rect x='8' y={y + 8} width='60' height='3' rx='1.5' {...line} />
              <rect x='8' y={y + 13.5} width='104' height='0.6' {...line} />
            </g>
          ))}
          <rect x='8' y='83' width='12' height='2.5' rx='1' {...accent} />
        </>
      )}
    </svg>
  )
}
