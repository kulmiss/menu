import { createFileRoute } from '@tanstack/react-router'
import { TagsPage } from '@/features/menu/tags'

export const Route = createFileRoute('/admin/tags')({
  component: TagsPage,
})
