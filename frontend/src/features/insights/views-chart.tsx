import { useTranslation } from 'react-i18next'
import { type Insights } from './api'

/**
 * Views and scans per day as plain SVG bars: no chart library, so nothing
 * extra lands in the admin's first download (PLAN.md §15.6).
 */
export default function ViewsChart({ days }: { days: Insights['days'] }) {
  const { t } = useTranslation()
  const max = Math.max(1, ...days.map((d) => d.views))
  const step = 100 / days.length
  const label = (date: string) =>
    new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
      day: 'numeric',
      month: 'short',
    })
  const ticks =
    days.length <= 7
      ? days.map((_, i) => i)
      : [0, Math.floor(days.length / 2), days.length - 1]

  return (
    <figure className='grid gap-2'>
      <svg
        viewBox='0 0 100 50'
        preserveAspectRatio='none'
        className='h-56 w-full'
        role='img'
        aria-label={t('insights.chart_label')}
      >
        {[0.25, 0.5, 0.75].map((f) => (
          <line
            key={f}
            x1='0'
            x2='100'
            y1={50 - f * 48}
            y2={50 - f * 48}
            className='stroke-muted'
            strokeWidth='0.2'
          />
        ))}
        {days.map((d, i) => {
          const h = (d.views / max) * 48
          const s = (d.scans / max) * 48
          return (
            <g key={d.date}>
              <title>{`${label(d.date)}: ${t('insights.views')} ${d.views}, ${t('insights.scans')} ${d.scans}`}</title>
              <rect
                x={i * step + step * 0.15}
                y={50 - h}
                width={step * 0.7}
                height={h}
                className='fill-primary/25'
              />
              <rect
                x={i * step + step * 0.3}
                y={50 - s}
                width={step * 0.4}
                height={s}
                className='fill-primary'
              />
            </g>
          )
        })}
      </svg>
      <div className='relative h-4 text-xs text-muted-foreground'>
        {ticks.map((i) => (
          <span
            key={i}
            className='absolute -translate-x-1/2 whitespace-nowrap'
            style={{ left: `${(i + 0.5) * step}%` }}
          >
            {label(days[i].date)}
          </span>
        ))}
      </div>
      <figcaption className='flex gap-4 text-xs text-muted-foreground'>
        <span className='flex items-center gap-1'>
          <span className='size-3 rounded-sm bg-primary/25' />{' '}
          {t('insights.views')} ({t('insights.max', { count: max })})
        </span>
        <span className='flex items-center gap-1'>
          <span className='size-3 rounded-sm bg-primary' />{' '}
          {t('insights.scans')}
        </span>
      </figcaption>
    </figure>
  )
}
