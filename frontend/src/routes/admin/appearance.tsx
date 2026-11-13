import { createFileRoute } from '@tanstack/react-router'
import { loadPageTexts } from '@/i18n'
import { AppearancePage } from '@/features/appearance'

export const Route = createFileRoute('/admin/appearance')({
  loader: () => loadPageTexts('appearance'),
  component: AppearancePage,
})
