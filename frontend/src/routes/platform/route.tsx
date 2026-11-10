import { createFileRoute, redirect } from '@tanstack/react-router'
import { loadPlatformTexts, loadScreenTexts } from '@/i18n'
import { requireMe } from '@/features/auth/guard'
import { PlatformLayout } from '@/features/platform/layout'

export const Route = createFileRoute('/platform')({
  // Super Admins only; restaurant users never download this area (PLAN.md §3.2).
  beforeLoad: async ({ context, location }) => {
    const [me] = await Promise.all([
      requireMe(context.queryClient, location.href),
      loadScreenTexts(),
      loadPlatformTexts(),
    ])
    if (!me.is_super_admin || me.impersonation) throw redirect({ to: '/admin' })
    return me
  },
  component: PlatformLayout,
})
