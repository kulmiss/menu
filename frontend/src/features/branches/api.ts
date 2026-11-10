import {
  queryOptions,
  useMutation,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { menuKeys } from '@/features/menu/api'
import { type Translations } from '@/features/menu/types'

export type Branch = {
  id: number
  slug: string
  name: Translations
  address: Translations
  phone: string | null
  maps_url: string | null
  is_default: boolean
  is_active: boolean
  tables_count: number
  qr_code: string | null
  menu_ids: number[]
  url: string
}

export type DiningTable = {
  id: number
  branch_id: number
  label: string
  qr_code: string | null
  qr_id: number | null
}

export type Override = {
  item_id: number
  price_minor: number | null
  is_sold_out: boolean | null
  is_hidden: boolean | null
}

export type QrCodeRow = {
  id: number
  code: string
  url: string
  label: string | null
  kind: 'restaurant' | 'branch' | 'table' | 'extra'
  branch_id: number | null
  dining_table_id: number | null
  description: string
}

export const branchKeys = {
  all: (t: number) => ['tenant', t, 'branches'] as const,
  list: (t: number) => ['tenant', t, 'branches', 'list'] as const,
  tables: (t: number, b: number) =>
    ['tenant', t, 'branches', 'tables', b] as const,
  overrides: (t: number, b: number) =>
    ['tenant', t, 'branches', 'overrides', b] as const,
  qr: (t: number) => ['tenant', t, 'branches', 'qr'] as const,
}

const get = async <T>(t: number, path: string) =>
  (await api.get<{ data: T }>(tenantUrl(t, path))).data.data

export const branchesQuery = (t: number) =>
  queryOptions({
    queryKey: branchKeys.list(t),
    queryFn: () => get<Branch[]>(t, 'branches'),
  })
export const tablesQuery = (t: number, b: number) =>
  queryOptions({
    queryKey: branchKeys.tables(t, b),
    queryFn: () => get<DiningTable[]>(t, `branches/${b}/tables`),
  })
export const overridesQuery = (t: number, b: number) =>
  queryOptions({
    queryKey: branchKeys.overrides(t, b),
    queryFn: () => get<Override[]>(t, `branches/${b}/overrides`),
  })
export const qrCodesQuery = (t: number) =>
  queryOptions({
    queryKey: branchKeys.qr(t),
    queryFn: () => get<QrCodeRow[]>(t, 'qr-codes'),
  })

/** Address of a download (the browser sends the session cookie). */
export const downloadUrl = (t: number, path: string) =>
  `${api.defaults.baseURL}${tenantUrl(t, path)}`

function refresh(qc: QueryClient, t: number) {
  return Promise.all([
    qc.invalidateQueries({ queryKey: branchKeys.all(t) }),
    qc.invalidateQueries({ queryKey: menuKeys.menus(t) }),
  ])
}

function useBranchMutation<V, R = unknown>(
  t: number,
  fn: (v: V) => Promise<R>,
  form = false
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    meta: { inlineErrors: form },
    onSettled: () => refresh(qc, t),
  })
}

export type BranchInput = {
  name: Translations
  slug: string
  address?: Translations
  phone?: string | null
  maps_url?: string | null
  is_active?: boolean
  is_default?: boolean
}

export const useSaveBranch = (t: number) =>
  useBranchMutation(
    t,
    async ({ id, ...v }: BranchInput & { id?: number }) =>
      id
        ? (await api.patch<{ data: Branch }>(tenantUrl(t, `branches/${id}`), v))
            .data.data
        : (await api.post<{ data: Branch }>(tenantUrl(t, 'branches'), v)).data
            .data,
    true
  )

export const useDeleteBranch = (t: number) =>
  useBranchMutation(t, (id: number) =>
    api.delete(tenantUrl(t, `branches/${id}`))
  )

export const useMenuBranches = (t: number) =>
  useBranchMutation(t, ({ menu, ids }: { menu: number; ids: number[] }) =>
    api.put(tenantUrl(t, `menus/${menu}/branches`), { branch_ids: ids })
  )

export const useAddTables = (t: number, branch: number) =>
  useBranchMutation(
    t,
    (v: { label?: string; prefix?: string; from?: number; to?: number }) =>
      api.post(tenantUrl(t, `branches/${branch}/tables`), v),
    true
  )

export const useDeleteTable = (t: number) =>
  useBranchMutation(t, (id: number) => api.delete(tenantUrl(t, `tables/${id}`)))

export const useSaveOverrides = (t: number, branch: number) =>
  useBranchMutation(t, (overrides: Override[]) =>
    api.put(tenantUrl(t, `branches/${branch}/overrides`), { overrides })
  )

export const useAddQrCode = (t: number) =>
  useBranchMutation(
    t,
    (v: { label: string; branch_id?: number | null }) =>
      api.post(tenantUrl(t, 'qr-codes'), v),
    true
  )

export const useDeleteQrCode = (t: number) =>
  useBranchMutation(t, (id: number) =>
    api.delete(tenantUrl(t, `qr-codes/${id}`))
  )
