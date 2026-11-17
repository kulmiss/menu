import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { SignInPage } from '@/features/auth/sign-in'

export const Route = createFileRoute('/auth/sign-in')({
  validateSearch: (
    search: Record<string, unknown>
  ): { redirect?: string; verify?: string; google?: string } => ({
    redirect: optionalString(search.redirect),
    // Set by the e-mail confirmation link (VerifyEmailController).
    verify: optionalString(search.verify),
    // Why "Continue with Google" didn't sign in (GoogleController).
    google: optionalString(search.google),
  }),
  component: function SignInRoute() {
    const { redirect, verify, google } = Route.useSearch()
    return <SignInPage redirect={redirect} verify={verify} google={google} />
  },
})
