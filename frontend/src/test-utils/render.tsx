import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from 'vitest-browser-react'
import { DirectionProvider } from '@/context/direction-provider'
import { ThemeProvider } from '@/context/theme-provider'
import { ME_KEY, type Me } from '@/features/auth/api'

export function testQueryClient(me?: Me) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  if (me) client.setQueryData(ME_KEY, me)
  return client
}

/**
 * Renders with the app's providers and a fresh React Query client
 * (optionally signed in as `me`).
 */
export async function renderWithQuery(
  ui: React.ReactNode,
  { me, client = testQueryClient(me) }: { me?: Me; client?: QueryClient } = {}
) {
  const screen = await render(
    <QueryClientProvider client={client}>
      <ThemeProvider>
        <DirectionProvider>{ui}</DirectionProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
  return { ...screen, client }
}
