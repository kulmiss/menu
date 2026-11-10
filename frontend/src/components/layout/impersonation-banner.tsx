import { useState } from 'react'
import { LogOut } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'

type Props = { userName: string; endsAt: string }

/** Red bar while a Super Admin is "logged in as" a restaurant user (PLAN.md §5). */
export function ImpersonationBanner({ userName, endsAt }: Props) {
  const { t } = useTranslation()
  const [busy, setBusy] = useState(false)
  const until = new Date(endsAt).toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  })

  async function back() {
    setBusy(true)
    try {
      await api.post('/impersonation/stop')
    } finally {
      // A full reload clears every cached screen of the restaurant.
      window.location.assign('/platform')
    }
  }

  return (
    <div
      role='status'
      className='flex flex-wrap items-center justify-between gap-2 bg-red-700 px-4 py-2 text-sm text-white'
    >
      <span>{t('impersonation.banner', { name: userName, time: until })}</span>
      <Button size='sm' variant='secondary' disabled={busy} onClick={back}>
        <LogOut /> {t('impersonation.back')}
      </Button>
    </div>
  )
}
