import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Copy, Loader2, Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ConfirmDialog } from '@/components/confirm-dialog'
import {
  addonGroupsQuery,
  itemQuery,
  type ItemInput,
  tagsQuery,
  useCreateItem,
  useDeleteItem,
  useDuplicateItem,
  useUpdateItem,
  useUpdatePrice,
} from '../api'
import { MoneyInput } from '../components/money-input'
import { PhotoField } from '../components/photo-field'
import { TranslatableInput } from '../components/translatable-input'
import { tagLabel, tr } from '../text'
import {
  ALLERGENS,
  type Category,
  type Item,
  type Media,
  type Variant,
} from '../types'
import { type MenuContext } from '../use-menu-context'

type Draft = Required<
  Pick<
    ItemInput,
    | 'category_id'
    | 'name'
    | 'summary'
    | 'description'
    | 'price_minor'
    | 'calories'
    | 'prep_minutes'
    | 'is_visible'
    | 'is_new'
    | 'new_until'
    | 'is_popular'
    | 'variants'
    | 'addon_group_ids'
    | 'tag_ids'
    | 'allergens'
  >
>

const emptyDraft = (categoryId: number): Draft => ({
  category_id: categoryId,
  name: {},
  summary: {},
  description: {},
  price_minor: null,
  calories: null,
  prep_minutes: null,
  is_visible: true,
  is_new: false,
  new_until: null,
  is_popular: false,
  variants: [],
  addon_group_ids: [],
  tag_ids: [],
  allergens: [],
})

const fromItem = (item: Item): Draft => ({
  category_id: item.category_id,
  name: item.name,
  summary: item.summary,
  description: item.description,
  price_minor: item.price_minor,
  calories: item.calories,
  prep_minutes: item.prep_minutes,
  is_visible: item.is_visible,
  is_new: item.is_new,
  new_until: item.new_until,
  is_popular: item.is_popular,
  variants: item.variants,
  addon_group_ids: item.addon_group_ids,
  tag_ids: item.tag_ids,
  allergens: item.allergens,
})

type Tab = 'details' | 'prices' | 'addons' | 'tags'

/** Which tab shows the server's message for a field. */
function tabOf(field: string): Tab {
  if (field.startsWith('variants') || field === 'price_minor') return 'prices'
  if (field.startsWith('addon_group_ids')) return 'addons'
  if (field.startsWith('tag_ids') || field.startsWith('allergens'))
    return 'tags'
  return 'details'
}

type Props = {
  ctx: MenuContext
  /** null = new item in `newInCategory`. */
  itemId: number | null
  newInCategory: number | null
  categories: Category[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated?: (id: number) => void
}

/** The item editor: a sheet on desktop, full screen on phones. */
export function ItemSheet(props: Props) {
  const { t } = useTranslation()
  const { ctx, itemId, open, onOpenChange } = props
  const item = useQuery({
    ...itemQuery(ctx.tenantId, itemId ?? 0),
    enabled: open && itemId !== null,
  })

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side='right'
        className='flex w-full flex-col gap-0 p-0 sm:max-w-xl'
      >
        {itemId !== null && !item.data ? (
          <>
            <SheetHeader className='border-b'>
              <SheetTitle>{t('menu.item')}</SheetTitle>
              <SheetDescription className='sr-only'>
                {t('menu.item_editor')}
              </SheetDescription>
            </SheetHeader>
            <div className='grid gap-3 p-4' aria-busy='true'>
              <Skeleton className='h-10 w-full' />
              <Skeleton className='h-24 w-full' />
            </div>
          </>
        ) : (
          // Keyed: another item (or a new one) starts from a fresh form.
          <ItemForm
            key={itemId ?? `new-${props.newInCategory}`}
            {...props}
            item={item.data ?? null}
          />
        )}
      </SheetContent>
    </Sheet>
  )
}

function ItemForm({
  ctx,
  itemId,
  newInCategory,
  categories,
  onOpenChange,
  onCreated,
  item,
}: Props & { item: Item | null }) {
  const { t } = useTranslation()
  const editing = itemId !== null
  const priceOnly = !ctx.canManage && ctx.canEditPrices
  const groups = useQuery({
    ...addonGroupsQuery(ctx.tenantId),
    enabled: ctx.canManage,
  })
  const tags = useQuery({
    ...tagsQuery(ctx.tenantId),
    enabled: ctx.canManage,
  })

  const [draft, setDraft] = useState<Draft>(() =>
    item ? fromItem(item) : emptyDraft(newInCategory ?? categories[0]?.id ?? 0)
  )
  const [photo, setPhoto] = useState<Media | null>(item?.media ?? null)
  const [tab, setTab] = useState<Tab>(priceOnly ? 'prices' : 'details')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [confirmDelete, setConfirmDelete] = useState(false)

  const create = useCreateItem(ctx.tenantId)
  const update = useUpdateItem(ctx.tenantId)
  const savePrice = useUpdatePrice(ctx.tenantId)
  const remove = useDeleteItem(ctx.tenantId)
  const duplicate = useDuplicateItem(ctx.tenantId)
  const saving = create.isPending || update.isPending || savePrice.isPending

  const set = <K extends keyof Draft>(key: K, value: Draft[K]) =>
    setDraft((d) => ({ ...d, [key]: value }))
  const setVariant = (i: number, patch: Partial<Variant>) =>
    set(
      'variants',
      draft.variants.map((v, j) =>
        j === i
          ? { ...v, ...patch }
          : patch.is_default
            ? { ...v, is_default: false }
            : v
      )
    )

  async function save() {
    if (!draft.name[ctx.locale]?.trim() && !priceOnly) {
      setErrors({ name: t('validation.menu_name') })
      setTab('details')
      return
    }
    setErrors({})
    try {
      if (priceOnly && itemId) {
        await savePrice.mutateAsync({
          id: itemId,
          price_minor: draft.variants.length ? undefined : draft.price_minor,
          variants: draft.variants
            .filter((v): v is Variant & { id: number } => v.id !== undefined)
            .map((v) => ({ id: v.id, price_minor: v.price_minor })),
        })
      } else if (itemId) {
        await update.mutateAsync({
          id: itemId,
          ...draft,
          media_id: photo?.id ?? null,
        })
      } else {
        const created = await create.mutateAsync({
          ...draft,
          media_id: photo?.id ?? null,
        })
        onCreated?.(created.id)
      }
      toast.success(t('menu.saved'))
      onOpenChange(false)
    } catch (error) {
      // Other failures are already shown as a toast (see main.tsx).
      const fields = validationErrors(error)
      if (fields) {
        setErrors(fields)
        setTab(tabOf(Object.keys(fields)[0]))
      }
    }
  }

  const title = editing
    ? tr(item?.name ?? draft.name, ctx.locale) || t('menu.item')
    : t('menu.new_item')

  return (
    <>
      <SheetHeader className='border-b'>
        <SheetTitle className='truncate pe-8'>{title}</SheetTitle>
        <SheetDescription className='sr-only'>
          {t('menu.item_editor')}
        </SheetDescription>
      </SheetHeader>

      <Tabs
        value={tab}
        onValueChange={(v) => setTab(v as Tab)}
        className='min-h-0 flex-1 gap-0'
      >
        {!priceOnly && (
          <div className='overflow-x-auto border-b px-4 py-2'>
            <TabsList>
              <TabsTrigger value='details'>{t('menu.tab_details')}</TabsTrigger>
              <TabsTrigger value='prices'>{t('menu.tab_prices')}</TabsTrigger>
              <TabsTrigger value='addons'>{t('menu.tab_addons')}</TabsTrigger>
              <TabsTrigger value='tags'>{t('menu.tab_tags')}</TabsTrigger>
            </TabsList>
          </div>
        )}

        <div className='min-h-0 flex-1 overflow-y-auto p-4'>
          <TabsContent value='details' className='grid gap-4'>
            <PhotoField
              tenantId={ctx.tenantId}
              label={t('photo.label')}
              media={photo}
              onChange={setPhoto}
              disabled={!ctx.canWrite}
            />
            <TranslatableInput
              label={t('menu.name')}
              locale={ctx.locale}
              value={draft.name}
              onChange={(v) => set('name', v)}
              maxLength={120}
              error={errors.name}
              autoFocus={!editing}
            />
            <TranslatableInput
              label={t('menu.summary')}
              locale={ctx.locale}
              value={draft.summary}
              onChange={(v) => set('summary', v)}
              maxLength={160}
              error={errors.summary}
              placeholder={t('menu.summary_hint')}
            />
            <TranslatableInput
              label={t('menu.description')}
              locale={ctx.locale}
              value={draft.description}
              onChange={(v) => set('description', v)}
              maxLength={2000}
              multiline
              error={errors.description}
            />
            <div className='grid gap-2'>
              <Label>{t('menu.category')}</Label>
              <Select
                value={String(draft.category_id)}
                onValueChange={(v) => set('category_id', Number(v))}
              >
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('menu.category')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {categories.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {tr(c.name, ctx.locale)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <ToggleRow
              label={t('menu.visible_to_guests')}
              checked={draft.is_visible}
              onChange={(v) => set('is_visible', v)}
            />
            <ToggleRow
              label={t('menu.badge_popular')}
              checked={draft.is_popular}
              onChange={(v) => set('is_popular', v)}
            />
            <ToggleRow
              label={t('menu.badge_new')}
              checked={draft.is_new}
              onChange={(v) => set('is_new', v)}
            />
            {draft.is_new && (
              <NumberOrDate
                label={t('menu.new_until')}
                type='date'
                value={draft.new_until ?? ''}
                onChange={(v) => set('new_until', v || null)}
                error={errors.new_until}
              />
            )}
            <div className='grid grid-cols-2 gap-4'>
              <NumberOrDate
                label={t('menu.calories')}
                type='number'
                value={draft.calories ?? ''}
                onChange={(v) => set('calories', v === '' ? null : Number(v))}
                error={errors.calories}
              />
              <NumberOrDate
                label={t('menu.prep_minutes')}
                type='number'
                value={draft.prep_minutes ?? ''}
                onChange={(v) =>
                  set('prep_minutes', v === '' ? null : Number(v))
                }
                error={errors.prep_minutes}
              />
            </div>
          </TabsContent>

          <TabsContent value='prices' className='grid gap-4'>
            {draft.variants.length === 0 ? (
              <MoneyInput
                label={t('menu.price')}
                currency={ctx.currency}
                value={draft.price_minor}
                onChange={(v) => set('price_minor', v)}
                error={errors.price_minor}
              />
            ) : (
              <fieldset className='grid gap-3'>
                <legend className='mb-2 text-sm font-medium'>
                  {t('menu.sizes')}
                </legend>
                {draft.variants.map((v, i) => (
                  <div
                    key={v.id ?? `new-${i}`}
                    className='grid gap-2 rounded-md border p-3'
                  >
                    {priceOnly ? (
                      <p className='font-medium'>{tr(v.name, ctx.locale)}</p>
                    ) : (
                      <TranslatableInput
                        label={t('menu.size_name')}
                        locale={ctx.locale}
                        value={v.name}
                        onChange={(name) => setVariant(i, { name })}
                        maxLength={60}
                        error={errors[`variants.${i}.name`]}
                      />
                    )}
                    <MoneyInput
                      label={t('menu.price_of', {
                        name:
                          tr(v.name, ctx.locale) ||
                          t('menu.size_n', { n: i + 1 }),
                      })}
                      currency={ctx.currency}
                      value={v.price_minor}
                      onChange={(p) => setVariant(i, { price_minor: p ?? 0 })}
                      error={errors[`variants.${i}.price_minor`]}
                    />
                    {!priceOnly && (
                      <div className='flex items-center justify-between'>
                        <Label className='flex min-h-11 items-center gap-2 font-normal'>
                          <Checkbox
                            checked={v.is_default}
                            onCheckedChange={(c) =>
                              setVariant(i, { is_default: c === true })
                            }
                          />
                          {t('menu.default_size')}
                        </Label>
                        <Button
                          type='button'
                          variant='ghost'
                          size='icon'
                          className='size-11 sm:size-9'
                          aria-label={t('menu.remove_size')}
                          onClick={() =>
                            set(
                              'variants',
                              draft.variants.filter((_, j) => j !== i)
                            )
                          }
                        >
                          <Trash2 />
                        </Button>
                      </div>
                    )}
                  </div>
                ))}
                {errors.variants && (
                  <p className='text-sm text-destructive'>{errors.variants}</p>
                )}
              </fieldset>
            )}
            {!priceOnly && (
              <Button
                type='button'
                variant='outline'
                className='min-h-11 sm:min-h-9'
                onClick={() =>
                  set('variants', [
                    ...draft.variants,
                    {
                      name: {},
                      // The first size starts at the current price.
                      price_minor:
                        draft.variants.length === 0
                          ? (draft.price_minor ?? 0)
                          : 0,
                      is_default: false,
                    },
                  ])
                }
              >
                <Plus /> {t('menu.add_size')}
              </Button>
            )}
            <p className='text-xs text-muted-foreground'>
              {t('menu.sizes_hint')}
            </p>
          </TabsContent>

          <TabsContent value='addons' className='grid gap-2'>
            <p className='text-sm text-muted-foreground'>
              {t('menu.addons_hint')}
            </p>
            {groups.data?.length === 0 && (
              <p className='text-sm'>{t('menu.no_addon_groups')}</p>
            )}
            {groups.data?.map((g) => (
              <Label
                key={g.id}
                className='flex min-h-11 items-center gap-3 rounded-md border px-3 font-normal'
              >
                <Checkbox
                  checked={draft.addon_group_ids.includes(g.id)}
                  onCheckedChange={(c) =>
                    set(
                      'addon_group_ids',
                      c === true
                        ? [...draft.addon_group_ids, g.id]
                        : draft.addon_group_ids.filter((id) => id !== g.id)
                    )
                  }
                />
                <span className='flex-1'>{tr(g.name, ctx.locale)}</span>
                <span className='text-xs text-muted-foreground'>
                  {t('menu.options_count', { count: g.options.length })}
                </span>
              </Label>
            ))}
            <Button asChild variant='link' className='justify-start px-0'>
              <Link to='/admin/addons'>{t('menu.manage_addons')}</Link>
            </Button>
          </TabsContent>

          <TabsContent value='tags' className='grid gap-6'>
            <fieldset>
              <legend className='mb-2 text-sm font-medium'>
                {t('menu.tags')}
              </legend>
              <div className='flex flex-wrap gap-2'>
                {tags.data?.map((tag) => {
                  const on = draft.tag_ids.includes(tag.id)
                  return (
                    <Button
                      key={tag.id}
                      type='button'
                      variant={on ? 'default' : 'outline'}
                      aria-pressed={on}
                      className='min-h-11 rounded-full sm:min-h-8'
                      onClick={() =>
                        set(
                          'tag_ids',
                          on
                            ? draft.tag_ids.filter((id) => id !== tag.id)
                            : [...draft.tag_ids, tag.id]
                        )
                      }
                    >
                      {tagLabel(tag, ctx.locale, t)}
                    </Button>
                  )
                })}
              </div>
              <Button asChild variant='link' className='mt-1 px-0'>
                <Link to='/admin/tags'>{t('menu.manage_tags')}</Link>
              </Button>
            </fieldset>
            <fieldset>
              <legend className='mb-2 text-sm font-medium'>
                {t('menu.allergens')}
              </legend>
              <div className='grid grid-cols-2 gap-x-4 sm:grid-cols-3'>
                {ALLERGENS.map((a) => (
                  <Label
                    key={a}
                    className='flex min-h-11 items-center gap-2 font-normal'
                  >
                    <Checkbox
                      checked={draft.allergens.includes(a)}
                      onCheckedChange={(c) =>
                        set(
                          'allergens',
                          c === true
                            ? [...draft.allergens, a]
                            : draft.allergens.filter((x) => x !== a)
                        )
                      }
                    />
                    {t(`allergens.${a}`)}
                  </Label>
                ))}
              </div>
            </fieldset>
          </TabsContent>
        </div>
      </Tabs>

      <SheetFooter className='flex-row flex-wrap gap-2 border-t'>
        {editing && ctx.canManage && (
          <>
            <Button
              type='button'
              variant='ghost'
              size='icon'
              className='size-11 sm:size-9'
              aria-label={t('menu.delete')}
              onClick={() => setConfirmDelete(true)}
            >
              <Trash2 />
            </Button>
            <Button
              type='button'
              variant='ghost'
              size='icon'
              className='size-11 sm:size-9'
              aria-label={t('menu.duplicate')}
              disabled={duplicate.isPending}
              onClick={() =>
                duplicate.mutate(itemId, {
                  onSuccess: () => {
                    toast.success(t('menu.duplicated'))
                    onOpenChange(false)
                  },
                })
              }
            >
              <Copy />
            </Button>
          </>
        )}
        <Button
          type='button'
          className={cn('ms-auto min-h-11 sm:min-h-9')}
          disabled={saving || !ctx.canWrite}
          onClick={save}
        >
          {saving && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </SheetFooter>

      {editing && (
        <ConfirmDialog
          open={confirmDelete}
          onOpenChange={setConfirmDelete}
          title={t('menu.delete_item')}
          desc={t('menu.delete_item_confirm', { name: title })}
          confirmText={t('menu.delete')}
          destructive
          isLoading={remove.isPending}
          handleConfirm={() =>
            remove.mutate(itemId, {
              onSuccess: () => {
                setConfirmDelete(false)
                onOpenChange(false)
              },
            })
          }
        />
      )}
    </>
  )
}

function ToggleRow({
  label,
  checked,
  onChange,
}: {
  label: string
  checked: boolean
  onChange: (v: boolean) => void
}) {
  return (
    <Label className='flex min-h-11 items-center justify-between gap-4 font-normal'>
      {label}
      <Switch checked={checked} onCheckedChange={onChange} />
    </Label>
  )
}

function NumberOrDate(props: {
  label: string
  type: 'number' | 'date'
  value: string | number
  onChange: (v: string) => void
  error?: string
}) {
  return (
    <Label className='grid gap-2'>
      {props.label}
      <Input
        type={props.type}
        inputMode={props.type === 'number' ? 'numeric' : undefined}
        min={props.type === 'number' ? 0 : undefined}
        dir='ltr'
        value={props.value}
        onChange={(e) => props.onChange(e.target.value)}
        aria-invalid={props.error ? true : undefined}
        className='min-h-11 font-normal sm:min-h-9'
      />
      {props.error && (
        <span className='text-sm font-normal text-destructive'>
          {props.error}
        </span>
      )}
    </Label>
  )
}
