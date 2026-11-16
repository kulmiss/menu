import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { RestaurantHelpPage } from '@/features/help/restaurant-help'

export const Route = createFileRoute('/admin/help')({
  validateSearch: (search: Record<string, unknown>): { a?: string } => ({
    a: optionalString(search.a),
  }),
  component: () => <RestaurantHelpPage />,
})
