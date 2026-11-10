import { createFileRoute } from '@tanstack/react-router'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'
import { requireMe } from '@/features/auth/guard'

export const Route = createFileRoute('/admin')({
  // Every restaurant page needs the signed-in user (and their restaurants).
  beforeLoad: ({ context, location }) =>
    requireMe(context.queryClient, location.href),
  component: AuthenticatedLayout,
})
