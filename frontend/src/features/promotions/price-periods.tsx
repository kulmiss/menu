import { useState } from 'react'
import {
  queryOptions,
  useMutation,
  useQueries,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query'
import { Clock, Loader2, Pencil, Plus, Trash2, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl, validationErrors } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { DataTable } from '@/components/data-table'
import { branchesQuery } from '@/features/branches/api'
import { menusQuery, treeQuery } from '@/features/menu/api'
import { tr, withText } from '@/features/menu/text'
import { type Translations } from '@/features/menu/types'

type Rule = {
  item_id: number
  item_variant_id: number | null
  mode: 'fixed' | 'percent'
  value: number
}
export type PricePeriod = {
  id: number
  name: Translations
  branch_id: number | null
  weekdays: number[]
  starts_at: string
  ends_at: string
  is_active: boolean
  items: Rule[]
}
type Preview = {
  now: string
  running: { id: number; name: string; until: string; label: string }[]
  prices: { item: string; normal: string; now: string }[]
}

/** Monday first; values are 0 = Sunday … 6 = Saturday (as the server). */
const DAYS = [1, 2, 3, 4, 5, 6, 0]
const key = (t: number) => ['tenant', t, 'price-periods'] as const
const periodsQuery = (t: number) =>
  queryOptions({
    queryKey: key(t),
    queryFn: async () =>
      (await api.get<{ data: PricePeriod[] }>(tenantUrl(t, 'price-periods')))
        .data.data,
  })

type Props = {
  tenantId: number
  locale: string
  currency: string
  canEdit: boolean
  /** Plan feature "price_periods". */
  included: boolean
}

/** Promotions → Price periods: happy hour, lunch prices (owner, 2026-11-14). */
export function PricePeriodsSection(props: Props) {
  const { t } = useTranslation()
  const { tenantId, locale, canEdit, included } = props
  const qc = useQueryClient()
  const list = useQuery({ ...periodsQuery(tenantId), enabled: included })
  const [editing, setEditing] = useState<PricePeriod | 'new' | null>(null)
  const remove = useMutation({
    mutationFn: (id: number) =>
      api.delete(tenantUrl(tenantId, `price-periods/${id}`)),
    onSettled: () => qc.invalidateQueries({ queryKey: key(tenantId) }),
  })
  const dayName = (d: number) =>
    new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(
      new Date(2026, 10, 15 + d) // 15 Nov 2026 is a Sunday
    )

  return (
    <section className='mt-10 grid gap-4' aria-labelledby='pp-title'>
      <div className='flex flex-wrap items-end justify-between gap-2'>
        <div>
          <h2 id='pp-title' className='text-xl font-bold tracking-tight'>
            {t('promotions.periods.title')}
          </h2>
          <p className='text-muted-foreground'>
            {t('promotions.periods.description')}
          </p>
        </div>
        {included && canEdit && (
          <Button onClick={() => setEditing('new')}>
            <Plus /> {t('promotions.periods.new')}
          </Button>
        )}
      </div>
      {!included ? (
        <p className='rounded-md border p-6 text-center text-muted-foreground'>
          {t('promotions.periods.not_included')}
        </p>
      ) : (
        <>
          <DataTable<PricePeriod>
            id='price-periods'
            rows={list.data}
            loading={list.isPending}
            error={list.isError}
            onRetry={() => list.refetch()}
            rowId={(p) => p.id}
            csvName='price-periods'
            searchPlaceholder={t('promotions.periods.search')}
            empty={t('promotions.periods.empty')}
            onRowClick={canEdit ? (p) => setEditing(p) : undefined}
            filters={[
              {
                id: 'active',
                label: t('promotions.periods.status'),
                options: [
                  { value: 'on', label: t('promotions.periods.on') },
                  { value: 'off', label: t('promotions.off') },
                ],
                match: (p, v) => v.includes(p.is_active ? 'on' : 'off'),
              },
            ]}
            columns={[
              {
                id: 'name',
                label: t('promotions.periods.name'),
                primary: true,
                text: (p) => tr(p.name, locale),
                sortValue: (p) => tr(p.name, locale).toLowerCase(),
                cell: (p) => (
                  <span className='flex flex-wrap items-center gap-2'>
                    <Clock className='size-4 text-primary' aria-hidden />
                    <span className='font-medium'>{tr(p.name, locale)}</span>
                    {!p.is_active && (
                      <Badge variant='secondary'>{t('promotions.off')}</Badge>
                    )}
                  </span>
                ),
              },
              {
                id: 'days',
                label: t('promotions.periods.days'),
                text: (p) =>
                  DAYS.filter((d) => p.weekdays.includes(d))
                    .map(dayName)
                    .join(', '),
                cell: (p) =>
                  DAYS.filter((d) => p.weekdays.includes(d))
                    .map(dayName)
                    .join(', '),
              },
              {
                id: 'time',
                label: t('promotions.periods.time'),
                text: (p) => `${p.starts_at}–${p.ends_at}`,
                sortValue: (p) => p.starts_at,
                cell: (p) => (
                  <span className='tabular-nums'>
                    {p.starts_at}–{p.ends_at}
                  </span>
                ),
              },
              {
                id: 'dishes',
                label: t('promotions.periods.dishes_col'),
                align: 'end',
                text: (p) => new Set(p.items.map((i) => i.item_id)).size,
                sortValue: (p) => new Set(p.items.map((i) => i.item_id)).size,
                cell: (p) => new Set(p.items.map((i) => i.item_id)).size,
              },
              ...(canEdit
                ? [
                    {
                      id: 'actions',
                      label: t('promotions.periods.actions'),
                      cell: (p: PricePeriod) => (
                        <span
                          className='flex gap-1'
                          onClick={(e) => e.stopPropagation()}
                        >
                          <Button
                            variant='ghost'
                            size='icon'
                            className='size-11 sm:size-9'
                            aria-label={t('promotions.edit_one', {
                              name: tr(p.name, locale),
                            })}
                            onClick={() => setEditing(p)}
                          >
                            <Pencil />
                          </Button>
                          <Button
                            variant='ghost'
                            size='icon'
                            className='size-11 text-destructive sm:size-9'
                            aria-label={t('promotions.delete', {
                              name: tr(p.name, locale),
                            })}
                            onClick={() => {
                              if (
                                window.confirm(
                                  t('promotions.periods.delete_confirm')
                                )
                              )
                                remove.mutate(p.id)
                            }}
                          >
                            <Trash2 />
                          </Button>
                        </span>
                      ),
                    },
                  ]
                : []),
            ]}
          />
          <NowPreview tenantId={tenantId} locale={locale} />
        </>
      )}
      {editing && (
        <PeriodDialog
          {...props}
          period={editing === 'new' ? null : editing}
          dayName={dayName}
          onClose={() => setEditing(null)}
        />
      )}
    </section>
  )
}

function NowPreview({
  tenantId,
  locale,
}: {
  tenantId: number
  locale: string
}) {
  const { t } = useTranslation()
  const branches = useQuery(branchesQuery(tenantId))
  const [branch, setBranch] = useState('all')
  const preview = useQuery({
    queryKey: [...key(tenantId), 'preview', branch],
    queryFn: async () =>
      (
        await api.get<{ data: Preview }>(
          tenantUrl(
            tenantId,
            `price-periods/preview${branch === 'all' ? '' : `?branch_id=${branch}`}`
          )
        )
      ).data.data,
    refetchInterval: 60_000,
  })
  const p = preview.data

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('promotions.periods.now_title')}</CardTitle>
        <CardDescription>
          {p ? t('promotions.periods.now_at', { time: p.now }) : '…'}
        </CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {(branches.data?.length ?? 0) > 1 && (
          <Select value={branch} onValueChange={setBranch}>
            <SelectTrigger
              className='min-h-11 w-full sm:min-h-9 sm:w-60'
              aria-label={t('promotions.branch')}
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>
                {t('promotions.periods.main')}
              </SelectItem>
              {branches.data?.map((b) => (
                <SelectItem key={b.id} value={String(b.id)}>
                  {tr(b.name, locale)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        {p && p.running.length === 0 ? (
          <p className='text-sm text-muted-foreground'>
            {t('promotions.periods.none_running')}
          </p>
        ) : (
          <>
            <div className='flex flex-wrap gap-2'>
              {p?.running.map((r) => (
                <Badge key={r.id}>{r.label}</Badge>
              ))}
            </div>
            <ul className='divide-y rounded-md border text-sm'>
              {p?.prices.map((row, i) => (
                <li
                  key={i}
                  className='flex flex-wrap items-center justify-between gap-2 px-3 py-2'
                >
                  <span>{row.item}</span>
                  <span className='tabular-nums'>
                    <s className='me-2 text-muted-foreground'>{row.normal}</s>
                    <b>{row.now}</b>
                  </span>
                </li>
              ))}
            </ul>
          </>
        )}
      </CardContent>
    </Card>
  )
}

type Row = Rule & { label: string; normal: number }

function PeriodDialog({
  tenantId,
  locale,
  currency,
  period,
  dayName,
  onClose,
}: Props & {
  period: PricePeriod | null
  dayName: (d: number) => string
  onClose: () => void
}) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const branches = useQuery(branchesQuery(tenantId))
  const menus = useQuery(menusQuery(tenantId))
  const trees = useQueries({
    queries: (menus.data ?? []).map((m) => treeQuery(tenantId, m.id)),
  })
  const dishes = trees.flatMap(
    (q) => q.data?.categories.flatMap((c) => c.items) ?? []
  )
  const byId = new Map(dishes.map((d) => [d.id, d]))
  const describe = (r: Rule): Row | null => {
    const dish = byId.get(r.item_id)
    if (!dish) return null
    const size = dish.variants?.find((v) => v.id === r.item_variant_id)
    return {
      ...r,
      label:
        tr(dish.name, locale) + (size ? ` · ${tr(size.name, locale)}` : ''),
      normal: size?.price_minor ?? dish.price_minor ?? 0,
    }
  }

  const [name, setName] = useState(period ? tr(period.name, locale) : '')
  const [branch, setBranch] = useState(
    period?.branch_id ? String(period.branch_id) : 'all'
  )
  const [days, setDays] = useState<number[]>(
    period?.weekdays ?? [1, 2, 3, 4, 5]
  )
  const [starts, setStarts] = useState(period?.starts_at ?? '16:00')
  const [ends, setEnds] = useState(period?.ends_at ?? '18:00')
  const [active, setActive] = useState(period?.is_active ?? true)
  const [rules, setRules] = useState<Rule[]>(period?.items ?? [])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: (body: object) =>
      period
        ? api.put(tenantUrl(tenantId, `price-periods/${period.id}`), body)
        : api.post(tenantUrl(tenantId, 'price-periods'), body),
    meta: { inlineErrors: true },
    onSettled: () => qc.invalidateQueries({ queryKey: key(tenantId) }),
  })
  const rows = rules.map(describe)
  const used = new Set(rules.map((r) => r.item_id))
  const money = (minor: number) =>
    `${currency} ${(minor / 100).toLocaleString(locale, { maximumFractionDigits: 2 })}`

  function addDish(id: number) {
    const dish = byId.get(id)
    if (!dish) return
    const sizes = dish.variants ?? []
    const add: Rule[] = sizes.length
      ? sizes.map((v) => ({
          item_id: id,
          item_variant_id: v.id,
          mode: 'percent' as const,
          value: 10,
        }))
      : [
          {
            item_id: id,
            item_variant_id: null,
            mode: 'percent' as const,
            value: 10,
          },
        ]
    setRules([...rules, ...add])
  }
  const update = (i: number, patch: Partial<Rule>) =>
    setRules(rules.map((r, j) => (j === i ? { ...r, ...patch } : r)))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        name: withText(period?.name, locale, name.trim()),
        branch_id: branch === 'all' ? null : Number(branch),
        weekdays: days,
        starts_at: starts,
        ends_at: ends,
        is_active: active,
        items: rules,
      })
      onClose()
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }
  const firstError = Object.entries(errors).find(([k]) =>
    k.startsWith('items')
  )?.[1]

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-2xl'>
        <form onSubmit={submit} className='grid gap-4'>
          <DialogHeader>
            <DialogTitle>
              {period
                ? t('promotions.periods.edit')
                : t('promotions.periods.new')}
            </DialogTitle>
          </DialogHeader>
          <div className='grid gap-1.5'>
            <Label htmlFor='pp-name'>{t('promotions.periods.name')}</Label>
            <Input
              id='pp-name'
              maxLength={60}
              placeholder={t('promotions.periods.name_hint')}
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
            {errors.name && (
              <p className='text-sm text-destructive'>{errors.name}</p>
            )}
          </div>
          <fieldset className='grid gap-1.5'>
            <legend className='mb-1 text-sm font-medium'>
              {t('promotions.periods.days')}
            </legend>
            <div className='flex flex-wrap gap-1'>
              {DAYS.map((d) => {
                const on = days.includes(d)
                return (
                  <Button
                    key={d}
                    type='button'
                    size='sm'
                    variant={on ? 'default' : 'outline'}
                    aria-pressed={on}
                    className='min-h-11 min-w-12 sm:min-h-9'
                    onClick={() =>
                      setDays(on ? days.filter((x) => x !== d) : [...days, d])
                    }
                  >
                    {dayName(d)}
                  </Button>
                )
              })}
            </div>
            {errors.weekdays && (
              <p className='text-sm text-destructive'>{errors.weekdays}</p>
            )}
          </fieldset>
          <div className='grid gap-3 sm:grid-cols-3'>
            <div className='grid gap-1.5'>
              <Label htmlFor='pp-from'>{t('promotions.periods.from')}</Label>
              <Input
                id='pp-from'
                type='time'
                value={starts}
                onChange={(e) => setStarts(e.target.value)}
              />
            </div>
            <div className='grid gap-1.5'>
              <Label htmlFor='pp-to'>{t('promotions.periods.to')}</Label>
              <Input
                id='pp-to'
                type='time'
                value={ends}
                onChange={(e) => setEnds(e.target.value)}
              />
              {errors.ends_at && (
                <p className='text-sm text-destructive'>{errors.ends_at}</p>
              )}
            </div>
            <div className='grid gap-1.5'>
              <span className='text-sm font-medium'>
                {t('promotions.branch')}
              </span>
              <Select value={branch} onValueChange={setBranch}>
                <SelectTrigger
                  className='min-h-11 w-full sm:min-h-9'
                  aria-label={t('promotions.branch')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('promotions.all_branches')}
                  </SelectItem>
                  {branches.data?.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      {tr(b.name, locale)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <p className='-mt-2 text-sm text-muted-foreground'>
            {t('promotions.periods.time_hint')}
          </p>

          <fieldset className='grid gap-2'>
            <legend className='mb-1 text-sm font-medium'>
              {t('promotions.periods.prices')}
            </legend>
            <Select value='' onValueChange={(v) => addDish(Number(v))}>
              <SelectTrigger
                className='min-h-11 w-full sm:min-h-9'
                aria-label={t('promotions.periods.add_dish')}
              >
                <SelectValue placeholder={t('promotions.periods.add_dish')} />
              </SelectTrigger>
              <SelectContent>
                {trees.map((q, i) =>
                  (q.data?.categories ?? []).map((c) => (
                    <SelectGroup key={`${i}-${c.id}`}>
                      <SelectLabel>{tr(c.name, locale)}</SelectLabel>
                      {c.items
                        .filter((d) => !used.has(d.id))
                        .map((d) => (
                          <SelectItem key={d.id} value={String(d.id)}>
                            {tr(d.name, locale)}
                          </SelectItem>
                        ))}
                    </SelectGroup>
                  ))
                )}
              </SelectContent>
            </Select>
            {rows.length === 0 && (
              <p className='text-sm text-muted-foreground'>
                {t('promotions.periods.no_dishes')}
              </p>
            )}
            <ul className='grid gap-2'>
              {rows.map((row, i) =>
                row === null ? null : (
                  <li
                    key={`${row.item_id}-${row.item_variant_id}`}
                    className='grid gap-2 rounded-md border p-2 sm:grid-cols-[1fr_9rem_8rem_auto] sm:items-center'
                  >
                    <span className='text-sm'>
                      <span className='font-medium'>{row.label}</span>
                      <span className='block text-xs text-muted-foreground'>
                        {t('promotions.periods.normal', {
                          price: money(row.normal),
                        })}{' '}
                        →{' '}
                        {money(
                          Math.min(
                            row.normal,
                            row.mode === 'percent'
                              ? Math.round(
                                  (row.normal * (100 - row.value)) / 100
                                )
                              : row.value
                          )
                        )}
                      </span>
                    </span>
                    <Select
                      value={row.mode}
                      onValueChange={(m) =>
                        update(i, {
                          mode: m as Rule['mode'],
                          value: m === 'percent' ? 10 : row.normal,
                        })
                      }
                    >
                      <SelectTrigger
                        className='min-h-11 w-full sm:min-h-9'
                        aria-label={t('promotions.periods.mode')}
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='percent'>
                          {t('promotions.periods.percent')}
                        </SelectItem>
                        <SelectItem value='fixed'>
                          {t('promotions.periods.fixed')}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <Input
                      type='number'
                      inputMode='decimal'
                      min={row.mode === 'percent' ? 1 : 0.01}
                      max={row.mode === 'percent' ? 90 : undefined}
                      step={row.mode === 'percent' ? 1 : 0.01}
                      aria-label={
                        row.mode === 'percent'
                          ? t('promotions.periods.percent')
                          : t('promotions.periods.fixed')
                      }
                      className={cn(
                        errors[`items.${i}.value`] && 'border-destructive'
                      )}
                      value={
                        row.mode === 'percent' ? row.value : row.value / 100
                      }
                      onChange={(e) =>
                        update(i, {
                          value:
                            row.mode === 'percent'
                              ? Number(e.target.value)
                              : Math.round(Number(e.target.value) * 100),
                        })
                      }
                    />
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      className='size-11 sm:size-9'
                      aria-label={t('promotions.periods.remove', {
                        name: row.label,
                      })}
                      onClick={() => setRules(rules.filter((_, j) => j !== i))}
                    >
                      <X />
                    </Button>
                  </li>
                )
              )}
            </ul>
            {firstError && (
              <p className='text-sm text-destructive'>{firstError}</p>
            )}
          </fieldset>
          <div className='flex items-center justify-between gap-4'>
            <Label htmlFor='pp-active'>{t('promotions.periods.active')}</Label>
            <Switch
              id='pp-active'
              checked={active}
              onCheckedChange={setActive}
            />
          </div>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button
              type='submit'
              disabled={
                save.isPending ||
                !name.trim() ||
                days.length === 0 ||
                rules.length === 0
              }
            >
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
