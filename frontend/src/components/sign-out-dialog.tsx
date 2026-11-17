import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { signInUrl } from '@/lib/area'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { signOut } from '@/features/auth/api'

interface SignOutDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function SignOutDialog({ open, onOpenChange }: SignOutDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const handleSignOut = async () => {
    try {
      await signOut(queryClient)
    } catch {
      // Signed out on this device even if the request failed (offline).
    }
    window.location.assign(signInUrl())
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      title={t('auth.sign_out')}
      desc={t('auth.sign_out_confirm')}
      confirmText={t('auth.sign_out')}
      destructive
      handleConfirm={() => void handleSignOut()}
      className='sm:max-w-sm'
    />
  )
}
