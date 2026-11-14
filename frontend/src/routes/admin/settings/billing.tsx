import { createFileRoute } from '@tanstack/react-router'
import { BillingSettings } from '@/features/settings/billing'

export const Route = createFileRoute('/admin/settings/billing')({
  component: BillingSettings,
})
