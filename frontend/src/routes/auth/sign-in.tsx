import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { SignInPage } from '@/features/auth/sign-in'

export const Route = createFileRoute('/auth/sign-in')({
  validateSearch: z.object({ redirect: z.string().optional() }),
  component: function SignInRoute() {
    const { redirect } = Route.useSearch()
    return <SignInPage redirect={redirect} />
  },
})
