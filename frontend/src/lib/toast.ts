/**
 * Toast messages without putting the toast library in the first download
 * (PLAN.md §15.6). The first message mounts the toaster, then shows; the
 * library is also preloaded once the page is idle, so it is usually ready.
 */
type Kind = 'success' | 'error' | 'message'

const listeners = new Set<() => void>()
let requested = false
let mounted: Promise<void> | null = null
let markMounted: () => void = () => {}

/** For <LazyToaster />: has anyone asked for the toaster yet? */
export function subscribeToaster(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}
export const toasterRequested = () => requested

/** Called by <LazyToaster /> once sonner's toaster is on the page. */
export function toasterMounted() {
  markMounted()
}

export function ensureToaster(): Promise<void> {
  if (!mounted) {
    mounted = new Promise((resolve) => (markMounted = resolve))
    requested = true
    listeners.forEach((l) => l())
  }
  return mounted
}

function show(kind: Kind, text: string) {
  void Promise.all([import('sonner'), ensureToaster()]).then(([{ toast }]) => {
    toast[kind](text)
  })
}

export const toast = {
  success: (text: string) => show('success', text),
  error: (text: string) => show('error', text),
  message: (text: string) => show('message', text),
}
