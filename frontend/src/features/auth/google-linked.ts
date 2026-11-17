import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from '@/lib/toast'

/**
 * After "Continue with Google" linked an existing account, the server sends
 * the browser to /admin?google=linked: say so once, then tidy the address.
 */
export function useGoogleLinkedNotice() {
  const { t } = useTranslation()
  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    if (params.get('google') !== 'linked') return
    toast.success(t('auth.google.linked'))
    params.delete('google')
    const query = params.toString()
    window.history.replaceState(
      window.history.state,
      '',
      window.location.pathname + (query ? `?${query}` : '')
    )
  }, [t])
}
