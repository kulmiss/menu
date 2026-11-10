import { createFileRoute, redirect } from '@tanstack/react-router'

// Laravel serves the SPA only under /admin, /platform and /auth; this covers
// the Vite dev server's root.
export const Route = createFileRoute('/')({
  beforeLoad: () => {
    throw redirect({ to: '/admin' })
  },
})
