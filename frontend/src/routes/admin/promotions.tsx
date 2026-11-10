import { createFileRoute } from '@tanstack/react-router'
import { PromotionsPage } from '@/features/promotions'

export const Route = createFileRoute('/admin/promotions')({
  component: PromotionsPage,
})
