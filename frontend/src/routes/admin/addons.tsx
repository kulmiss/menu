import { createFileRoute } from '@tanstack/react-router'
import { AddonGroupsPage } from '@/features/menu/addons'

export const Route = createFileRoute('/admin/addons')({
  component: AddonGroupsPage,
})
