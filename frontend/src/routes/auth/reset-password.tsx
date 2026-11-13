import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { ResetPasswordPage } from '@/features/auth/password-reset'

// Opened from the reset email: /auth/reset-password?token=…&email=…[&welcome=1]
export const Route = createFileRoute('/auth/reset-password')({
  validateSearch: (
    search: Record<string, unknown>
  ): { token?: string; email?: string; welcome?: string } => ({
    token: optionalString(search.token),
    email: optionalString(search.email),
    // Set by the new-owner welcome e-mail (its link works for 7 days).
    welcome: optionalString(
      search.welcome == null ? '' : String(search.welcome)
    ),
  }),
  component: function ResetPasswordRoute() {
    const { token, email, welcome } = Route.useSearch()
    return (
      <ResetPasswordPage
        token={token}
        email={email}
        welcome={welcome === '1'}
      />
    )
  },
})
