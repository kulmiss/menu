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

/** [label, href]: a link button inside the toast ("Upgrade on WhatsApp"). */
type Link = [string, string]

function show(kind: Kind, text: string, link?: Link) {
  void Promise.all([import('sonner'), ensureToaster()]).then(([{ toast }]) => {
    toast[kind](
      text,
      link && {
        duration: 12e3,
        action: { label: link[0], onClick: () => open(link[1], '_blank') },
      }
    )
  })
}

export const toast = {
  success: (text: string) => show('success', text),
  error: (text: string, link?: Link) => show('error', text, link),
  message: (text: string) => show('message', text),
}
