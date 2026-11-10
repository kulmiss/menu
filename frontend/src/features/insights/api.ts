import { queryOptions } from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'
import { type Translations } from '@/features/menu/types'

export type Insights = {
  days: { date: string; views: number; scans: number }[]
  totals: { views: number; scans: number; item_views: number }
  top_items: { id: number; name: string | null; count: number }[]
  scans: {
    qr_id: number
    code: string | null
    label: string | null
    deleted: boolean
    count: number
  }[]
}

export type DashboardData = {
  today: { views: number; scans: number }
  yesterday: { views: number; scans: number }
  sold_out: { id: number; name: Translations }[]
}

export const insightsQuery = (t: number, days: number, branch: number | null) =>
  queryOptions({
    queryKey: ['tenant', t, 'insights', days, branch] as const,
    queryFn: async () => {
      const q = new URLSearchParams({ days: String(days) })
      if (branch) q.set('branch_id', String(branch))
      return (await api.get<{ data: Insights }>(tenantUrl(t, `insights?${q}`)))
        .data.data
    },
    staleTime: 60_000,
  })

export const dashboardQuery = (t: number) =>
  queryOptions({
    queryKey: ['tenant', t, 'dashboard'] as const,
    queryFn: async () =>
      (await api.get<{ data: DashboardData }>(tenantUrl(t, 'dashboard'))).data
        .data,
    staleTime: 30_000,
  })
