import { createFileRoute, notFound } from '@tanstack/react-router'
import { optionalId, optionalString } from '@/lib/search-params'
import { PlatformSection } from '@/features/platform/sections'

const SECTIONS = [
  'restaurants',
  'plans',
  'payments',
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
  ): { state?: string; open?: number } => ({
    state: optionalString(search.state),
    open: optionalId(search.open),
  }),
  beforeLoad: ({ params }) => {
    if (!SECTIONS.includes(params.section)) throw notFound()
  },
  component: function PlatformSectionRoute() {
    const { section } = Route.useParams()
    return <PlatformSection section={section} />
  },
})
