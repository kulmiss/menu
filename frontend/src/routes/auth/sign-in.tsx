import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { SignInPage } from '@/features/auth/sign-in'

export const Route = createFileRoute('/auth/sign-in')({
  validateSearch: (search: Record<string, unknown>): { redirect?: string } => ({
    redirect: optionalString(search.redirect),
  }),
  component: function SignInRoute() {
    const { redirect } = Route.useSearch()
    return <SignInPage redirect={redirect} />
  },
})
