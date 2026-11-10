import { createFileRoute } from '@tanstack/react-router'
import { SecuritySettings } from '@/features/settings/security'

export const Route = createFileRoute('/admin/settings/security')({
  component: SecuritySettings,
})
