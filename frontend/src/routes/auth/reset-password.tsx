import { createFileRoute } from '@tanstack/react-router'
import { optionalString } from '@/lib/search-params'
import { ResetPasswordPage } from '@/features/auth/password-reset'

// Opened from the reset email: /auth/reset-password?token=…&email=…
export const Route = createFileRoute('/auth/reset-password')({
  validateSearch: (
    search: Record<string, unknown>
  ): { token?: string; email?: string } => ({
    token: optionalString(search.token),
    email: optionalString(search.email),
  }),
  component: function ResetPasswordRoute() {
    const { token, email } = Route.useSearch()
    return <ResetPasswordPage token={token} email={email} />
  },
})
