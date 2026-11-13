import { createFileRoute } from '@tanstack/react-router'
import { PrintMaterialsPage } from '@/features/qr/print-materials'

export const Route = createFileRoute('/admin/print-materials')({
  component: PrintMaterialsPage,
})
