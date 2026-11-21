import * as CollapsiblePrimitive from '@radix-ui/react-collapsible'
import { ChevronDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Card } from '@/components/ui/card'

/**
 * A card whose header opens and closes it (owner, 2026-11-21). Closed, the
 * header still shows the number, title and a short summary of the choice,
 * plus a "Changed" mark for unsaved edits inside. Used by Appearance and the
 * Super Admin settings.
 */
export function CollapsibleCard({
  id,
  open,
  onOpenChange,
  title,
  number,
  icon,
  hint,
  summary,
  changed = false,
  className,
  children,
}: {
  id: string
  open: boolean
  onOpenChange: (open: boolean) => void
  title: React.ReactNode
  number?: number
  icon?: React.ReactNode
  /** Shown under the title while open. */
  hint?: React.ReactNode
  /** Shown under the title while closed. */
  summary?: React.ReactNode
  changed?: boolean
  className?: string
  children: React.ReactNode
}) {
  const { t } = useTranslation()
  return (
    <CollapsiblePrimitive.Root open={open} onOpenChange={onOpenChange} asChild>
      <Card id={id} className={cn('scroll-mt-20 gap-0 py-0', className)}>
        <h2 className='m-0'>
          <CollapsiblePrimitive.Trigger
            data-section-trigger
            className='flex min-h-11 w-full items-center gap-3 rounded-xl px-6 py-5 text-start focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
          >
            {icon && (
              <span className='flex size-9 flex-none items-center justify-center rounded-xl bg-primary/10 text-primary'>
                {icon}
              </span>
            )}
            <span className='grid min-w-0 flex-1 gap-1'>
              <span className='flex flex-wrap items-center gap-x-2 gap-y-1 text-lg leading-tight font-semibold'>
                <span>
                  {number !== undefined && (
                    <span className='me-1.5 text-muted-foreground'>
                      {number}.
                    </span>
                  )}{' '}
                  {title}
                </span>
                {changed && ' '}
                {changed && (
                  <span className='inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200'>
                    <span
                      className='size-1.5 rounded-full bg-amber-500'
                      aria-hidden
                    />
                    {t('common.changed')}
                  </span>
                )}
              </span>
              {(open ? hint : summary) && ' '}
              {(open ? hint : summary) && (
                <span
                  className={cn(
                    'text-sm font-normal text-muted-foreground',
                    !open && 'truncate'
                  )}
                >
                  {open ? hint : summary}
                </span>
              )}
            </span>
            <ChevronDown
              aria-hidden
              className={cn(
                'size-5 flex-none text-muted-foreground transition-transform duration-200 motion-reduce:transition-none',
                open && 'rotate-180'
              )}
            />
          </CollapsiblePrimitive.Trigger>
        </h2>
        <CollapsiblePrimitive.Content className='overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down motion-reduce:animate-none'>
          <div className='grid gap-5 px-6 pb-6'>{children}</div>
        </CollapsiblePrimitive.Content>
      </Card>
    </CollapsiblePrimitive.Root>
  )
}
