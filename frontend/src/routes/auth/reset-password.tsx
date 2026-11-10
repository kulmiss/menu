import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { ResetPasswordPage } from '@/features/auth/password-reset'

// Opened from the reset email: /auth/reset-password?token=…&email=…
export const Route = createFileRoute('/auth/reset-password')({
  validateSearch: z.object({
    token: z.string().optional(),
    email: z.string().optional(),
  }),
  component: function ResetPasswordRoute() {
    const { token, email } = Route.useSearch()
    return <ResetPasswordPage token={token} email={email} />
  },
})
