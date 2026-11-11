import { type ReactNode } from 'react'
import { RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

/** Card with the three states every widget shares: loading, error (retry), content. */
export function Widget<T>({
  title,
  hint,
  query,
  children,
}: {
  title: string
  hint?: string
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
        <CardTitle>{title}</CardTitle>
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
