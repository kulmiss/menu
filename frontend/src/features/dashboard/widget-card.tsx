import { type ReactNode } from 'react'
import { Download, Info, RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { type CsvCell, downloadCsv } from '@/lib/csv'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'

/**
 * Card with the three states every widget shares: loading, error (retry), content.
 * Optional: an "i" button explaining the numbers in easy words, and "Export CSV"
 * for the data shown (owner, 2026-11-22).
 */
export function Widget<T>({
  title,
  hint,
  info,
  csv,
  query,
  children,
}: {
  title: string
  hint?: string
  info?: string
  csv?: (data: T) => { name: string; rows: CsvCell[][] }
  query: {
    data?: T
    isError: boolean
    refetch: () => unknown
    isFetching: boolean
  }
  children: (data: T) => ReactNode
}) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader>
        <div className='flex items-start justify-between gap-2'>
          <CardTitle className='leading-snug'>{title}</CardTitle>
          <div className='-me-2 -mt-1.5 flex shrink-0 items-center'>
            {info && (
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant='ghost'
                    size='icon'
                    className='size-11 sm:size-8'
                    aria-label={t('dashboard.w.about', { title })}
                  >
                    <Info aria-hidden />
                  </Button>
                </PopoverTrigger>
                <PopoverContent className='text-sm' align='end'>
                  {info}
                </PopoverContent>
              </Popover>
            )}
            {csv && query.data !== undefined && (
              <Button
                variant='ghost'
                size='icon'
                className='size-11 sm:size-8'
                aria-label={t('dashboard.w.export_csv', { title })}
                title={t('dashboard.w.export_csv', { title })}
                onClick={() => {
                  const file = csv(query.data as T)
                  downloadCsv(file.name, file.rows)
                }}
              >
                <Download aria-hidden />
              </Button>
            )}
          </div>
        </div>
        {hint && <CardDescription>{hint}</CardDescription>}
      </CardHeader>
      <CardContent>
        {query.data !== undefined ? (
          children(query.data)
        ) : query.isError ? (
          <div className='flex flex-wrap items-center gap-3 text-sm'>
            <span className='text-destructive'>{t('dashboard.w.error')}</span>
            <Button
              variant='outline'
              size='sm'
              className='min-h-11 sm:min-h-9'
              disabled={query.isFetching}
              onClick={() => query.refetch()}
            >
              <RefreshCw /> {t('dashboard.w.retry')}
            </Button>
          </div>
        ) : (
          <div className='grid gap-2' aria-busy='true'>
            <Skeleton className='h-5' style={{ width: '66%' }} />
            <Skeleton className='h-5' style={{ width: '50%' }} />
          </div>
        )}
      </CardContent>
    </Card>
  )
}

export function Bar({ value, label }: { value: number; label: string }) {
  return (
    <div
      role='progressbar'
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={value}
      className='h-2 flex-1 overflow-hidden rounded-full bg-muted'
    >
      <div
        className='h-full rounded-full bg-primary'
        style={{ width: `${value}%` }}
      />
    </div>
  )
}
