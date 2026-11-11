import {
  queryOptions,
  useMutation,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import {
  type AddonGroup,
  type AddonOption,
  type Category,
  type Item,
  type ItemSummary,
  type Menu,
  type MenuTree,
  type Schedule,
  type Tag,
  type Translations,
  type Variant,
} from './types'

const get = async <T>(tenantId: number, path: string) =>
  (await api.get<{ data: T }>(tenantUrl(tenantId, path))).data.data

export const menuKeys = {
  all: (t: number) => ['tenant', t, 'menu'] as const,
  menus: (t: number) => ['tenant', t, 'menu', 'list'] as const,
  tree: (t: number, menuId: number) =>
    ['tenant', t, 'menu', 'tree', menuId] as const,
  item: (t: number, id: number) => ['tenant', t, 'menu', 'item', id] as const,
  addonGroups: (t: number) => ['tenant', t, 'menu', 'addon-groups'] as const,
  tags: (t: number) => ['tenant', t, 'menu', 'tags'] as const,
}

export const menusQuery = (t: number) =>
  queryOptions({
    queryKey: menuKeys.menus(t),
    queryFn: () => get<Menu[]>(t, 'menus'),
  })

export const treeQuery = (t: number, menuId: number) =>
  queryOptions({
    queryKey: menuKeys.tree(t, menuId),
    queryFn: () => get<MenuTree>(t, `menus/${menuId}/tree`),
  })

export const itemQuery = (t: number, id: number) =>
  queryOptions({
    queryKey: menuKeys.item(t, id),
    queryFn: () => get<Item>(t, `items/${id}`),
  })

export const addonGroupsQuery = (t: number) =>
  queryOptions({
    queryKey: menuKeys.addonGroups(t),
    queryFn: () => get<AddonGroup[]>(t, 'addon-groups'),
    staleTime: 60_000,
  })

export const tagsQuery = (t: number) =>
  queryOptions({
    queryKey: menuKeys.tags(t),
    queryFn: () => get<Tag[]>(t, 'tags'),
    staleTime: 5 * 60_000,
  })

/** After any menu change: refetch menus, trees and items of this restaurant. */
function refreshMenu(qc: QueryClient, t: number) {
  return qc.invalidateQueries({ queryKey: menuKeys.all(t) })
}

/** Changes every matching item in the cached trees (for instant feedback). */
function patchTreeItems(
  qc: QueryClient,
  t: number,
  ids: number[],
  patch: Partial<ItemSummary>
) {
  qc.setQueriesData<MenuTree>(
    { queryKey: ['tenant', t, 'menu', 'tree'] },
    (tree) =>
      tree && {
        ...tree,
        categories: tree.categories.map((c) => ({
          ...c,
          items: c.items.map((i) =>
            ids.includes(i.id) ? { ...i, ...patch } : i
          ),
        })),
      }
  )
}

/** A menu change; `form` = its 422 errors are shown under the fields. */
function useMenuMutation<TVars, TResult = unknown>(
  t: number,
  fn: (vars: TVars) => Promise<TResult>,
  form = false
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    meta: { inlineErrors: form },
    onSettled: () => refreshMenu(qc, t),
  })
}

// ---- Menus ----------------------------------------------------------------

export type MenuInput = {
  name: Translations
  is_visible?: boolean
  slug?: string
}

export const useCreateMenu = (t: number) =>
  useMenuMutation(
    t,
    async (input: MenuInput) =>
      (await api.post<{ data: Menu }>(tenantUrl(t, 'menus'), input)).data.data,
    true
  )

export const useUpdateMenu = (t: number) =>
  useMenuMutation(
    t,
    async ({ id, ...input }: Partial<MenuInput> & { id: number }) =>
      (await api.patch<{ data: Menu }>(tenantUrl(t, `menus/${id}`), input)).data
        .data,
    true
  )

export const useDeleteMenu = (t: number) =>
  useMenuMutation(t, (id: number) => api.delete(tenantUrl(t, `menus/${id}`)))

export const useDuplicateMenu = (t: number) =>
  useMenuMutation(
    t,
    async (id: number) =>
      (await api.post<{ data: Menu }>(tenantUrl(t, `menus/${id}/duplicate`)))
        .data.data
  )

export const useReorderMenus = (t: number) =>
  useMenuMutation(t, (ids: number[]) =>
    api.post(tenantUrl(t, 'menus/reorder'), { ids })
  )

export const useSaveSchedule = (t: number) =>
  useMenuMutation(
    t,
    async (v: {
      id: number
      schedule_mode: Menu['schedule_mode']
      schedules: Schedule[]
    }) =>
      (
        await api.put<{ data: Menu }>(tenantUrl(t, `menus/${v.id}/schedules`), {
          schedule_mode: v.schedule_mode,
          schedules: v.schedules,
        })
      ).data.data,
    true
  )

// ---- Categories -----------------------------------------------------------

export type CategoryInput = {
  menu_id?: number
  name?: Translations
  description?: Translations
  is_visible?: boolean
  media_id?: number | null
  /** All photos in order (max 5); the first is the main one. */
  photo_ids?: number[]
}

export const useCreateCategory = (t: number) =>
  useMenuMutation(
    t,
    async (input: CategoryInput) =>
      (await api.post<{ data: Category }>(tenantUrl(t, 'categories'), input))
        .data.data,
    true
  )

export const useUpdateCategory = (t: number) =>
  useMenuMutation(
    t,
    async ({ id, ...input }: CategoryInput & { id: number }) =>
      (
        await api.patch<{ data: Category }>(
          tenantUrl(t, `categories/${id}`),
          input
        )
      ).data.data,
    true
  )

export const useDeleteCategory = (t: number) =>
  useMenuMutation(t, (id: number) =>
    api.delete(tenantUrl(t, `categories/${id}`))
  )

export const useDuplicateCategory = (t: number) =>
  useMenuMutation(t, (id: number) =>
    api.post(tenantUrl(t, `categories/${id}/duplicate`))
  )

/** Saves a new order; the cached tree is updated first so the list doesn't jump. */
export function useReorder(t: number, menuId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (v: {
      kind: 'categories' | 'items'
      parentId: number
      ids: number[]
    }) =>
      api.post(
        tenantUrl(t, `${v.kind}/reorder`),
        v.kind === 'categories'
          ? { menu_id: v.parentId, ids: v.ids }
          : { category_id: v.parentId, ids: v.ids }
      ),
    onMutate: ({ kind, parentId, ids }) => {
      const key = menuKeys.tree(t, menuId)
      const before = qc.getQueryData<MenuTree>(key)
      const order = (id: number) => ids.indexOf(id)
      qc.setQueryData<MenuTree>(key, (tree) => {
        if (!tree) return tree
        if (kind === 'categories') {
          return {
            ...tree,
            categories: [...tree.categories].sort(
              (a, b) => order(a.id) - order(b.id)
            ),
          }
        }
        return {
          ...tree,
          categories: tree.categories.map((c) =>
            c.id === parentId
              ? {
                  ...c,
                  items: [...c.items].sort((a, b) => order(a.id) - order(b.id)),
                }
              : c
          ),
        }
      })
      return { before }
    },
    onError: (_e, _v, ctx) => {
      if (ctx?.before) qc.setQueryData(menuKeys.tree(t, menuId), ctx.before)
    },
    onSettled: () => refreshMenu(qc, t),
  })
}

// ---- Items ----------------------------------------------------------------

export type ItemInput = {
  category_id?: number
  name?: Translations
  summary?: Translations
  description?: Translations
  price_minor?: number | null
  media_id?: number | null
  /** All photos in order (max 5); the first is the main one. */
  photo_ids?: number[]
  calories?: number | null
  prep_minutes?: number | null
  is_visible?: boolean
  is_new?: boolean
  new_until?: string | null
  is_popular?: boolean
  is_sold_out?: boolean
  variants?: Variant[]
  addon_group_ids?: number[]
  tag_ids?: number[]
  allergens?: string[]
}

export const useCreateItem = (t: number) =>
  useMenuMutation(
    t,
    async (input: ItemInput) =>
      (await api.post<{ data: Item }>(tenantUrl(t, 'items'), input)).data.data,
    true
  )

export const useUpdateItem = (t: number) =>
  useMenuMutation(
    t,
    async ({ id, ...input }: ItemInput & { id: number }) =>
      (await api.patch<{ data: Item }>(tenantUrl(t, `items/${id}`), input)).data
        .data,
    true
  )

/** Price-only save, for staff allowed to edit prices. */
export const useUpdatePrice = (t: number) =>
  useMenuMutation(
    t,
    async (v: {
      id: number
      price_minor?: number | null
      variants?: { id: number; price_minor: number }[]
    }) =>
      (
        await api.patch<{ data: Item }>(tenantUrl(t, `items/${v.id}/price`), {
          price_minor: v.price_minor,
          variants: v.variants,
        })
      ).data.data,
    true
  )

export const useDeleteItem = (t: number) =>
  useMenuMutation(t, (id: number) => api.delete(tenantUrl(t, `items/${id}`)))

export const useDuplicateItem = (t: number) =>
  useMenuMutation(t, (id: number) =>
    api.post(tenantUrl(t, `items/${id}/duplicate`))
  )

/** Sold out / available, shown instantly and undone if saving fails. */
export function useSoldOut(t: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, is_sold_out }: { id: number; is_sold_out: boolean }) =>
      api.patch(tenantUrl(t, `items/${id}/sold-out`), { is_sold_out }),
    onMutate: ({ id, is_sold_out }) => {
      patchTreeItems(qc, t, [id], { is_sold_out })
    },
    onError: (_e, { id, is_sold_out }) => {
      patchTreeItems(qc, t, [id], { is_sold_out: !is_sold_out })
    },
    onSettled: () => refreshMenu(qc, t),
  })
}

export type BulkAction =
  | 'show'
  | 'hide'
  | 'sold_out'
  | 'available'
  | 'move'
  | 'delete'

export const useBulkItems = (t: number) =>
  useMenuMutation(
    t,
    (v: { ids: number[]; action: BulkAction; category_id?: number }) =>
      api.post(tenantUrl(t, 'items/bulk'), v)
  )

export const useBulkPrice = (t: number) =>
  useMenuMutation(
    t,
    (v: { ids: number[]; percent: number; round_to: number }) =>
      api.post(tenantUrl(t, 'items/bulk-price'), v)
  )

// ---- Add-ons and tags -----------------------------------------------------

export type AddonGroupInput = {
  name: Translations
  min_choices: number
  max_choices: number | null
  options: AddonOption[]
}

export const useSaveAddonGroup = (t: number) =>
  useMenuMutation(
    t,
    async ({ id, ...input }: AddonGroupInput & { id?: number }) =>
      id
        ? (
            await api.patch<{ data: AddonGroup }>(
              tenantUrl(t, `addon-groups/${id}`),
              input
            )
          ).data.data
        : (
            await api.post<{ data: AddonGroup }>(
              tenantUrl(t, 'addon-groups'),
              input
            )
          ).data.data,
    true
  )

export const useDeleteAddonGroup = (t: number) =>
  useMenuMutation(t, (id: number) =>
    api.delete(tenantUrl(t, `addon-groups/${id}`))
  )

export const useSaveTag = (t: number) =>
  useMenuMutation(
    t,
    async ({ id, name }: { id?: number; name: Translations }) =>
      id
        ? (await api.patch<{ data: Tag }>(tenantUrl(t, `tags/${id}`), { name }))
            .data.data
        : (await api.post<{ data: Tag }>(tenantUrl(t, 'tags'), { name })).data
            .data,
    true
  )

export const useDeleteTag = (t: number) =>
  useMenuMutation(t, (id: number) => api.delete(tenantUrl(t, `tags/${id}`)))
