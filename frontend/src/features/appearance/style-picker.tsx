import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { MENU_STYLES, type MenuStyle } from './api'

/**
 * "Menu style" picker: one card per style (A–G) with a small drawing of it.
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
      <div className='grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4'>
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
      {style === 'mesob' && (
        <>
          <defs>
            <pattern
              id='mesob-band'
              width='8'
              height='4'
              patternUnits='userSpaceOnUse'
            >
              <rect width='3' height='4' {...accent} />
              <rect x='4' width='3' height='4' fill='#c98a1b' />
            </pattern>
          </defs>
          <rect width='120' height='4' fill='url(#mesob-band)' />
          <circle cx='60' cy='15' r='6' {...accent} />
          <rect x='40' y='24' width='40' height='5' rx='2' {...ink} />
          <rect x='8' y='35' width='38' height='3' fill='url(#mesob-band)' />
          <rect x='74' y='35' width='38' height='3' fill='url(#mesob-band)' />
          {[43, 66].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='20' rx='5' {...bg} />
              <circle cx='17' cy={y + 10} r='7' {...photo} />
              <rect x='30' y={y + 5} width='40' height='4' rx='2' {...ink} />
              <rect
                x='30'
                y={y + 12}
                width='18'
                height='3'
                rx='1.5'
                {...accent}
              />
            </g>
          ))}
        </>
      )}
      {style === 'lounge' && (
        <>
          <rect width='120' height='90' fill='#0c0a13' />
          <rect x='8' y='8' width='50' height='7' rx='2' {...accent} />
          <rect x='8' y='19' width='30' height='3' rx='1.5' fill='#aaa2c8' />
          {[30, 50, 70].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='16' rx='4' fill='#17131f' />
              <rect x='9' y={y + 2} width='12' height='12' rx='3' {...photo} />
              <rect
                x='25'
                y={y + 4}
                width='40'
                height='4'
                rx='2'
                fill='#f2eeff'
              />
              <rect
                x='25'
                y={y + 10}
                width='28'
                height='2.5'
                rx='1'
                fill='#aaa2c8'
              />
              <rect x='92' y={y + 4} width='18' height='4' rx='2' {...accent} />
            </g>
          ))}
        </>
      )}
      {style === 'board' && (
        <>
          <rect width='120' height='90' fill='#1d2a25' />
          <rect x='34' y='7' width='52' height='7' rx='1' fill='#f5f2e9' />
          <rect
            x='6'
            y='22'
            width='108'
            height='63'
            rx='5'
            fill='none'
            stroke='#3f524a'
            strokeWidth='1.5'
            strokeDasharray='4 3'
          />
          <rect x='12' y='28' width='40' height='5' rx='1' {...accent} />
          {[40, 55, 70].map((y) => (
            <g key={y}>
              <rect x='12' y={y} width='46' height='4' rx='2' fill='#f5f2e9' />
              <rect
                x='12'
                y={y + 7}
                width='16'
                height='5'
                rx='1'
                transform={`rotate(-4 20 ${y + 9})`}
                {...accent}
              />
              <rect
                x='90'
                y={y - 1}
                width='16'
                height='13'
                fill='#f5f2e9'
                transform={`rotate(4 98 ${y + 5})`}
              />
              <rect x='92' y={y + 1} width='12' height='9' {...photo} />
            </g>
          ))}
        </>
      )}
    </svg>
  )
}
