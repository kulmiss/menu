import { createFileRoute } from '@tanstack/react-router'
import { DishesPage } from '@/features/menu/dishes'

export const Route = createFileRoute('/admin/dishes')({
  component: DishesPage,
})
