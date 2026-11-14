import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { meQueryOptions } from '@/features/auth/api'
import { pkeys, send, usePlatformMutation } from './api'

/**
 * Settings → Features (owner, 2026-11-14, QUESTIONS #192): one switch per
 * feature for the whole platform. OFF wins over every plan and restaurant.
 */
type Row = {
  key: string
  enabled: boolean
  label: string
  plan_feature: boolean
  plans_off: number
  restaurants_off: number
}

const GROUPS: { id: string; keys: string[] }[] = [
  {
    id: 'guests',
    keys: [
      'sold_out',
      'order_list',
      'share',
      'pdf_menu',
      'remove_branding',
      'style_editorial',
      'style_grid',
      'style_bistro',
      'style_minimal',
    ],
  },
  {
    id: 'tools',
    keys: [
      'branches',
      'promotions',
      'price_periods',
      'insights',
      'import_export',
      'print_materials',
    ],
  },
  { id: 'platform', keys: ['signup'] },
]

const globalQ = {
  queryKey: [...pkeys.all, 'features-global'],
  queryFn: async () =>
    (await api.get<{ data: Row[] }>('/platform/settings/features')).data.data,
}

/** Keys switched off for the whole platform (for the plan and restaurant screens). */
export function useGloballyOff(): Set<string> {
  const q = useQuery(globalQ)
  return useMemo(
    () => new Set((q.data ?? []).filter((r) => !r.enabled).map((r) => r.key)),
    [q.data]
  )
}

export function FeatureSwitches() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const q = useQuery(globalQ)
  const [draft, setDraft] = useState<Record<string, boolean>>({})
  const save = usePlatformMutation((features: Record<string, boolean>) =>
    send('put', 'settings/features', { features })
  )
  const rows = useMemo(() => q.data ?? [], [q.data])
  const value = (r: Row) => draft[r.key] ?? r.enabled
  const changed = rows.filter((r) => value(r) !== r.enabled)
  const on = rows.filter(value).length

  if (q.isPending) return <Skeleton className='h-96 w-full' />
  if (q.isError)
    return (
      <p className='text-sm text-destructive'>
        {t('platform.switches.load_failed')}
      </p>
    )

  const grouped = GROUPS.map((g) => ({
    ...g,
    rows: g.keys
      .map((k) => rows.find((r) => r.key === k))
      .filter((r): r is Row => !!r),
  }))
  // Anything new on the server that no group lists yet still shows up.
  const listed = new Set(GROUPS.flatMap((g) => g.keys))
  const rest = rows.filter((r) => !listed.has(r.key))
  if (rest.length) grouped.push({ id: 'other', keys: [], rows: rest })

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('platform.switches.title')}</CardTitle>
        <CardDescription>{t('platform.switches.hint')}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-6'>
        <p className='text-sm' aria-live='polite'>
          {t('platform.switches.summary', { on, total: rows.length })}
        </p>
        {grouped.map((g) => (
          <section key={g.id} aria-labelledby={`fs-${g.id}`}>
            <h3 id={`fs-${g.id}`} className='mb-2 text-sm font-semibold'>
              {t(`platform.switches.groups.${g.id}`)}
            </h3>
            <ul className='divide-y rounded-md border'>
              {g.rows.map((r) => {
                const v = value(r)
                const notes = [
                  r.plans_off > 0 &&
                    t('platform.switches.plans_off', { count: r.plans_off }),
                  r.restaurants_off > 0 &&
                    t('platform.switches.restaurants_off', {
                      count: r.restaurants_off,
                    }),
                ].filter(Boolean)
                return (
                  <li key={r.key}>
                    <label className='flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2'>
                      <span className='min-w-0 flex-1'>
                        <span className='block text-sm font-medium'>
                          {r.label}
                        </span>
                        {v && notes.length > 0 && (
                          <span className='block text-xs text-muted-foreground'>
                            {notes.join(' · ')}
                          </span>
                        )}
                      </span>
                      <Badge variant={v ? 'secondary' : 'destructive'}>
                        {v
                          ? t('platform.switches.on')
                          : t('platform.switches.off')}
                      </Badge>
                      <Switch
                        checked={v}
                        onCheckedChange={(next) =>
                          setDraft((d) => ({ ...d, [r.key]: next }))
                        }
                        aria-label={r.label}
                      />
                    </label>
                  </li>
                )
              })}
            </ul>
          </section>
        ))}
        <div className='flex flex-wrap items-center gap-3'>
          <Button
            className='min-h-11 sm:min-h-9'
            disabled={changed.length === 0 || save.isPending}
            onClick={async () => {
              await save.mutateAsync(
                Object.fromEntries(changed.map((r) => [r.key, value(r)]))
              )
              setDraft({})
              // The Super Admin's own restaurant view (if any) follows at once.
              await qc.invalidateQueries({ queryKey: meQueryOptions.queryKey })
              toast.success(t('platform.switches.saved'))
            }}
          >
            {t('common.save')}
          </Button>
          {changed.length > 0 && (
            <span className='text-sm text-muted-foreground'>
              {t('platform.switches.unsaved', { count: changed.length })}
            </span>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
