import { createFileRoute } from '@tanstack/react-router'
import { InsightsPage } from '@/features/insights'

export const Route = createFileRoute('/admin/insights')({
  component: InsightsPage,
})
