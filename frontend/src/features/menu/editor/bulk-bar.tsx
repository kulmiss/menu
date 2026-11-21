import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useFeature } from '@/features/auth/use-me'
import { type BulkAction } from '../api'
import { tr } from '../text'
import { type Category } from '../types'
import { type MenuContext } from '../use-menu-context'

type Props = {
  count: number
  ctx: MenuContext
  categories: Category[]
  onAction: (action: BulkAction, categoryId?: number) => void
  onPrices: () => void
  onClear: () => void
}

/** Actions for the selected items, pinned to the bottom of the screen. */
export function BulkBar({
  count,
  ctx,
  categories,
  onAction,
  onPrices,
  onClear,
}: Props) {
  const { t } = useTranslation()
  const soldOutOn = useFeature('sold_out')
  const disabled = count === 0 || !ctx.canWrite

  return (
    <div
      role='toolbar'
      aria-label={t('menu.bulk_label')}
      className='sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-2 backdrop-blur'
    >
      <Button
        variant='ghost'
        size='icon'
        className='size-11 sm:size-9'
        aria-label={t('menu.clear_selection')}
        onClick={onClear}
      >
        <X />
      </Button>
      <span className='me-auto text-sm font-medium' aria-live='polite'>
        {t('menu.selected', { count })}
      </span>
      {soldOutOn && (
        <>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={disabled}
            onClick={() => onAction('sold_out')}
          >
            {t('menu.sold_out')}
          </Button>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            disabled={disabled}
            onClick={() => onAction('available')}
          >
            {t('menu.available')}
          </Button>
        </>
      )}
      {(ctx.canManage || ctx.canEditPrices) && (
        <DropdownMenu modal={false}>
          <DropdownMenuTrigger asChild>
            <Button className='min-h-11 sm:min-h-9' disabled={disabled}>
              {t('menu.more_actions')}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            align='end'
            className='max-h-[60svh] overflow-y-auto'
          >
            {ctx.canEditPrices && (
              <DropdownMenuItem onClick={onPrices}>
                {t('menu.change_prices')}
              </DropdownMenuItem>
            )}
            {ctx.canManage && (
              <>
                <DropdownMenuItem onClick={() => onAction('show')}>
                  {t('menu.show')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => onAction('hide')}>
                  {t('menu.hide')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel className='text-xs text-muted-foreground'>
                  {t('menu.move_to')}
                </DropdownMenuLabel>
                {categories.map((c) => (
                  <DropdownMenuItem
                    key={c.id}
                    onClick={() => onAction('move', c.id)}
                  >
                    {tr(c.name, ctx.locale)}
                  </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant='destructive'
                  onClick={() => onAction('delete')}
                >
                  {t('menu.delete')}
                </DropdownMenuItem>
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  )
}
