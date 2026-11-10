import { createFileRoute } from '@tanstack/react-router'
import { loadScreenTexts } from '@/i18n'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'
import { requireMe } from '@/features/auth/guard'

export const Route = createFileRoute('/admin')({
  // Every restaurant page needs the signed-in user (and their restaurants)
  // and the screen texts; both load at the same time.
  beforeLoad: async ({ context, location }) => {
    const [me] = await Promise.all([
      requireMe(context.queryClient, location.href),
      loadScreenTexts(),
    ])
    return me
  },
  component: AuthenticatedLayout,
})
