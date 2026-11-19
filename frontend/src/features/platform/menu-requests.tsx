import { useQuery } from '@tanstack/react-query'
import { ExternalLink, FileText, Loader2, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { superPath } from '@/lib/area'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { q, send, usePlatformMutation } from './api'
import { PageTitle } from './layout'

type Status = 'new' | 'working' | 'done'
type MenuRequestRow = {
  id: number
  status: Status
  note: string | null
  created_at: string
  restaurant: { id: number; name: string; slug: string } | null
  user: { name: string; email: string } | null
  files: { n: number; name: string; mime: string; bytes: number }[]
}

const NEXT: Record<Status, Status | null> = {
  new: 'working',
  working: 'done',
  done: null,
}

/** The private photo or PDF, through the signed-in API (never a public address). */
const fileUrl = (id: number, n: number) =>
  `/api/v1/super/menu-requests/${id}/files/${n}`

/** Super Admin → Menu requests (QUESTIONS #235): "Add my menu for me" from restaurants. */
export function MenuRequestsPage() {
  const { t, i18n } = useTranslation()
  const data = useQuery(
    q<{ open: number; requests: MenuRequestRow[] }>('menu-requests')
  )
  const update = usePlatformMutation(
    ({ id, status }: { id: number; status: Status }) =>
      send('put', `menu-requests/${id}`, { status }),
    false
  )
  const remove = usePlatformMutation(
    (id: number) => send('delete', `menu-requests/${id}`),
    false
  )
  const when = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))
  const rows = data.data?.requests

  return (
    <>
      <PageTitle
        title={t('platform.nav.menu-requests')}
        hint={t('platform.menu_requests.hint')}
      />
      {!rows ? (
        <Skeleton className='h-40 w-full' />
      ) : rows.length === 0 ? (
        <p className='text-muted-foreground'>
          {t('platform.menu_requests.none')}
        </p>
      ) : (
        <ul className='grid gap-3'>
          {rows.map((r) => {
            const next = NEXT[r.status]
            return (
              <li key={r.id}>
                <Card>
                  <CardContent className='grid gap-3 pt-6'>
                    <div className='flex flex-wrap items-start justify-between gap-2'>
                      <div className='min-w-0'>
                        <p className='font-semibold'>
                          {r.restaurant?.name ?? '—'}{' '}
                          <Badge
                            variant={
                              r.status === 'done' ? 'secondary' : 'default'
                            }
                          >
                            {t(`platform.menu_requests.status.${r.status}`)}
                          </Badge>
                        </p>
                        <p className='text-sm text-muted-foreground'>
                          {r.user ? `${r.user.name} · ${r.user.email} · ` : ''}
                          {when(r.created_at)}
                        </p>
                      </div>
                      <div className='flex flex-wrap gap-2'>
                        {r.restaurant && (
                          <Button
                            asChild
                            variant='outline'
                            className='min-h-11 sm:min-h-9'
                          >
                            <a
                              href={`${superPath() ?? '/super'}/restaurants?open=${r.restaurant.id}`}
                            >
                              <ExternalLink aria-hidden />
                              {t('platform.menu_requests.open_restaurant')}
                            </a>
                          </Button>
                        )}
                        {next && (
                          <Button
                            className='min-h-11 sm:min-h-9'
                            disabled={update.isPending}
                            onClick={() =>
                              update.mutate({ id: r.id, status: next })
                            }
                          >
                            {update.isPending && (
                              <Loader2 className='animate-spin' />
                            )}
                            {t(`platform.menu_requests.to.${next}`)}
                          </Button>
                        )}
                        {r.status === 'done' && (
                          <Button
                            variant='ghost'
                            className='min-h-11 sm:min-h-9'
                            disabled={remove.isPending}
                            onClick={() => {
                              if (
                                window.confirm(
                                  t('platform.menu_requests.delete_confirm')
                                )
                              )
                                remove.mutate(r.id)
                            }}
                          >
                            <Trash2 aria-hidden />
                            {t('platform.menu_requests.delete')}
                          </Button>
                        )}
                      </div>
                    </div>
                    {r.note && (
                      <p className='rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-line'>
                        {r.note}
                      </p>
                    )}
                    <ul className='flex flex-wrap gap-2'>
                      {r.files.map((f) => (
                        <li key={f.n}>
                          <a
                            href={fileUrl(r.id, f.n)}
                            target='_blank'
                            rel='noopener'
                            className='block w-28 overflow-hidden rounded-md border text-xs'
                            title={f.name}
                          >
                            {f.mime.startsWith('image/') ? (
                              <img
                                src={fileUrl(r.id, f.n)}
                                alt={f.name}
                                loading='lazy'
                                width={112}
                                height={112}
                                className='h-28 w-28 bg-muted object-cover'
                              />
                            ) : (
                              <span className='flex h-28 w-28 flex-col items-center justify-center gap-1 bg-muted'>
                                <FileText aria-hidden className='size-8' />
                                PDF
                              </span>
                            )}
                            <span className='block truncate px-1.5 py-1'>
                              {f.name}
                            </span>
                          </a>
                        </li>
                      ))}
                    </ul>
                  </CardContent>
                </Card>
              </li>
            )
          })}
        </ul>
      )}
    </>
  )
}
