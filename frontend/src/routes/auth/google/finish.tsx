import { createFileRoute } from '@tanstack/react-router'
import { GoogleFinishPage } from '@/features/auth/google-finish'

// After "Continue with Google" with a new e-mail: the restaurant name and the Terms.
export const Route = createFileRoute('/auth/google/finish')({
  component: GoogleFinishPage,
})
