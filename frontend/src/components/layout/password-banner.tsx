import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { KeyRound, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useMe } from '@/features/auth/use-me'

const KEY = 'password-banner-hidden'

function hiddenThisSession(): boolean {
  try {
    return sessionStorage.getItem(KEY) === '1'
  } catch {
    return false
  }
}

/**
 * The password is on the common/breached list (SECURITY_REPORT.md M3). A
 * gentle nudge, never a forced reset (owner, 2026-11-14); hidden for the rest
 * of the browser session when closed. Not shown during "log in as".
 */
export function PasswordBanner({ platform = false }: { platform?: boolean }) {
  const { t } = useTranslation()
  const me = useMe()
  const [hidden, setHidden] = useState(hiddenThisSession)
  if (!me.password_weak || me.impersonation || hidden) return null

  return (
    <div
      role='status'
      className='flex items-start gap-2 border-b bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-50'
    >
      <KeyRound className='mt-0.5 size-4 shrink-0' aria-hidden />
      <span className='flex-1'>
        {t('settings.password_weak_banner')}{' '}
        {platform ? (
          <Link
            to='/platform/$section'
            params={{ section: 'security' }}
            className='font-medium underline'
          >
            {t('settings.password_weak_action')}
          </Link>
        ) : (
          <Link to='/admin/settings/security' className='font-medium underline'>
            {t('settings.password_weak_action')}
          </Link>
        )}
      </span>
      <button
        type='button'
        className='-m-1 rounded p-1 hover:bg-amber-100 dark:hover:bg-amber-900'
        aria-label={t('common.close')}
        onClick={() => {
          try {
            sessionStorage.setItem(KEY, '1')
          } catch {
            /* private mode: hidden until reload */
          }
          setHidden(true)
        }}
      >
        <X className='size-4' aria-hidden />
      </button>
    </div>
  )
}
