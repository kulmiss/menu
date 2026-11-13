import { lazy, Suspense, useEffect, useSyncExternalStore } from 'react'
import {
  ensureToaster,
  subscribeToaster,
  toasterMounted,
  toasterRequested,
} from '@/lib/toast'

const ToasterMount = lazy(async () => {
  const { Toaster } = await import('@/components/ui/sonner')
  return {
    default: function Mount() {
      // Runs after the toaster's own effects, so it is listening by now.
      useEffect(() => toasterMounted(), [])
      return <Toaster duration={5000} />
    },
  }
})

/** Mounts the toaster on the first message, or when the page is idle. */
export function LazyToaster() {
  const requested = useSyncExternalStore(subscribeToaster, toasterRequested)

  useEffect(() => {
    const idle =
      window.requestIdleCallback ??
      ((cb: () => void) => window.setTimeout(cb, 2000))
    idle(() => void ensureToaster())
  }, [])

  if (!requested) return null
  return (
    <Suspense fallback={null}>
      <ToasterMount />
    </Suspense>
  )
}
