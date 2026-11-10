import { lazy, Suspense } from 'react'

const SignOutDialog = lazy(() =>
  import('@/components/sign-out-dialog').then((m) => ({
    default: m.SignOutDialog,
  }))
)

/** The sign-out confirmation, downloaded only when it opens (PLAN.md §15.3). */
export function LazySignOutDialog({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  if (!open) return null
  return (
    <Suspense fallback={null}>
      <SignOutDialog open onOpenChange={onOpenChange} />
    </Suspense>
  )
}
