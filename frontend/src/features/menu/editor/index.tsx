import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CalendarClock,
  CheckSquare,
  Copy,
  Eye,
  EyeOff,
  MoreHorizontal,
  Pencil,
  Plus,
  Search,
  Trash2,
  UtensilsCrossed,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import {
  type BulkAction,
  menusQuery,
  treeQuery,
  useBulkItems,
  useBulkPrice,
  useCreateCategory,
  useCreateMenu,
  useDeleteCategory,
  useDeleteMenu,
  useDuplicateCategory,
  useDuplicateMenu,
  useReorder,
  useSaveSchedule,
  useSoldOut,
  useUpdateCategory,
  useUpdateMenu,
} from '../api'
import { tr } from '../text'
import { type Category, type Menu } from '../types'
import { type MenuContext, useMenuContext } from '../use-menu-context'
import { BulkBar } from './bulk-bar'
import { BulkPriceDialog } from './bulk-price-dialog'
import { CategoryCard } from './category-card'
import { ItemSheet } from './item-sheet'
import { NameDialog, type NameValues } from './name-dialog'
import { ScheduleDialog } from './schedule-dialog'
import { SortableItem, SortableList } from './sortable'

type Props = {
  menuId?: number
  itemId?: number
  onSelectMenu: (id: number) => void
  onOpenItem: (id: number | undefined) => void
}

/** Restaurant menu editor (PLAN.md §7.2.2): menus → categories → items. */
export function MenuEditorPage({
  menuId,
  itemId,
  onSelectMenu,
  onOpenItem,
}: Props) {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const menus = useQuery(menusQuery(ctx.tenantId))
  const createMenu = useCreateMenu(ctx.tenantId)
  const [newMenuOpen, setNewMenuOpen] = useState(false)

  const current = menus.data?.find((m) => m.id === menuId) ?? menus.data?.[0]

  return (
    <>
      <AdminHeader />
      <Main className='pb-0'>
        <div className='mb-4 flex flex-wrap items-end justify-between gap-2'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('menu.title')}
          </h1>
        </div>

        {menus.isPending ? (
          <div className='grid gap-3' aria-busy='true'>
            <Skeleton className='h-10 w-full' />
            <Skeleton className='h-40 w-full' />
          </div>
        ) : menus.isError ? (
          <p className='text-destructive'>{t('errors.generic')}</p>
        ) : !current ? (
          <div className='flex flex-col items-center gap-3 py-16 text-center'>
            <UtensilsCrossed
              className='size-10 text-muted-foreground'
              aria-hidden
            />
            <p className='text-muted-foreground'>{t('menu.no_menus')}</p>
            {ctx.canManage && (
              <Button
                className='min-h-11 sm:min-h-9'
                onClick={() => setNewMenuOpen(true)}
              >
                <Plus /> {t('menu.create_first_menu')}
              </Button>
            )}
          </div>
        ) : (
          <>
            <MenuTabs
              menus={menus.data}
              current={current}
              ctx={ctx}
              onSelect={onSelectMenu}
              onNew={() => setNewMenuOpen(true)}
            />
            <MenuBody
              key={current.id}
              menu={current}
              ctx={ctx}
              itemId={itemId}
              onOpenItem={onOpenItem}
              onMenuGone={() =>
                onSelectMenu(
                  menus.data.find((m) => m.id !== current.id)?.id ?? 0
                )
              }
            />
          </>
        )}

        <NameDialog
          open={newMenuOpen}
          onOpenChange={setNewMenuOpen}
          title={t('menu.new_menu')}
          locale={ctx.locale}
          initial={{ name: {}, is_visible: true }}
          onSave={async (v) => {
            const menu = await createMenu.mutateAsync({
              name: v.name,
              is_visible: v.is_visible,
            })
            onSelectMenu(menu.id)
          }}
        />
      </Main>
    </>
  )
}

function MenuTabs({
  menus,
  current,
  ctx,
  onSelect,
  onNew,
}: {
  menus: Menu[]
  current: Menu
  ctx: MenuContext
  onSelect: (id: number) => void
  onNew: () => void
}) {
  const { t } = useTranslation()
  return (
    <nav
      aria-label={t('menu.menus')}
      className='-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1'
    >
      {menus.map((m) => (
        <Button
          key={m.id}
          variant={m.id === current.id ? 'default' : 'outline'}
          aria-current={m.id === current.id ? 'page' : undefined}
          className='min-h-11 shrink-0 rounded-full sm:min-h-9'
          onClick={() => onSelect(m.id)}
        >
          {!m.is_visible && <EyeOff aria-label={t('menu.hidden')} />}
          {tr(m.name, ctx.locale)}
          {m.schedule_mode === 'scheduled' && (
            <CalendarClock aria-label={t('menu.scheduled')} />
          )}
        </Button>
      ))}
      {ctx.canManage && ctx.canWrite && (
        <Button
          variant='ghost'
          className='min-h-11 shrink-0 rounded-full sm:min-h-9'
          onClick={onNew}
        >
          <Plus /> {t('menu.new_menu')}
        </Button>
      )}
    </nav>
  )
}

type Dialog =
  | { kind: 'rename-menu' }
  | { kind: 'schedule' }
  | { kind: 'delete-menu' }
  | { kind: 'new-category' }
  | { kind: 'edit-category'; category: Category }
  | { kind: 'delete-category'; category: Category }
  | { kind: 'bulk-price' }
  | { kind: 'bulk-delete' }
  | null

function MenuBody({
  menu,
  ctx,
  itemId,
  onOpenItem,
  onMenuGone,
}: {
  menu: Menu
  ctx: MenuContext
  itemId?: number
  onOpenItem: (id: number | undefined) => void
  onMenuGone: () => void
}) {
  const { t } = useTranslation()
  const tree = useQuery(treeQuery(ctx.tenantId, menu.id))
  const [search, setSearch] = useState('')
  const [selecting, setSelecting] = useState(false)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [dialog, setDialog] = useState<Dialog>(null)
  const [newItemIn, setNewItemIn] = useState<number | null>(null)

  const T = ctx.tenantId
  const updateMenu = useUpdateMenu(T)
  const deleteMenu = useDeleteMenu(T)
  const duplicateMenu = useDuplicateMenu(T)
  const saveSchedule = useSaveSchedule(T)
  const createCategory = useCreateCategory(T)
  const updateCategory = useUpdateCategory(T)
  const deleteCategory = useDeleteCategory(T)
  const duplicateCategory = useDuplicateCategory(T)
  const reorder = useReorder(T, menu.id)
  const soldOut = useSoldOut(T)
  const bulk = useBulkItems(T)
  const bulkPrice = useBulkPrice(T)

  const categories = useMemo(() => tree.data?.categories ?? [], [tree.data])
  const query = search.trim().toLocaleLowerCase()
  const filtered = useMemo(
    () =>
      categories.map((c) => ({
        category: c,
        items: query
          ? c.items.filter((i) =>
              tr(i.name, ctx.locale).toLocaleLowerCase().includes(query)
            )
          : c.items,
      })),
    [categories, query, ctx.locale]
  )
  const dragDisabled =
    !ctx.canManage || !ctx.canWrite || query !== '' || selecting

  const select = (id: number, on: boolean) =>
    setSelected((s) => {
      const next = new Set(s)
      if (on) next.add(id)
      else next.delete(id)
      return next
    })

  const endSelection = () => {
    setSelecting(false)
    setSelected(new Set())
  }

  function runBulk(action: BulkAction, categoryId?: number) {
    if (action === 'delete') return setDialog({ kind: 'bulk-delete' })
    bulk.mutate(
      { ids: [...selected], action, category_id: categoryId },
      {
        onSuccess: () =>
          toast.success(t('menu.bulk_done', { count: selected.size })),
      }
    )
  }

  const sheetOpen = itemId !== undefined || newItemIn !== null

  return (
    <>
      <div className='mb-3 flex flex-wrap items-center gap-2'>
        <div className='relative min-w-48 flex-1'>
          <Search
            className='pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground'
            aria-hidden
          />
          <Input
            type='search'
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('menu.search_items')}
            aria-label={t('menu.search_items')}
            className='min-h-11 ps-9 sm:min-h-9'
          />
        </div>
        <Button
          variant={selecting ? 'secondary' : 'outline'}
          className='min-h-11 sm:min-h-9'
          aria-pressed={selecting}
          onClick={() => (selecting ? endSelection() : setSelecting(true))}
        >
          <CheckSquare /> {t('menu.select')}
        </Button>
        {ctx.canManage && ctx.canWrite && (
          <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
              <Button
                variant='outline'
                size='icon'
                className='size-11 sm:size-9'
                aria-label={t('menu.menu_actions', {
                  name: tr(menu.name, ctx.locale),
                })}
              >
                <MoreHorizontal />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align='end'>
              <DropdownMenuItem
                onClick={() => setDialog({ kind: 'rename-menu' })}
              >
                <Pencil /> {t('menu.edit_menu')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setDialog({ kind: 'schedule' })}>
                <CalendarClock /> {t('menu.schedule_title')}
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={() =>
                  updateMenu.mutate({
                    id: menu.id,
                    is_visible: !menu.is_visible,
                  })
                }
              >
                {menu.is_visible ? <EyeOff /> : <Eye />}{' '}
                {menu.is_visible ? t('menu.hide') : t('menu.show')}
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={() =>
                  duplicateMenu.mutate(menu.id, {
                    onSuccess: () => toast.success(t('menu.duplicated')),
                  })
                }
              >
                <Copy /> {t('menu.duplicate')}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                variant='destructive'
                onClick={() => setDialog({ kind: 'delete-menu' })}
              >
                <Trash2 /> {t('menu.delete')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>

      {menu.schedule_mode === 'scheduled' && (
        <p className='mb-3 flex items-center gap-2 text-sm text-muted-foreground'>
          <CalendarClock className='size-4' aria-hidden />
          {t('menu.scheduled_note', { count: menu.schedules?.length ?? 0 })}
        </p>
      )}

      {tree.isPending ? (
        <div className='grid gap-3' aria-busy='true'>
          <Skeleton className='h-32 w-full' />
          <Skeleton className='h-32 w-full' />
        </div>
      ) : tree.isError ? (
        <p className='text-destructive'>{t('errors.generic')}</p>
      ) : categories.length === 0 ? (
        <div className='flex flex-col items-center gap-3 py-12 text-center text-muted-foreground'>
          <p>{t('menu.no_categories')}</p>
        </div>
      ) : (
        <SortableList
          ids={categories.map((c) => c.id)}
          onReorder={(ids) =>
            reorder.mutate({ kind: 'categories', parentId: menu.id, ids })
          }
        >
          <div className={cn('grid gap-4', selecting && 'pb-2')}>
            {filtered
              .filter(({ items }) => !query || items.length > 0)
              .map(({ category, items }) => (
                <SortableItem
                  key={category.id}
                  id={category.id}
                  as='div'
                  disabled={dragDisabled}
                  handleLabel={t('menu.drag_category', {
                    name: tr(category.name, ctx.locale),
                  })}
                >
                  {(handle) => (
                    <CategoryCard
                      category={category}
                      items={items}
                      ctx={ctx}
                      handle={handle}
                      dragDisabled={dragDisabled}
                      selecting={selecting}
                      selected={selected}
                      onSelect={select}
                      onOpenItem={(id) => onOpenItem(id)}
                      onAddItem={() => setNewItemIn(category.id)}
                      onSoldOut={(id, is_sold_out) =>
                        soldOut.mutate({ id, is_sold_out })
                      }
                      onReorderItems={(ids) =>
                        reorder.mutate({
                          kind: 'items',
                          parentId: category.id,
                          ids,
                        })
                      }
                      onEdit={() =>
                        setDialog({ kind: 'edit-category', category })
                      }
                      onDuplicate={() =>
                        duplicateCategory.mutate(category.id, {
                          onSuccess: () => toast.success(t('menu.duplicated')),
                        })
                      }
                      onToggleVisible={() =>
                        updateCategory.mutate({
                          id: category.id,
                          is_visible: !category.is_visible,
                        })
                      }
                      onDelete={() =>
                        setDialog({ kind: 'delete-category', category })
                      }
                    />
                  )}
                </SortableItem>
              ))}
          </div>
        </SortableList>
      )}

      {ctx.canManage && ctx.canWrite && !tree.isPending && (
        <Button
          variant='outline'
          className='mt-4 mb-4 min-h-11 w-full sm:min-h-9'
          onClick={() => setDialog({ kind: 'new-category' })}
        >
          <Plus /> {t('menu.add_category')}
        </Button>
      )}

      {selecting && (
        <BulkBar
          count={selected.size}
          ctx={ctx}
          categories={categories}
          onAction={runBulk}
          onPrices={() => setDialog({ kind: 'bulk-price' })}
          onClear={endSelection}
        />
      )}

      <ItemSheet
        ctx={ctx}
        itemId={itemId ?? null}
        newInCategory={newItemIn}
        categories={categories}
        open={sheetOpen}
        onOpenChange={(open) => {
          if (!open) {
            setNewItemIn(null)
            onOpenItem(undefined)
          }
        }}
      />

      <NameDialog
        open={dialog?.kind === 'rename-menu'}
        onOpenChange={(o) => !o && setDialog(null)}
        title={t('menu.edit_menu')}
        locale={ctx.locale}
        initial={{ name: menu.name, is_visible: menu.is_visible }}
        onSave={(v) =>
          updateMenu.mutateAsync({
            id: menu.id,
            name: v.name,
            is_visible: v.is_visible,
          })
        }
      />
      <ScheduleDialog
        menu={menu}
        open={dialog?.kind === 'schedule'}
        onOpenChange={(o) => !o && setDialog(null)}
        onSave={(v) => saveSchedule.mutateAsync({ id: menu.id, ...v })}
      />
      <NameDialog
        open={
          dialog?.kind === 'new-category' || dialog?.kind === 'edit-category'
        }
        onOpenChange={(o) => !o && setDialog(null)}
        title={
          dialog?.kind === 'edit-category'
            ? t('menu.edit_category')
            : t('menu.new_category')
        }
        locale={ctx.locale}
        withDescription
        initial={
          dialog?.kind === 'edit-category'
            ? {
                name: dialog.category.name,
                description: dialog.category.description,
                is_visible: dialog.category.is_visible,
              }
            : { name: {}, description: {}, is_visible: true }
        }
        onSave={(v: NameValues) =>
          dialog?.kind === 'edit-category'
            ? updateCategory.mutateAsync({ id: dialog.category.id, ...v })
            : createCategory.mutateAsync({ menu_id: menu.id, ...v })
        }
      />
      <ConfirmDialog
        open={dialog?.kind === 'delete-menu'}
        onOpenChange={(o) => !o && setDialog(null)}
        title={t('menu.delete_menu')}
        desc={t('menu.delete_menu_confirm', {
          name: tr(menu.name, ctx.locale),
          count: menu.items_count ?? 0,
        })}
        confirmText={t('menu.delete')}
        destructive
        isLoading={deleteMenu.isPending}
        handleConfirm={() =>
          deleteMenu.mutate(menu.id, {
            onSuccess: () => {
              setDialog(null)
              onMenuGone()
            },
          })
        }
      />
      {dialog?.kind === 'delete-category' && (
        <ConfirmDialog
          open
          onOpenChange={(o) => !o && setDialog(null)}
          title={t('menu.delete_category')}
          desc={t('menu.delete_category_confirm', {
            name: tr(dialog.category.name, ctx.locale),
            count: dialog.category.items.length,
          })}
          confirmText={t('menu.delete')}
          destructive
          isLoading={deleteCategory.isPending}
          handleConfirm={() =>
            deleteCategory.mutate(dialog.category.id, {
              onSuccess: () => setDialog(null),
            })
          }
        />
      )}
      <ConfirmDialog
        open={dialog?.kind === 'bulk-delete'}
        onOpenChange={(o) => !o && setDialog(null)}
        title={t('menu.delete_items')}
        desc={t('menu.delete_items_confirm', { count: selected.size })}
        confirmText={t('menu.delete')}
        destructive
        isLoading={bulk.isPending}
        handleConfirm={() =>
          bulk.mutate(
            { ids: [...selected], action: 'delete' },
            {
              onSuccess: () => {
                setDialog(null)
                endSelection()
              },
            }
          )
        }
      />
      <BulkPriceDialog
        count={selected.size}
        currency={ctx.currency}
        open={dialog?.kind === 'bulk-price'}
        onOpenChange={(o) => !o && setDialog(null)}
        onApply={(v) =>
          bulkPrice.mutateAsync(
            { ids: [...selected], ...v },
            {
              onSuccess: () =>
                toast.success(
                  t('menu.prices_updated', { count: selected.size })
                ),
            }
          )
        }
      />
      {!ctx.canWrite && (
        <Badge variant='destructive' className='fixed end-4 bottom-4'>
          {t('errors.read_only')}
        </Badge>
      )}
    </>
  )
}
