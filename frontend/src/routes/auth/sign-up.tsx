import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { SignUpPage } from '@/features/auth/sign-up'

export const Route = createFileRoute('/auth/sign-up')({
  validateSearch: z.object({
    redirect: z.string().optional(),
    invite: z.string().optional(),
  }),
  component: function SignUpRoute() {
    const { redirect, invite } = Route.useSearch()
    return <SignUpPage redirect={redirect} invite={invite} />
  },
})
