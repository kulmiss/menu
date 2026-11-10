import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { ME_KEY, type Role } from '@/features/auth/api'

export type Member = {
  id: number
  role: Role
  can_edit_prices: boolean
  user: { id: number; name: string; email: string }
  joined_at: string | null
}

export type Invitation = {
  id: number
  email: string
  role: Role
  can_edit_prices: boolean
  expires_at: string
}

export const teamKeys = {
  members: (tenantId: number) => ['tenant', tenantId, 'members'] as const,
  invitations: (tenantId: number) =>
    ['tenant', tenantId, 'invitations'] as const,
}

export const membersQuery = (tenantId: number) =>
  queryOptions({
    queryKey: teamKeys.members(tenantId),
    queryFn: async () =>
      (await api.get<{ data: Member[] }>(tenantUrl(tenantId, 'members'))).data
        .data,
  })

export const invitationsQuery = (tenantId: number) =>
  queryOptions({
    queryKey: teamKeys.invitations(tenantId),
    queryFn: async () =>
      (
        await api.get<{ data: Invitation[] }>(
          tenantUrl(tenantId, 'invitations')
        )
      ).data.data,
  })

export type MemberChange = { role?: Role; can_edit_prices?: boolean }

export function useUpdateMember(tenantId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...change }: MemberChange & { id: number }) =>
      (
        await api.patch<{ data: Member }>(
          tenantUrl(tenantId, `members/${id}`),
          change
        )
      ).data.data,
    onSuccess: (member) =>
      qc.setQueryData(teamKeys.members(tenantId), (list?: Member[]) =>
        list?.map((m) => (m.id === member.id ? member : m))
      ),
  })
}

export function useRemoveMember(tenantId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id }: { id: number; self: boolean }) => {
      await api.delete(tenantUrl(tenantId, `members/${id}`))
    },
    onSuccess: (_data, { id, self }) => {
      if (self) return qc.invalidateQueries({ queryKey: ME_KEY })
      qc.setQueryData(teamKeys.members(tenantId), (list?: Member[]) =>
        list?.filter((m) => m.id !== id)
      )
    },
  })
}

export type InviteInput = {
  email: string
  role: Role
  can_edit_prices: boolean
}

export function useInvite(tenantId: number, onError: (error: unknown) => void) {
  const qc = useQueryClient()
  return useMutation({
    // The dialog shows field errors itself (replaces the default toast).
    onError,
    mutationFn: async (input: InviteInput) =>
      (
        await api.post<{ data: Invitation }>(
          tenantUrl(tenantId, 'invitations'),
          input
        )
      ).data.data,
    // A new invitation replaces an open one for the same address.
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: teamKeys.invitations(tenantId) }),
  })
}

export function useRevokeInvitation(tenantId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(tenantUrl(tenantId, `invitations/${id}`))
    },
    onSuccess: (_d, id) =>
      qc.setQueryData(teamKeys.invitations(tenantId), (list?: Invitation[]) =>
        list?.filter((i) => i.id !== id)
      ),
  })
}
