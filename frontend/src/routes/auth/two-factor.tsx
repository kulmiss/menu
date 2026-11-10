import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { TwoFactorPage } from '@/features/auth/two-factor'

export const Route = createFileRoute('/auth/two-factor')({
  validateSearch: z.object({ redirect: z.string().optional() }),
  component: function TwoFactorRoute() {
    const { redirect } = Route.useSearch()
    return <TwoFactorPage redirect={redirect} />
  },
})
