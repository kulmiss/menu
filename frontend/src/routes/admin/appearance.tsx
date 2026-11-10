import { createFileRoute } from '@tanstack/react-router'
import { AppearancePage } from '@/features/appearance'

export const Route = createFileRoute('/admin/appearance')({
  component: AppearancePage,
})
