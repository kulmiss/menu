import { createFileRoute } from '@tanstack/react-router'
import { PlatformDashboard } from '@/features/platform/dashboard'

export const Route = createFileRoute('/platform/')({
  component: PlatformDashboard,
})
