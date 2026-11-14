import { createFileRoute } from '@tanstack/react-router'
import { SecuritySettings } from '@/features/settings/security'

export const Route = createFileRoute('/admin/settings/security')({
  // ?setup=1 (dashboard card, reminder e-mail): start turning on two-factor at once.
  validateSearch: (search: Record<string, unknown>): { setup?: number } =>
    search.setup === 1 || search.setup === '1' ? { setup: 1 } : {},
  component: function SecurityRoute() {
    const { setup } = Route.useSearch()
    return <SecuritySettings startSetup={setup === 1} />
  },
})
