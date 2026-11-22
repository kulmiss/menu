import { useState } from 'react'
import { useTranslation } from 'react-i18next'

/**
 * Small SVG charts for the Super Admin dashboard (owner, 2026-11-22), in the
 * same plain style as the existing columns: no chart library, theme colours,
 * light and dark, a readout on hover or keyboard focus, and a legend.
 */

/** Series colours, always in this order (never cycled); text never uses them. */
const COLOURS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)']

export type Series = { key: string; label: string; values: number[] }

/** Up to three lines over the same days, one y-axis, with a legend. */
export function LineChart({
  title,
  labels,
  series,
  format,
}: {
  title: string
  labels: string[]
  series: Series[]
  format: (v: number) => string
}) {
  const { t } = useTranslation()
  const [hover, setHover] = useState<number | null>(null)
  const width = 320
  const height = 110
  const max = Math.max(1, ...series.flatMap((s) => s.values))
  const n = Math.max(1, labels.length - 1)
  const x = (i: number) => (labels.length === 1 ? width / 2 : (i / n) * width)
  const y = (v: number) => height - 4 - (v / max) * (height - 12)
  const totals = series.map((s) => s.values.reduce((a, b) => a + b, 0))
  const empty = totals.every((v) => v === 0)
  const step = width / Math.max(1, labels.length)
  return (
    <figure className='grid gap-1'>
      <figcaption className='grid gap-1 text-sm'>
        <span className='font-medium'>{title}</span>
        <span className='text-muted-foreground tabular-nums' aria-live='polite'>
          {hover !== null
            ? `${labels[hover]}: ${series.map((s) => `${s.label} ${format(s.values[hover] ?? 0)}`).join(' · ')}`
            : series
                .map((s, i) => `${s.label} ${format(totals[i])}`)
                .join(' · ')}
        </span>
      </figcaption>
      {empty ? (
        <p className='text-sm text-muted-foreground'>
          {t('platform.w.no_data')}
        </p>
      ) : (
        <>
          <svg
            viewBox={`0 0 ${width} ${height}`}
            className='h-28 w-full overflow-visible'
            preserveAspectRatio='none'
            role='img'
            aria-label={`${title}: ${series.map((s, i) => `${s.label} ${format(totals[i])}`).join(', ')}`}
            onMouseLeave={() => setHover(null)}
          >
            <line
              x1='0'
              x2={width}
              y1={height - 3.5}
              y2={height - 3.5}
              stroke='currentColor'
              opacity='0.2'
            />
            {series.map((s, si) => (
              <polyline
                key={s.key}
                fill='none'
                stroke={COLOURS[si]}
                strokeWidth='2'
                strokeLinejoin='round'
                strokeLinecap='round'
                vectorEffect='non-scaling-stroke'
                points={s.values.map((v, i) => `${x(i)},${y(v)}`).join(' ')}
              />
            ))}
            {hover !== null && (
              <line
                x1={x(hover)}
                x2={x(hover)}
                y1='0'
                y2={height}
                stroke='currentColor'
                opacity='0.35'
                vectorEffect='non-scaling-stroke'
              />
            )}
            {labels.map((label, i) => (
              <rect
                key={label}
                x={x(i) - step / 2}
                y='0'
                width={step}
                height={height}
                fill='transparent'
                onMouseEnter={() => setHover(i)}
              >
                <title>{`${label}: ${series.map((s) => `${s.label} ${format(s.values[i] ?? 0)}`).join(', ')}`}</title>
              </rect>
            ))}
          </svg>
          <ul className='flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground'>
            {series.map((s, i) => (
              <li key={s.key} className='flex items-center gap-1.5'>
                <span
                  aria-hidden
                  className='inline-block h-0.5 w-4 rounded-full'
                  style={{ background: COLOURS[i] }}
                />
                {s.label}
              </li>
            ))}
          </ul>
        </>
      )}
    </figure>
  )
}

/** A list of labelled horizontal bars (share of a total, or of the largest). */
export function HBars({
  title,
  rows,
  total,
  format = (v) => v.toLocaleString(),
  percent = false,
}: {
  title: string
  rows: { key: string; label: string; value: number }[]
  /** The 100 % mark (default: the largest row). */
  total?: number
  format?: (v: number) => string
  /** Show the share in % after the value. */
  percent?: boolean
}) {
  const { t } = useTranslation()
  const top = Math.max(1, total ?? Math.max(0, ...rows.map((r) => r.value)))
  return (
    <figure className='grid gap-1.5'>
      <figcaption className='text-sm font-medium'>{title}</figcaption>
      {rows.length === 0 ? (
        <p className='text-sm text-muted-foreground'>
          {t('platform.w.no_data')}
        </p>
      ) : (
        <ul className='grid gap-1.5'>
          {rows.map((r) => {
            const share = Math.round((r.value * 100) / top)
            return (
              <li
                key={r.key}
                className='grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-2 text-sm'
              >
                <span className='truncate' title={r.label}>
                  {r.label}
                </span>
                <span
                  aria-hidden
                  className='h-2 overflow-hidden rounded-full bg-muted'
                >
                  <span
                    className='block h-full rounded-full bg-primary'
                    style={{ width: `${Math.min(100, share)}%` }}
                  />
                </span>
                <span className='text-end tabular-nums'>
                  {format(r.value)}
                  {percent && (
                    <span className='ms-1 text-muted-foreground'>{share}%</span>
                  )}
                </span>
              </li>
            )
          })}
        </ul>
      )}
    </figure>
  )
}

/** A tiny line of a few points (no axes), for table rows. */
export function Sparkline({
  values,
  label,
}: {
  values: number[]
  label: string
}) {
  const max = Math.max(1, ...values)
  const w = 72
  const h = 20
  const n = Math.max(1, values.length - 1)
  return (
    <svg
      viewBox={`0 0 ${w} ${h}`}
      className='h-5 w-18'
      role='img'
      aria-label={label}
      preserveAspectRatio='none'
    >
      <polyline
        fill='none'
        stroke='var(--chart-1)'
        strokeWidth='1.5'
        vectorEffect='non-scaling-stroke'
        points={values
          .map((v, i) => `${(i / n) * w},${h - 1 - (v / max) * (h - 2)}`)
          .join(' ')}
      />
    </svg>
  )
}
