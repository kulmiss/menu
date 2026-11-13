import { createFileRoute } from '@tanstack/react-router'
import { InfoPage } from '@/features/info'

export const Route = createFileRoute('/admin/info')({
  component: InfoPage,
})
