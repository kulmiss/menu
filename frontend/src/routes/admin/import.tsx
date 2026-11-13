import { createFileRoute } from '@tanstack/react-router'
import { ImportExportPage } from '@/features/menu/import'

export const Route = createFileRoute('/admin/import')({
  component: ImportExportPage,
})
