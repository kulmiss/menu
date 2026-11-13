import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Media, type Translations } from '@/features/menu/types'

export type Promotion = {
  id: number
  type: 'special' | 'announcement'
  title: Translations
  body: Translations
  media: Media | null
  item_id: number | null
  branch_id: number | null
  /** Restaurant local time, "YYYY-MM-DD HH:MM". */
  starts_at: string | null
  ends_at: string | null
  is_active: boolean
  sort: number
}

export type PromotionInput = Partial<
  Omit<Promotion, 'id' | 'media' | 'sort'>
> & {
  media_id?: number | null
}

const key = (t: number) => ['tenant', t, 'promotions'] as const

export const promotionsQuery = (t: number) =>
  queryOptions({
    queryKey: key(t),
    queryFn: async () =>
      (await api.get<{ data: Promotion[] }>(tenantUrl(t, 'promotions'))).data
        .data,
  })

function usePromoMutation<V>(
  t: number,
  fn: (v: V) => Promise<unknown>,
  form = false
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    meta: { inlineErrors: form },
    onSettled: () => qc.invalidateQueries({ queryKey: key(t) }),
  })
}

export const useSavePromotion = (t: number) =>
  usePromoMutation(
    t,
    ({ id, ...v }: PromotionInput & { id?: number }) =>
      id
        ? api.patch(tenantUrl(t, `promotions/${id}`), v)
        : api.post(tenantUrl(t, 'promotions'), v),
    true
  )

export const useDeletePromotion = (t: number) =>
  usePromoMutation(t, (id: number) =>
    api.delete(tenantUrl(t, `promotions/${id}`))
  )

export const useReorderPromotions = (t: number) =>
  usePromoMutation(t, (ids: number[]) =>
    api.post(tenantUrl(t, 'promotions/reorder'), { ids })
  )

/** "YYYY-MM-DD HH:MM" ⇄ the value of <input type="datetime-local">. */
export const toInput = (v: string | null) => (v ? v.replace(' ', 'T') : '')
export const fromInput = (v: string) =>
  v ? v.replace('T', ' ').slice(0, 16) : null
