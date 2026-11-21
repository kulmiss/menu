import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { MENU_STYLES, type MenuStyle } from './api'

/**
 * "Menu style" picker: one card per style (A–T) with a small drawing of it.
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
      {style === 'arabic' && (
        <>
          <rect width='120' height='90' fill='#f6efe2' />
          <rect width='120' height='26' {...accent} />
          {[0, 12, 24, 36, 48, 60, 72, 84, 96, 108].map((x) => (
            <path
              key={x}
              d={`M${x} 26h12c-3 0-4 2-6 5-2-3-3-5-6-5z`}
              {...accent}
            />
          ))}
          <rect x='42' y='9' width='36' height='6' rx='2' fill='#fff' />
          <rect x='8' y='39' width='30' height='1' fill='#b8892e' />
          <rect x='82' y='39' width='30' height='1' fill='#b8892e' />
          <rect x='45' y='36' width='30' height='6' rx='2' {...ink} />
          {[48, 69].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='18' rx='4' fill='#fffaf0' />
              <path d={`M96 ${y + 16}v-9a7 7 0 0 1 14 0v9z`} {...photo} />
              <rect x='40' y={y + 4} width='50' height='4' rx='2' {...ink} />
              <rect
                x='64'
                y={y + 11}
                width='26'
                height='4'
                rx='2'
                fill='none'
                stroke='#b8892e'
                strokeWidth='0.8'
              />
            </g>
          ))}
        </>
      )}
      {style === 'islamic' && (
        <>
          <defs>
            <pattern
              id='islamic-star'
              width='10'
              height='10'
              patternUnits='userSpaceOnUse'
            >
              <rect width='10' height='10' fill='#0d4f4a' />
              <rect
                x='3'
                y='3'
                width='4'
                height='4'
                fill='none'
                stroke='#c9a24b'
                strokeWidth='0.5'
              />
              <rect
                x='3'
                y='3'
                width='4'
                height='4'
                fill='none'
                stroke='#c9a24b'
                strokeWidth='0.5'
                transform='rotate(45 5 5)'
              />
            </pattern>
          </defs>
          <rect width='120' height='90' fill='#f7f3e8' />
          <rect width='120' height='34' fill='url(#islamic-star)' />
          <path d='M14 34V22a46 18 0 0 1 92 0v12z' fill='#f7f3e8' />
          <rect x='40' y='20' width='40' height='5' rx='2' {...ink} />
          {[8, 62].map((x) => (
            <g key={x}>
              <path d={`M${x} 88V52a25 10 0 0 1 50 0v36z`} fill='#fff' />
              <path d={`M${x + 3} 70V54a22 8 0 0 1 44 0v16z`} {...photo} />
              <rect x={x + 4} y='75' width='30' height='4' rx='2' {...ink} />
              <rect
                x={x + 4}
                y='82'
                width='16'
                height='3'
                rx='1.5'
                {...accent}
              />
            </g>
          ))}
        </>
      )}
      {style === 'somali' && (
        <>
          <rect width='120' height='90' fill='#f2f8fc' />
          <rect width='120' height='30' fill='#0b5ea8' />
          <rect width='120' height='3' fill='#e8772e' />
          <path
            d='M0 30q10-5 20 0t20 0 20 0 20 0 20 0 20 0v4H0z'
            fill='#f2f8fc'
          />
          <rect x='10' y='12' width='46' height='6' rx='2' fill='#fff' />
          <path d='M8 45q0-6 6-6t6 6z' fill='#f3e3c3' stroke='#e8772e' />
          <rect x='24' y='40' width='34' height='5' rx='2' {...accent} />
          {[50, 70].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='17' rx='4' fill='#fff' />
              <rect x='6' y={y + 15} width='108' height='2' fill='#f3e3c3' />
              <rect x='9' y={y + 2} width='13' height='13' rx='3' {...photo} />
              <rect x='26' y={y + 4} width='40' height='4' rx='2' {...ink} />
              <rect
                x='26'
                y={y + 10}
                width='18'
                height='3'
                rx='1.5'
                {...accent}
              />
              <circle cx='106' cy={y + 8} r='4' fill='#e8772e' />
            </g>
          ))}
        </>
      )}
      {style === 'mono' && (
        <>
          <rect width='120' height='90' fill='#fff' />
          <rect x='8' y='9' width='62' height='9' rx='1' fill='#111' />
          <rect x='8' y='22' width='26' height='3' rx='1' fill='#777' />
          <rect x='8' y='30' width='104' height='0.8' fill='#111' />
          <rect x='8' y='37' width='8' height='3' fill='#777' />
          <rect x='19' y='35' width='34' height='6' rx='1' fill='#111' />
          {[47, 62, 77].map((y) => (
            <g key={y}>
              <rect x='8' y={y} width='34' height='4' rx='1' fill='#111' />
              <rect
                x='45'
                y={y + 3}
                width='30'
                height='0.8'
                fill='#999'
                strokeDasharray='1 1'
              />
              <rect x='78' y={y} width='12' height='4' rx='1' fill='#111' />
              <rect x='96' y={y - 2} width='16' height='12' fill='#777' />
              <rect x='8' y={y + 12} width='82' height='0.6' fill='#ccc' />
            </g>
          ))}
        </>
      )}
      {style === 'colour' && (
        <>
          <rect width='120' height='90' fill='#fffaf2' />
          <circle cx='108' cy='4' r='16' fill='#ffd23f' fillOpacity='0.5' />
          <rect x='8' y='8' width='54' height='8' rx='3' fill='#1d1a33' />
          {[
            [8, '#e84a8a'],
            [36, '#ff7b29'],
            [64, '#3a86ff'],
            [92, '#2a9d4b'],
          ].map(([x, c]) => (
            <rect
              key={x}
              x={x}
              y='22'
              width='24'
              height='7'
              rx='3.5'
              fill={c as string}
            />
          ))}
          <rect x='8' y='35' width='34' height='8' rx='4' {...accent} />
          {[47, 68].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='18' rx='7' fill='#fde4ef' />
              <rect x='9' y={y + 2} width='14' height='14' rx='4' {...photo} />
              <rect
                x='27'
                y={y + 4}
                width='40'
                height='4'
                rx='2'
                fill='#1d1a33'
              />
              <rect
                x='27'
                y={y + 10}
                width='18'
                height='5'
                rx='2.5'
                {...accent}
              />
              <circle cx='106' cy={y + 9} r='4' fill='#1d1a33' />
            </g>
          ))}
        </>
      )}
      {style === 'coffee' && (
        <>
          <rect width='120' height='90' fill='#f5ece0' />
          <rect width='120' height='34' fill='#2b1d14' />
          <path
            d='M95 30c-7 0-11-4-11-11 0-7 4-12 10-14v-4h3v4c6 2 10 7 10 14 0 7-4 11-12 11z'
            fill='none'
            stroke='#c8a165'
            strokeWidth='1.5'
          />
          <circle cx='14' cy='22' r='7' {...accent} />
          <rect x='25' y='16' width='40' height='6' rx='2' fill='#f5ece0' />
          {[42, 66].map((y) => (
            <g key={y}>
              <circle cx='17' cy={y + 9} r='9' {...photo} />
              <rect x='31' y={y + 3} width='50' height='5' rx='2' {...ink} />
              <rect
                x='31'
                y={y + 11}
                width='22'
                height='4'
                rx='2'
                {...accent}
              />
              <path
                d={`M6 ${y + 21}h108`}
                stroke='#c8a165'
                strokeDasharray='2 2'
              />
            </g>
          ))}
        </>
      )}
      {style === 'grill' && (
        <>
          <rect width='120' height='90' fill='#161311' />
          <rect width='120' height='34' fill='#7a2410' />
          <path
            d='M88 34c-4-10 6-14 3-24 9 7 11 16 6 24zM104 34c-3-7 4-10 2-17 6 5 8 11 4 17z'
            fill='#ffb347'
          />
          <rect x='8' y='14' width='48' height='9' rx='1' fill='#f3e9e0' />
          {[40, 64].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='20' rx='3' fill='#231d1a' />
              <rect x='6' y={y} width='2' height='20' fill='#ff8a3d' />
              <rect x='11' y={y + 3} width='19' height='14' rx='2' {...photo} />
              <rect
                x='35'
                y={y + 4}
                width='40'
                height='4'
                rx='2'
                fill='#f3e9e0'
              />
              <rect
                x='35'
                y={y + 11}
                width='18'
                height='6'
                rx='1'
                {...accent}
              />
            </g>
          ))}
        </>
      )}
      {style === 'garden' && (
        <>
          <rect width='120' height='90' fill='#f1f7ef' />
          <rect width='120' height='34' rx='10' fill='#dcefd8' />
          <path
            d='M112 2c-12 4-20 16-18 30M100 12c-8-2-14 2-16 8 7 2 13-1 16-8z'
            fill='#a8dcb2'
            stroke='#2f8f5b'
          />
          <rect x='8' y='14' width='46' height='7' rx='3' fill='#173b2a' />
          {[40, 64].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='20' rx='8' fill='#fff' />
              <rect x='10' y={y + 3} width='14' height='14' rx='5' {...photo} />
              <rect
                x='29'
                y={y + 4}
                width='44'
                height='4'
                rx='2'
                fill='#173b2a'
              />
              <rect
                x='29'
                y={y + 11}
                width='18'
                height='4'
                rx='2'
                {...accent}
              />
            </g>
          ))}
        </>
      )}
      {style === 'coastal' && (
        <>
          <rect width='120' height='90' fill='#f6f1e7' />
          <rect width='120' height='34' fill='#0b4a68' />
          <circle cx='104' cy='10' r='5' fill='#ffd27a' />
          <path d='M84 28l9-20 2 20zM95 8l7 20h-7z' fill='#fff' />
          <path d='M78 28h28l-4 4H82z' fill='#7a4a26' />
          <path
            d='M0 34q7-4 14 0t14 0 14 0 14 0 14 0 14 0 14 0 14 0 8 0v2H0z'
            fill='#f6f1e7'
          />
          <rect x='8' y='14' width='44' height='7' rx='2' fill='#fff' />
          {[42, 65].map((y) => (
            <g key={y}>
              <rect x='6' y={y} width='108' height='19' rx='2' fill='#fff' />
              <rect x='6' y={y} width='2.5' height='19' fill='#e8735a' />
              <rect
                x='12'
                y={y + 3}
                width='13'
                height='13'
                rx='1.5'
                {...photo}
              />
              <rect
                x='30'
                y={y + 4}
                width='44'
                height='4'
                rx='2'
                fill='#0d2f45'
              />
              <rect
                x='30'
                y={y + 11}
                width='18'
                height='4'
                rx='2'
                {...accent}
              />
            </g>
          ))}
        </>
      )}
      {style === 'burger' && (
        <>
          <rect width='120' height='90' fill='#fff8ec' />
          <rect width='120' height='30' fill='#f5b301' />
          {Array.from({ length: 30 }, (_, i) => (
            <rect
              key={i}
              x={i * 4}
              y={i % 2 ? 30 : 32}
              width='4'
              height='2'
              fill='#d7261e'
            />
          ))}
          <rect x='8' y='10' width='46' height='8' rx='1' fill='#231a12' />
          <path d='M90 18a12 9 0 0 1 24 0z' fill='#fff3d6' stroke='#231a12' />
          <rect x='89' y='19' width='26' height='4' rx='2' fill='#7a3b1c' />
          {[8, 62].map((x) => (
            <g key={x}>
              <rect x={x} y='40' width='50' height='46' rx='5' fill='#231a12' />
              <rect
                x={x - 1.5}
                y='38.5'
                width='50'
                height='46'
                rx='5'
                fill='#fff'
                stroke='#231a12'
              />
              <rect x={x} y='40' width='47' height='26' rx='3.5' {...photo} />
              <rect
                x={x + 3}
                y='59'
                width='18'
                height='6'
                rx='3'
                fill='#f5b301'
                stroke='#231a12'
                strokeWidth='0.8'
                transform={`rotate(-4 ${x + 12} 62)`}
              />
              <rect
                x={x + 3}
                y='71'
                width='30'
                height='4'
                rx='1'
                fill='#231a12'
              />
            </g>
          ))}
        </>
      )}
      {style === 'injera' && (
        <>
          <rect width='120' height='90' fill='#fbf8f2' />
          <rect width='120' height='30' fill='#2a1d17' />
          {Array.from({ length: 10 }, (_, i) => (
            <rect
              key={i}
              x={i * 12}
              y='30'
              width='12'
              height='3'
              fill={['#135e57', '#a8321f', '#2c3e7a'][i % 3]}
            />
          ))}
          <rect x='8' y='11' width='44' height='7' rx='2' fill='#f6ede2' />
          <ellipse
            cx='98'
            cy='21'
            rx='15'
            ry='5'
            fill='none'
            stroke='#e3b25a'
            strokeDasharray='1 2'
          />
          {[42, 65].map((y) => (
            <g key={y}>
              <circle cx='17' cy={y + 8} r='9' fill='#fff' stroke='#c98f2a' />
              <circle cx='17' cy={y + 8} r='7.5' {...photo} />
              <rect
                x='32'
                y={y + 3}
                width='46'
                height='4'
                rx='2'
                fill='#2a1d17'
              />
              <rect
                x='32'
                y={y + 10}
                width='18'
                height='4'
                rx='2'
                {...accent}
              />
              <path d={`M8 ${y + 20}h104`} stroke='#e7dccd' />
            </g>
          ))}
        </>
      )}
      {style === 'juice' && (
        <>
          <rect width='120' height='90' fill='#f8fbf2' />
          <rect width='120' height='30' fill='#1d4d2b' />
          <rect y='28' width='120' height='2' fill='#8cc63f' />
          <rect y='30' width='120' height='2' fill='#f4a62a' />
          <rect y='32' width='120' height='2' fill='#e8505b' />
          <rect x='8' y='11' width='44' height='7' rx='3' fill='#fff' />
          <path d='M94 6h14l-2 20h-10z' fill='none' stroke='#fff' />
          <rect x='95' y='10' width='12' height='5' fill='#8cc63f' />
          <rect x='95.5' y='15' width='11' height='5' fill='#f4a62a' />
          <rect x='96' y='20' width='10' height='5' fill='#e8505b' />
          {[
            [8, '#8cc63f', '#e6f3d2'],
            [62, '#f4a62a', '#fde9c4'],
          ].map(([x, line, pill]) => (
            <g key={x as number}>
              <rect
                x={x as number}
                y='40'
                width='50'
                height='46'
                rx='8'
                fill='#fff'
              />
              <rect
                x={x as number}
                y='40'
                width='50'
                height='26'
                rx='8'
                {...photo}
              />
              <rect
                x={x as number}
                y='64'
                width='50'
                height='2'
                fill={line as string}
              />
              <rect
                x={(x as number) + 4}
                y='70'
                width='30'
                height='4'
                rx='2'
                fill='#17251a'
              />
              <rect
                x={(x as number) + 4}
                y='77'
                width='18'
                height='6'
                rx='3'
                fill={pill as string}
              />
            </g>
          ))}
        </>
      )}
      {style === 'luxe' && (
        <>
          <rect width='120' height='90' fill='#f7f4ee' />
          <path
            d='M0 10c22 8 32-4 54 3s30 14 66 6M0 28c18-4 34 10 56 4s34-6 64 0'
            fill='none'
            stroke='#d7cdbb'
          />
          <rect x='38' y='12' width='44' height='6' rx='1' {...ink} />
          <path d='M44 24h12M64 24h12' stroke='#b08d57' />
          <path d='M60 21.5l2.5 2.5-2.5 2.5-2.5-2.5z' fill='#b08d57' />
          <rect x='46' y='34' width='28' height='4' rx='1' fill='#b08d57' />
          {[44, 67].map((y) => (
            <g key={y}>
              <rect
                x='9'
                y={y}
                width='15'
                height='15'
                fill='none'
                stroke='#b08d57'
                strokeWidth='0.8'
              />
              <rect x='10.5' y={y + 1.5} width='12' height='12' {...photo} />
              <rect x='30' y={y + 3} width='46' height='4' rx='1' {...ink} />
              <rect
                x='30'
                y={y + 10}
                width='16'
                height='3'
                rx='1'
                {...accent}
              />
              <path d={`M8 ${y + 19}h104`} stroke='#e3dccf' />
            </g>
          ))}
        </>
      )}
    </svg>
  )
}
