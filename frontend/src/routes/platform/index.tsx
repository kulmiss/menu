import { createFileRoute } from '@tanstack/react-router'
import { optionalId, optionalString } from '@/lib/search-params'
import { PlatformDashboard } from '@/features/platform/dashboard'

const DATE = /^\d{4}-\d{2}-\d{2}$/
const date = (v: unknown) => {
  const s = optionalString(v)
  return s && DATE.test(s) ? s : undefined
}

// Dashboard filters live in the address so a view can be shared (QUESTIONS #186).
export const Route = createFileRoute('/platform/')({
  validateSearch: (
    search: Record<string, unknown>
  ): { days?: number; from?: string; to?: string; plan?: number } => {
    const days = Number(search.days)
    return {
      days: [7, 30, 90, 365].includes(days) ? days : undefined,
      from: date(search.from),
      to: date(search.to),
      plan: optionalId(search.plan),
    }
  },
  component: PlatformDashboard,
})
