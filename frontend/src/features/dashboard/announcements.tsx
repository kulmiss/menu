import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { tr } from '@/features/menu/text'

type Note = {
  id: number
  title: Record<string, string>
  body: Record<string, string>
  level: string
}

/** Messages from the platform (Super Admin), until the reader taps "Got it". */
export default function Announcements({
  tenantId,
  locale,
}: {
  tenantId: number
  locale: string
}) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const key = ['tenant', tenantId, 'announcements']
  const notes = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await api.get<{ data: Note[] }>(tenantUrl(tenantId, 'announcements')))
        .data.data,
    staleTime: 5 * 60_000,
  })
  const seen = useMutation({
    mutationFn: (id: number) =>
      api.post(tenantUrl(tenantId, `announcements/${id}/read`)),
    onSettled: () => qc.invalidateQueries({ queryKey: key }),
  })

  return (
    <>
      {notes.data?.map((n) => (
        <div
          key={n.id}
          role='status'
          className={`mb-4 flex flex-wrap items-start justify-between gap-2 rounded-md border p-3 text-sm ${n.level === 'warning' ? 'border-amber-500/50 bg-amber-500/10' : 'bg-muted/40'}`}
        >
          <div>
            <p className='font-medium'>{tr(n.title, locale)}</p>
            {tr(n.body, locale) && (
              <p className='whitespace-pre-line text-muted-foreground'>
                {tr(n.body, locale)}
              </p>
            )}
          </div>
          <Button size='sm' variant='ghost' onClick={() => seen.mutate(n.id)}>
            {t('dashboard.announcement_ok')}
          </Button>
        </div>
      ))}
    </>
  )
}
