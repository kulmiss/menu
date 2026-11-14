import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { SignInPage } from '@/features/auth/sign-in'

export const Route = createFileRoute('/auth/sign-in')({
  validateSearch: (
    search: Record<string, unknown>
  ): { redirect?: string; verify?: string } => ({
    redirect: optionalString(search.redirect),
    // Set by the e-mail confirmation link (VerifyEmailController).
    verify: optionalString(search.verify),
  }),
  component: function SignInRoute() {
    const { redirect, verify } = Route.useSearch()
    return <SignInPage redirect={redirect} verify={verify} />
  },
})
