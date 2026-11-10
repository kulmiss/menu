import { EyeOff, MoreVertical, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { tr } from '../text'
import { type Category, type ItemSummary } from '../types'
import { type MenuContext } from '../use-menu-context'
import { ItemRow } from './item-row'
import { SortableItem, SortableList } from './sortable'

type Props = {
  category: Category
  /** Items after the search filter. */
  items: ItemSummary[]
  ctx: MenuContext
  handle: React.ReactNode
  dragDisabled: boolean
  selecting: boolean
  selected: Set<number>
  onSelect: (id: number, selected: boolean) => void
  onOpenItem: (id: number) => void
  onAddItem: () => void
  onSoldOut: (id: number, soldOut: boolean) => void
  onReorderItems: (ids: number[]) => void
  onEdit: () => void
  onDuplicate: () => void
  onToggleVisible: () => void
  onDelete: () => void
}

export function CategoryCard(p: Props) {
  const { t } = useTranslation()
  const { category, ctx } = p
  const name = tr(category.name, ctx.locale)

  return (
    <section aria-label={name} className='rounded-lg border bg-card'>
      <header className='flex items-center gap-1 border-b py-1 pe-1'>
        {p.handle ?? <span className='w-3' />}
        <h2 className='min-w-0 flex-1 truncate px-1 font-semibold'>{name}</h2>
        {!category.is_visible && (
          <Badge variant='outline' className='gap-1'>
            <EyeOff className='size-3' aria-hidden /> {t('menu.hidden')}
          </Badge>
        )}
        <span className='px-1 text-sm text-muted-foreground tabular-nums'>
          {category.items.length}
        </span>
        {ctx.canManage && ctx.canWrite && (
          <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
              <Button
                variant='ghost'
                size='icon'
                className='size-11 sm:size-8'
                aria-label={t('menu.category_actions', { name })}
              >
                <MoreVertical />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align='end'>
              <DropdownMenuItem onClick={p.onAddItem}>
                {t('menu.add_item')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={p.onEdit}>
                {t('menu.edit_category')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={p.onToggleVisible}>
                {category.is_visible ? t('menu.hide') : t('menu.show')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={p.onDuplicate}>
                {t('menu.duplicate')}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant='destructive' onClick={p.onDelete}>
                {t('menu.delete')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </header>

      {p.items.length === 0 ? (
        <p className='px-4 py-3 text-sm text-muted-foreground'>
          {t('menu.no_items')}
        </p>
      ) : (
        <SortableList
          ids={p.items.map((i) => i.id)}
          onReorder={p.onReorderItems}
        >
          <ul className='divide-y'>
            {p.items.map((item) => (
              <SortableItem
                key={item.id}
                id={item.id}
                disabled={p.dragDisabled}
                handleLabel={t('menu.drag_item', {
                  name: tr(item.name, ctx.locale),
                })}
              >
                {(handle) => (
                  <ItemRow
                    item={item}
                    ctx={ctx}
                    handle={handle ?? <span className='w-2' />}
                    selecting={p.selecting}
                    selected={p.selected.has(item.id)}
                    onSelect={(s) => p.onSelect(item.id, s)}
                    onOpen={() => p.onOpenItem(item.id)}
                    onSoldOut={(s) => p.onSoldOut(item.id, s)}
                  />
                )}
              </SortableItem>
            ))}
          </ul>
        </SortableList>
      )}

      {ctx.canManage && ctx.canWrite && (
        <div className='border-t p-1'>
          <Button
            variant='ghost'
            className='min-h-11 w-full justify-start sm:min-h-9'
            onClick={p.onAddItem}
          >
            <Plus /> {t('menu.add_item')}
          </Button>
        </div>
      )}
    </section>
  )
}
