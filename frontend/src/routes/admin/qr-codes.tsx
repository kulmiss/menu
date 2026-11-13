import { createFileRoute } from '@tanstack/react-router'
import { QrCodesPage } from '@/features/qr'

export const Route = createFileRoute('/admin/qr-codes')({
  component: QrCodesPage,
})
