import { createFileRoute, notFound } from '@tanstack/react-router'
import { optionalId, optionalString } from '@/lib/search-params'
import { PlatformSection } from '@/features/platform/sections'

const text = (v: unknown) =>
  typeof v === 'number' ? String(v) : optionalString(v)

const SECTIONS = [
  'restaurants',
  'plans',
  'payments',
  'invoices',
  'messages',
  'backups',
  'themes',
  'announcements',
  'activity',
  'settings',
  'security',
]

// One route for every platform screen (the admin's first download carries
// one route definition instead of eight); screens load on demand.
export const Route = createFileRoute('/platform/$section')({
  validateSearch: (
    search: Record<string, unknown>
  ): {
    state?: string
    open?: number
    tag?: number
    restaurants?: string
    health?: string
    usage?: string
    plan?: string
    from?: string
    to?: string
    sort?: string
  } => ({
    state: optionalString(search.state),
    open: optionalId(search.open),
    tag: optionalId(search.tag),
    // Messages: e-mail the restaurants picked in the restaurants table.
    restaurants: optionalString(search.restaurants),
    // Dashboard click-throughs to the tables (QUESTIONS #186); the tables read them.
    health: text(search.health),
    usage: text(search.usage),
    plan: text(search.plan),
    from: text(search.from),
    to: text(search.to),
    sort: text(search.sort),
  }),
  beforeLoad: ({ params }) => {
    if (!SECTIONS.includes(params.section)) throw notFound()
  },
  component: function PlatformSectionRoute() {
    const { section } = Route.useParams()
    return <PlatformSection section={section} />
  },
})
