import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { SignUpPage } from '@/features/auth/sign-up'

export const Route = createFileRoute('/auth/sign-up')({
  validateSearch: (
    search: Record<string, unknown>
  ): { redirect?: string; invite?: string } => ({
    redirect: optionalString(search.redirect),
    invite: optionalString(search.invite),
  }),
  component: function SignUpRoute() {
    const { redirect, invite } = Route.useSearch()
    return <SignUpPage redirect={redirect} invite={invite} />
  },
})
