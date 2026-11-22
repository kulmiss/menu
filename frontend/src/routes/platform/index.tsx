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
  ): {
    days?: number
    from?: string
    to?: string
    plan?: number
    demo?: boolean
    compare?: boolean
  } => {
    const days = Number(search.days)
    const flag = (v: unknown) => v === true || v === 1 || v === '1'
    const off = (v: unknown) => v === false || v === 0 || v === '0'
    return {
      days: [7, 30, 90, 365].includes(days) ? days : undefined,
      from: date(search.from),
      to: date(search.to),
      plan: optionalId(search.plan),
      // "Include demo data" (off unless asked) and "Compare" (on unless switched off), 2026-11-22.
      demo: flag(search.demo) ? true : undefined,
      compare: off(search.compare) ? false : undefined,
    }
  },
  component: PlatformDashboard,
})
