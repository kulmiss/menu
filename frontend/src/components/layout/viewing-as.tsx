import { useState } from 'react'
import { Eye } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { type Me } from '@/features/auth/api'

type Props = { info: NonNullable<Me['impersonation']> }

/**
 * Quiet header badge while a Super Admin is "logged in as" a restaurant
 * (owner, 2026-11-11: replaces the red banner). It only exists in the Super
 * Admin's own session, so the restaurant never sees it. Since 2026-11-13
 * there is no time limit, no blocked action and nothing is recorded.
 */
export function ViewingAs({ info }: Props) {
  const { t } = useTranslation()
  const [busy, setBusy] = useState(false)

  async function back() {
    setBusy(true)
    // The server answers with the Super Admin area's address; /admin sends a
    // Super Admin there too. A full reload clears every cached screen.
    let to = '/admin'
    try {
      const res = await api.post<{ data: { redirect: string } }>(
        '/impersonation/stop'
      )
      to = res.data.data.redirect
    } finally {
      window.location.assign(to)
    }
  }

  return (
    <div
      role='status'
      className='flex min-w-0 items-center gap-1 rounded-full border ps-3 text-sm text-muted-foreground'
    >
      <Eye className='size-4 shrink-0' aria-hidden />
      <span className='hidden truncate sm:inline'>
        {t('impersonation.viewing_as', { name: info.tenant_name })}
      </span>
      <span className='sr-only sm:hidden'>
        {t('impersonation.viewing_as', { name: info.tenant_name })}
      </span>
      <Button
        variant='ghost'
        size='sm'
        className='min-h-11 rounded-full sm:min-h-9'
        disabled={busy}
        onClick={back}
      >
        {t('impersonation.back')}
      </Button>
    </div>
  )
}
