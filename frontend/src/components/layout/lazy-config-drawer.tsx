import { lazy, Suspense, useState } from 'react'
import { Settings } from 'lucide-react'
import { Button } from '@/components/ui/button'

const load = () => import('@/components/config-drawer')
const ConfigDrawer = lazy(() =>
  load().then((m) => ({ default: m.ConfigDrawer }))
)

/**
 * The appearance drawer is downloaded when someone points at, focuses or
 * taps its button — not on every admin page load (PLAN.md §15.3). Pointing
 * only starts the download; nothing re-renders until the click, so the
 * click is never lost. The stand-in button looks the same.
 */
export function LazyConfigDrawer() {
  const [open, setOpen] = useState(false)
  const button = (
    <Button
      size='icon'
      variant='ghost'
      aria-label='Open theme settings'
      className='rounded-full'
      onPointerEnter={() => void load()}
      onFocus={() => void load()}
      onClick={() => setOpen(true)}
    >
      <Settings aria-hidden='true' />
    </Button>
  )

  if (!open) return button
  return (
    <Suspense fallback={button}>
      <ConfigDrawer defaultOpen />
    </Suspense>
  )
}
