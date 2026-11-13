import { createFileRoute } from '@tanstack/react-router'
import { BranchesPage } from '@/features/branches'

export const Route = createFileRoute('/admin/branches')({
  component: BranchesPage,
})
