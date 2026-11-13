import { StrictMode } from 'react'
import ReactDOM from 'react-dom/client'
import {
  MutationCache,
  QueryCache,
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query'
import { RouterProvider, createRouter } from '@tanstack/react-router'
import { errorStatus, validationErrors } from '@/lib/api'
import { handleServerError } from '@/lib/handle-server-error'
import { toast } from '@/lib/toast'
import { DirectionProvider } from './context/direction-provider'
import { FontProvider } from './context/font-provider'
import { ThemeProvider } from './context/theme-provider'
// i18n (must load before the first render)
import i18n, { i18nReady } from './i18n'
// Generated Routes
import { routeTree } from './routeTree.gen'
// Styles
import './styles/index.css'

declare module '@tanstack/react-query' {
  interface Register {
    queryMeta: {
      /** false: a 401 is expected (signed-out page) — don't redirect. */
      authRedirect?: boolean
    }
    mutationMeta: {
      /** true: the form shows 422 field errors itself — no toast for those. */
      inlineErrors?: boolean
    }
  }
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failureCount, error) => {
        if (import.meta.env.DEV) return false
        if (failureCount > 2) return false
        // Retry network trouble and server errors, not answers like 401/403/404.
        const status = errorStatus(error)
        return status === 0 || status >= 500
      },
      refetchOnWindowFocus: import.meta.env.PROD,
      staleTime: 10 * 1000, // 10s
    },
  },
  // One place for failed saves: sign-in again on 401, else one toast —
  // except validation errors a form already shows under its fields.
  mutationCache: new MutationCache({
    onError: (error, _variables, _result, mutation) => {
      if (errorStatus(error) === 401) return sessionExpired()
      if (mutation.meta?.inlineErrors && validationErrors(error)) return
      handleServerError(error)
    },
  }),
  queryCache: new QueryCache({
    onError: (error, query) => {
      if (errorStatus(error) === 401) {
        if (query.meta?.authRedirect !== false) sessionExpired()
        return
      }
      if (errorStatus(error) >= 500) toast.error(i18n.t('errors.generic'))
    },
  }),
})

/** Signed out on the server (expired session, signed out elsewhere). */
function sessionExpired() {
  const { pathname } = router.history.location
  if (pathname.startsWith('/auth/')) return
  queryClient.clear()
  toast.error(i18n.t('auth.session_expired'))
  void router.navigate({
    to: '/auth/sign-in',
    search: { redirect: router.history.location.href },
  })
}

// Create a new router instance
const router = createRouter({
  routeTree,
  context: { queryClient },
  defaultPreload: 'intent',
  defaultPreloadStaleTime: 0,
})

// Register the router instance for type safety
declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}

// Render the app
const rootElement = document.getElementById('root')!
// A remembered non-English language is loaded first, so no English flash.
void i18nReady.then(render)

function render() {
  if (rootElement.innerHTML) return
  const root = ReactDOM.createRoot(rootElement)
  root.render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <FontProvider>
            <DirectionProvider>
              <RouterProvider router={router} />
            </DirectionProvider>
          </FontProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </StrictMode>
  )
}
