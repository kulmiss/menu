import { createFileRoute } from '@tanstack/react-router'
import { PrintMaterialsPage } from '@/features/qr/print-materials'

export const Route = createFileRoute('/admin/print-materials')({
  validateSearch: (search: Record<string, unknown>): { codes?: string } => ({
    codes: typeof search.codes === 'string' ? search.codes : undefined,
  }),
  component: PrintMaterialsPage,
})
