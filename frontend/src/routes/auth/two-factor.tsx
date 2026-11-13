import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { TwoFactorPage } from '@/features/auth/two-factor'

export const Route = createFileRoute('/auth/two-factor')({
  validateSearch: (search: Record<string, unknown>): { redirect?: string } => ({
    redirect: optionalString(search.redirect),
  }),
  component: function TwoFactorRoute() {
    const { redirect } = Route.useSearch()
    return <TwoFactorPage redirect={redirect} />
  },
})
