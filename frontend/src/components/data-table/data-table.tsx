import { type ReactNode, useEffect, useMemo, useState } from 'react'
import {
  keepPreviousData,
  useQuery,
  type QueryKey,
} from '@tanstack/react-query'
import {
  type ColumnDef,
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  CalendarRange,
  Check,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Download,
  Filter,
  Rows3,
  Rows4,
  Search,
  Settings2,
  X,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { useIsMobile } from '@/hooks/use-mobile'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import { downloadCsv } from './csv'
import { type TableState, toParams, useTableState } from './url-state'

/**
 * The one table every admin list uses (owner, 2026-11-13, QUESTIONS #185).
 *
 * - server mode (`fetch`): search, filters, sort and pages run on the server;
 *   CSV comes from `csvUrl` with the same filters.
 * - client mode (`rows`): for short lists that are already loaded (team,
 *   QR codes, backups…): the same features, done in the browser.
 *
 * Filters, sort and page live in the address; column visibility and density
 * are remembered per table in this browser. On phones each row is a card.
 */
export type Col<T> = {
  id: string
  label: string
  cell: (row: T) => ReactNode
  /** Server sort key (server mode), or any value to sort by (client mode). */
  sort?: string
  sortValue?: (row: T) => string | number | null
  /** Plain text for the client search and the CSV. */
  text?: (row: T) => string | number | null
  /** The row's title on phones; can't be hidden. */
  primary?: boolean
  hidden?: boolean
  className?: string
  align?: 'end'
}

export type TableFilter<T> = {
  id: string
  label: string
  options: { value: string; label: string }[]
  /** Client mode: does the row match one of the chosen values? */
  match?: (row: T, values: string[]) => boolean
}

export type Page<T> = {
  data: T[]
  meta: {
    total: number
    page: number
    per_page: number
    pages: number
    /** Sums over the whole filtered view, e.g. per currency. */
    totals?: Record<string, number>
  }
}

type Props<T> = {
  id: string
  columns: Col<T>[]
  rowId: (row: T) => string | number
  searchPlaceholder?: string
  filters?: TableFilter<T>[]
  /** Label of the date range filter (server: `from` / `to`; client: `dateOf`). */
  dateLabel?: string
  dateOf?: (row: T) => string | null
  /** Server mode. */
  queryKey?: QueryKey
  fetch?: (params: URLSearchParams) => Promise<Page<T>>
  csvUrl?: (params: URLSearchParams) => string
  /** Client mode. */
  rows?: T[]
  loading?: boolean
  error?: boolean
  onRetry?: () => void
  csvName?: string
  /** Extra parameters always sent (e.g. deleted=1). */
  extra?: Record<string, string>
  prefix?: string
  onRowClick?: (row: T) => void
  bulk?: (rows: T[], clear: () => void) => ReactNode
  actions?: ReactNode
  empty?: ReactNode
  /** A line above the rows about the whole filtered view (totals…). */
  summary?: (meta: Page<T>['meta']) => ReactNode
}

type Density = 'comfortable' | 'compact'

const stored = <V,>(key: string, fallback: V): V => {
  try {
    const raw = localStorage.getItem(key)
    return raw ? (JSON.parse(raw) as V) : fallback
  } catch {
    return fallback
  }
}
const store = (key: string, value: unknown) => {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    /* private mode: just not remembered */
  }
}

export function DataTable<T>(props: Props<T>) {
  const { t } = useTranslation()
  const filters = useMemo(() => props.filters ?? [], [props.filters])
  const [state, update] = useTableState(
    props.prefix ?? '',
    filters.map((f) => f.id)
  )
  const [search, setSearch] = useState(state.q)
  const [selected, setSelected] = useState<Map<string | number, T>>(new Map())
  const [density, setDensity] = useState<Density>(() =>
    stored(`dt:${props.id}:density`, 'comfortable')
  )
  const [visibility, setVisibility] = useState<VisibilityState>(() =>
    stored(
      `dt:${props.id}:columns`,
      Object.fromEntries(
        props.columns.filter((c) => c.hidden).map((c) => [c.id, false])
      )
    )
  )

  // Typing waits a moment before searching.
  useEffect(() => {
    if (search === state.q) return
    const timer = setTimeout(() => update({ q: search }), 300)
    return () => clearTimeout(timer)
  }, [search, state.q, update])

  const server = !!props.fetch
  const fetchPage = props.fetch
  const params = toParams(state, props.extra)
  const queryKey = [...(props.queryKey ?? [props.id]), params.toString()]
  // The key holds every parameter sent; the fetch function is stable per table.
  // eslint-disable-next-line @tanstack/query/exhaustive-deps
  const query = useQuery({
    queryKey,
    queryFn: () => fetchPage!(params),
    enabled: server,
    placeholderData: keepPreviousData,
  })
  const local = useMemo(
    () => (server ? null : clientPage(props, state)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [server, props.rows, state, props.columns, filters]
  )
  const page: Page<T> | undefined = server ? query.data : local!
  const loading = server ? query.isPending : !!props.loading
  const failed = server ? query.isError && !query.data : !!props.error
  const rows = page?.data ?? []
  const meta = page?.meta

  const columns = useMemo<ColumnDef<T>[]>(
    () =>
      props.columns.map((c) => ({
        id: c.id,
        header: c.label,
        accessorFn: (row) => (c.text ? c.text(row) : null),
        cell: ({ row }) => c.cell(row.original),
        enableHiding: !c.primary,
      })),
    [props.columns]
  )
  const table = useReactTable({
    data: rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getRowId: (row) => String(props.rowId(row)),
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    state: { columnVisibility: visibility },
    onColumnVisibilityChange: (u) =>
      setVisibility((v) => {
        const next = typeof u === 'function' ? u(v) : u
        store(`dt:${props.id}:columns`, next)
        return next
      }),
  })
  const visible = props.columns.filter((c) => visibility[c.id] !== false)
  const byId = new Map(props.columns.map((c) => [c.id, c]))

  const filtered =
    !!state.q ||
    !!state.from ||
    !!state.to ||
    Object.values(state.filters).some((v) => v.length > 0)
  const clearFilters = () => {
    setSearch('')
    update({
      q: '',
      from: '',
      to: '',
      filters: Object.fromEntries(filters.map((f) => [f.id, []])),
    })
  }
  const clearSelection = () => setSelected(new Map())
  const pageIds = rows.map(props.rowId)
  const allOnPage =
    pageIds.length > 0 && pageIds.every((id) => selected.has(id))
  const someOnPage = pageIds.some((id) => selected.has(id))
  const toggleRow = (row: T, on: boolean) =>
    setSelected((s) => {
      const next = new Map(s)
      if (on) next.set(props.rowId(row), row)
      else next.delete(props.rowId(row))
      return next
    })
  const togglePage = (on: boolean) =>
    setSelected((s) => {
      const next = new Map(s)
      for (const row of rows)
        if (on) next.set(props.rowId(row), row)
        else next.delete(props.rowId(row))
      return next
    })

  const sortOf = (c: Col<T>) => {
    const key = server ? c.sort : c.sortValue ? c.id : undefined
    if (!key) return null
    return state.sort === key ? 'asc' : state.sort === `-${key}` ? 'desc' : null
  }
  const cycleSort = (c: Col<T>) => {
    const key = server ? c.sort : c.id
    const now = sortOf(c)
    update({ sort: now === null ? key! : now === 'asc' ? `-${key}` : '' })
  }

  const exportCsv = () => {
    if (server && props.csvUrl) {
      const p = toParams(state, props.extra)
      p.delete('page')
      p.delete('per')
      // A link click downloads the file without leaving the page.
      const a = document.createElement('a')
      a.href = props.csvUrl(p)
      a.click()
      return
    }
    const all = server
      ? rows
      : clientPage(props, { ...state, page: 1, per: 1e9 }).data
    downloadCsv(
      props.csvName ?? props.id,
      visible.filter((c) => c.text),
      all
    )
  }

  const mobile = useIsMobile()
  const cellPad = density === 'compact' ? 'px-2 py-1.5' : 'px-3 py-3'
  const selectable = !!props.bulk

  return (
    <div className='grid gap-3' data-table={props.id}>
      {/* Toolbar */}
      <div className='flex flex-wrap items-center gap-2'>
        <div className='relative min-w-48 flex-1 sm:max-w-72'>
          <Search
            className='pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground'
            aria-hidden
          />
          <Input
            type='search'
            className='min-h-11 ps-8 sm:min-h-9'
            placeholder={props.searchPlaceholder ?? t('table.search')}
            aria-label={props.searchPlaceholder ?? t('table.search')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        {filters.map((f) => (
          <FilterChip
            key={f.id}
            filter={f}
            values={state.filters[f.id] ?? []}
            onChange={(values) =>
              update({ filters: { ...state.filters, [f.id]: values } })
            }
          />
        ))}
        {props.dateLabel && (
          <DateChip
            label={props.dateLabel}
            from={state.from}
            to={state.to}
            onChange={(from, to) => update({ from, to })}
          />
        )}
        {filtered && (
          <Button
            variant='ghost'
            className='min-h-11 sm:min-h-9'
            onClick={clearFilters}
          >
            <X aria-hidden /> {t('table.clear')}
          </Button>
        )}
        <div className='ms-auto flex flex-wrap gap-2'>
          {props.actions}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant='outline' className='min-h-11 sm:min-h-9'>
                <Settings2 aria-hidden /> {t('table.view')}
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align='end' className='w-56'>
              <DropdownMenuLabel>{t('table.columns')}</DropdownMenuLabel>
              {props.columns
                .filter((c) => !c.primary)
                .map((c) => (
                  <DropdownMenuCheckboxItem
                    key={c.id}
                    checked={visibility[c.id] !== false}
                    onCheckedChange={(on) =>
                      table.getColumn(c.id)?.toggleVisibility(!!on)
                    }
                    onSelect={(e) => e.preventDefault()}
                  >
                    {c.label}
                  </DropdownMenuCheckboxItem>
                ))}
              <DropdownMenuSeparator />
              <DropdownMenuLabel>{t('table.density')}</DropdownMenuLabel>
              <DropdownMenuRadioGroup
                value={density}
                onValueChange={(v) => {
                  setDensity(v as Density)
                  store(`dt:${props.id}:density`, v)
                }}
              >
                <DropdownMenuRadioItem value='comfortable'>
                  <Rows3 aria-hidden /> {t('table.comfortable')}
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value='compact'>
                  <Rows4 aria-hidden /> {t('table.compact')}
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
            </DropdownMenuContent>
          </DropdownMenu>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={exportCsv}
            disabled={rows.length === 0}
          >
            <Download aria-hidden /> {t('table.export')}
          </Button>
        </div>
      </div>

      {/* Bulk actions */}
      {selectable && selected.size > 0 && (
        <div
          role='region'
          aria-label={t('table.bulk')}
          className='sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-md border bg-background p-2 shadow-sm'
        >
          <span className='px-1 text-sm font-medium' aria-live='polite'>
            {t('table.selected', { count: selected.size })}
          </span>
          {props.bulk!([...selected.values()], clearSelection)}
          <Button
            variant='ghost'
            size='sm'
            className='ms-auto min-h-11 sm:min-h-8'
            onClick={clearSelection}
          >
            {t('table.unselect')}
          </Button>
        </div>
      )}

      {props.summary && meta && (
        <div className='text-sm' aria-live='polite'>
          {props.summary(meta)}
        </div>
      )}

      {failed ? (
        <div className='grid justify-items-center gap-2 rounded-md border p-8 text-center'>
          <p className='text-sm'>{t('table.error')}</p>
          <Button
            variant='outline'
            onClick={() => (server ? query.refetch() : props.onRetry?.())}
          >
            {t('table.retry')}
          </Button>
        </div>
      ) : (
        <>
          {/* Wide screens: a table with a sticky header */}
          {!mobile && (
            <div
              className={cn(
                'max-h-[70vh] overflow-auto rounded-md border',
                query.isFetching && server && !loading && 'opacity-70'
              )}
            >
              <table className='w-full caption-bottom text-sm'>
                <thead className='sticky top-0 z-10 bg-muted/95 text-xs text-muted-foreground backdrop-blur'>
                  <tr>
                    {selectable && (
                      <th className={cn('w-10', cellPad)}>
                        <Checkbox
                          aria-label={t('table.select_page')}
                          checked={
                            allOnPage
                              ? true
                              : someOnPage
                                ? 'indeterminate'
                                : false
                          }
                          onCheckedChange={(on) => togglePage(on === true)}
                        />
                      </th>
                    )}
                    {visible.map((c) => {
                      const dir = sortOf(c)
                      const sortable = server ? !!c.sort : !!c.sortValue
                      return (
                        <th
                          key={c.id}
                          scope='col'
                          aria-sort={
                            dir === 'asc'
                              ? 'ascending'
                              : dir === 'desc'
                                ? 'descending'
                                : undefined
                          }
                          className={cn(
                            'text-start font-medium whitespace-nowrap',
                            cellPad,
                            c.align === 'end' && 'text-end',
                            c.className
                          )}
                        >
                          {sortable ? (
                            <button
                              type='button'
                              className='-mx-1 inline-flex min-h-8 items-center gap-1 rounded px-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                              onClick={() => cycleSort(c)}
                            >
                              {c.label}
                              {dir === 'asc' ? (
                                <ArrowUp className='size-3.5' aria-hidden />
                              ) : dir === 'desc' ? (
                                <ArrowDown className='size-3.5' aria-hidden />
                              ) : (
                                <ArrowUpDown
                                  className='size-3.5 opacity-50'
                                  aria-hidden
                                />
                              )}
                            </button>
                          ) : (
                            c.label
                          )}
                        </th>
                      )
                    })}
                  </tr>
                </thead>
                <tbody>
                  {loading
                    ? Array.from({ length: 6 }, (_, i) => (
                        <tr key={i} className='border-t'>
                          {selectable && <td className={cellPad} />}
                          {visible.map((c) => (
                            <td key={c.id} className={cellPad}>
                              <Skeleton className='h-4 w-full max-w-40' />
                            </td>
                          ))}
                        </tr>
                      ))
                    : table.getRowModel().rows.map((r) => {
                        const row = r.original
                        const id = props.rowId(row)
                        return (
                          <tr
                            key={r.id}
                            className={cn(
                              'border-t hover:bg-accent/50',
                              props.onRowClick && 'cursor-pointer',
                              selected.has(id) && 'bg-accent/40'
                            )}
                            onClick={() => props.onRowClick?.(row)}
                          >
                            {selectable && (
                              <td
                                className={cellPad}
                                onClick={(e) => e.stopPropagation()}
                              >
                                <Checkbox
                                  aria-label={t('table.select_row')}
                                  checked={selected.has(id)}
                                  onCheckedChange={(on) =>
                                    toggleRow(row, on === true)
                                  }
                                />
                              </td>
                            )}
                            {r.getVisibleCells().map((cell) => {
                              const c = byId.get(cell.column.id)!
                              return (
                                <td
                                  key={cell.id}
                                  className={cn(
                                    'align-top',
                                    cellPad,
                                    c.align === 'end' &&
                                      'text-end tabular-nums',
                                    c.className
                                  )}
                                >
                                  {flexRender(
                                    cell.column.columnDef.cell,
                                    cell.getContext()
                                  )}
                                </td>
                              )
                            })}
                          </tr>
                        )
                      })}
                </tbody>
              </table>
              {!loading && rows.length === 0 && (
                <Empty filtered={filtered} onClear={clearFilters}>
                  {props.empty}
                </Empty>
              )}
            </div>
          )}

          {/* Phones: one card per row, no sideways scrolling */}
          {mobile && (
            <ul className='grid gap-2' aria-label={t('table.rows')}>
              {loading
                ? Array.from({ length: 4 }, (_, i) => (
                    <li key={i}>
                      <Skeleton className='h-24 w-full' />
                    </li>
                  ))
                : rows.map((row) => {
                    const id = props.rowId(row)
                    const primary = props.columns.find((c) => c.primary)
                    return (
                      <li
                        key={String(id)}
                        className={cn(
                          'rounded-md border p-3',
                          selected.has(id) && 'bg-accent/40'
                        )}
                      >
                        <div className='flex items-start gap-3'>
                          {selectable && (
                            <Checkbox
                              className='mt-1'
                              aria-label={t('table.select_row')}
                              checked={selected.has(id)}
                              onCheckedChange={(on) =>
                                toggleRow(row, on === true)
                              }
                            />
                          )}
                          <div className='grid min-w-0 flex-1 gap-1'>
                            {primary && (
                              <div className='font-medium'>
                                {primary.cell(row)}
                              </div>
                            )}
                            <dl className='grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm'>
                              {visible
                                .filter((c) => !c.primary)
                                .map((c) => (
                                  <div key={c.id} className='contents'>
                                    <dt className='text-muted-foreground'>
                                      {c.label}
                                    </dt>
                                    <dd className='min-w-0 break-words'>
                                      {c.cell(row)}
                                    </dd>
                                  </div>
                                ))}
                            </dl>
                          </div>
                          {props.onRowClick && (
                            <Button
                              variant='ghost'
                              size='icon'
                              className='size-11 shrink-0'
                              aria-label={t('table.open', {
                                name: String(primary?.text?.(row) ?? ''),
                              })}
                              onClick={() => props.onRowClick?.(row)}
                            >
                              <ChevronRight />
                            </Button>
                          )}
                        </div>
                      </li>
                    )
                  })}
              {!loading && rows.length === 0 && (
                <li>
                  <Empty filtered={filtered} onClear={clearFilters}>
                    {props.empty}
                  </Empty>
                </li>
              )}
            </ul>
          )}
        </>
      )}

      {meta && meta.total > 0 && (
        <Pager
          state={state}
          meta={meta}
          onPage={(page) => update({ page }, true)}
          onPer={(per) => update({ per })}
        />
      )}
    </div>
  )
}

function Empty({
  filtered,
  onClear,
  children,
}: {
  filtered: boolean
  onClear: () => void
  children?: ReactNode
}) {
  const { t } = useTranslation()
  return (
    <div className='grid justify-items-center gap-2 p-8 text-center text-sm text-muted-foreground'>
      <p>{filtered ? t('table.no_match') : (children ?? t('table.empty'))}</p>
      {filtered && (
        <Button variant='outline' size='sm' onClick={onClear}>
          {t('table.clear')}
        </Button>
      )}
    </div>
  )
}

function FilterChip<T>({
  filter,
  values,
  onChange,
}: {
  filter: TableFilter<T>
  values: string[]
  onChange: (values: string[]) => void
}) {
  const { t } = useTranslation()
  const chosen = new Set(values)
  const names = filter.options.filter((o) => chosen.has(o.value))
  // One option: an on/off chip instead of a list.
  if (filter.options.length === 1) {
    const on = values.length > 0
    return (
      <Button
        variant={on ? 'secondary' : 'outline'}
        className={cn('min-h-11 sm:min-h-9', !on && 'border-dashed')}
        aria-pressed={on}
        onClick={() => onChange(on ? [] : [filter.options[0].value])}
      >
        {on ? <Check aria-hidden /> : <Filter aria-hidden />} {filter.label}
      </Button>
    )
  }
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          className='min-h-11 border-dashed sm:min-h-9'
          aria-label={`${filter.label}${names.length ? `: ${names.map((n) => n.label).join(', ')}` : ''}`}
        >
          <Filter aria-hidden /> {filter.label}
          {names.length > 0 && (
            <Badge variant='secondary' className='rounded-sm px-1 font-normal'>
              {names.length > 2
                ? t('table.n_chosen', { count: names.length })
                : names.map((n) => n.label).join(', ')}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-60 p-1' align='start'>
        <ul role='group' aria-label={filter.label} className='grid'>
          {filter.options.map((o) => {
            const on = chosen.has(o.value)
            return (
              <li key={o.value}>
                <button
                  type='button'
                  role='menuitemcheckbox'
                  aria-checked={on}
                  className='flex min-h-11 w-full items-center gap-2 rounded px-2 text-start text-sm hover:bg-accent sm:min-h-9'
                  onClick={() =>
                    onChange(
                      on
                        ? values.filter((v) => v !== o.value)
                        : [...values, o.value]
                    )
                  }
                >
                  <span
                    className={cn(
                      'flex size-4 items-center justify-center rounded-sm border',
                      on && 'border-primary bg-primary text-primary-foreground'
                    )}
                    aria-hidden
                  >
                    {on && <Check className='size-3' />}
                  </span>
                  {o.label}
                </button>
              </li>
            )
          })}
        </ul>
        {values.length > 0 && (
          <Button
            variant='ghost'
            size='sm'
            className='mt-1 w-full'
            onClick={() => onChange([])}
          >
            {t('table.clear')}
          </Button>
        )}
      </PopoverContent>
    </Popover>
  )
}

const iso = (d: Date) => d.toISOString().slice(0, 10)

function DateChip({
  label,
  from,
  to,
  onChange,
}: {
  label: string
  from: string
  to: string
  onChange: (from: string, to: string) => void
}) {
  const { t } = useTranslation()
  const preset = (days: number) => {
    const end = new Date()
    const start = new Date(end)
    start.setDate(start.getDate() - (days - 1))
    onChange(iso(start), iso(end))
  }
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant='outline' className='min-h-11 border-dashed sm:min-h-9'>
          <CalendarRange aria-hidden /> {label}
          {(from || to) && (
            <Badge variant='secondary' className='rounded-sm px-1 font-normal'>
              {from || '…'} – {to || '…'}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className='grid w-64 gap-3' align='start'>
        <div className='flex flex-wrap gap-1'>
          {[7, 30, 90].map((d) => (
            <Button
              key={d}
              variant='secondary'
              size='sm'
              onClick={() => preset(d)}
            >
              {t('table.last_days', { count: d })}
            </Button>
          ))}
        </div>
        <div className='grid gap-1'>
          <Label htmlFor={`${label}-from`}>{t('table.from')}</Label>
          <Input
            id={`${label}-from`}
            type='date'
            value={from}
            max={to || undefined}
            onChange={(e) => onChange(e.target.value, to)}
          />
        </div>
        <div className='grid gap-1'>
          <Label htmlFor={`${label}-to`}>{t('table.to')}</Label>
          <Input
            id={`${label}-to`}
            type='date'
            value={to}
            min={from || undefined}
            onChange={(e) => onChange(from, e.target.value)}
          />
        </div>
        {(from || to) && (
          <Button variant='ghost' size='sm' onClick={() => onChange('', '')}>
            {t('table.clear')}
          </Button>
        )}
      </PopoverContent>
    </Popover>
  )
}

function Pager({
  state,
  meta,
  onPage,
  onPer,
}: {
  state: TableState
  meta: Page<unknown>['meta']
  onPage: (page: number) => void
  onPer: (per: number) => void
}) {
  const { t } = useTranslation()
  const first = (meta.page - 1) * meta.per_page + 1
  const last = Math.min(meta.total, meta.page * meta.per_page)
  const btn = 'size-11 sm:size-9'
  return (
    <nav
      aria-label={t('table.pages')}
      className='flex flex-wrap items-center justify-between gap-2 text-sm'
    >
      <p className='text-muted-foreground tabular-nums' aria-live='polite'>
        {t('table.showing', { first, last, total: meta.total })}
      </p>
      <div className='flex flex-wrap items-center gap-1'>
        <label className='me-2 flex items-center gap-2 text-muted-foreground'>
          {t('table.per_page')}
          <select
            className='h-11 rounded-md border bg-background px-2 text-foreground sm:h-9'
            value={state.per}
            onChange={(e) => onPer(Number(e.target.value))}
          >
            {[10, 25, 50, 100].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </label>
        <Button
          variant='outline'
          size='icon'
          className={btn}
          disabled={meta.page <= 1}
          onClick={() => onPage(1)}
          aria-label={t('table.first')}
        >
          <ChevronsLeft />
        </Button>
        <Button
          variant='outline'
          size='icon'
          className={btn}
          disabled={meta.page <= 1}
          onClick={() => onPage(meta.page - 1)}
          aria-label={t('table.previous')}
        >
          <ChevronLeft />
        </Button>
        <span className='px-2 tabular-nums'>
          {t('table.page_of', { page: meta.page, pages: meta.pages })}
        </span>
        <Button
          variant='outline'
          size='icon'
          className={btn}
          disabled={meta.page >= meta.pages}
          onClick={() => onPage(meta.page + 1)}
          aria-label={t('table.next')}
        >
          <ChevronRight />
        </Button>
        <Button
          variant='outline'
          size='icon'
          className={btn}
          disabled={meta.page >= meta.pages}
          onClick={() => onPage(meta.pages)}
          aria-label={t('table.last')}
        >
          <ChevronsRight />
        </Button>
      </div>
    </nav>
  )
}

/** Client mode: search, filters, date range, sort and one page of the loaded rows. */
function clientPage<T>(props: Props<T>, s: TableState): Page<T> {
  const needle = s.q.trim().toLowerCase()
  let rows = (props.rows ?? []).filter((row) => {
    if (
      needle &&
      !props.columns.some((c) =>
        String(c.text?.(row) ?? '')
          .toLowerCase()
          .includes(needle)
      )
    )
      return false
    for (const f of props.filters ?? []) {
      const values = s.filters[f.id] ?? []
      if (values.length && f.match && !f.match(row, values)) return false
    }
    if (props.dateOf && (s.from || s.to)) {
      const d = (props.dateOf(row) ?? '').slice(0, 10)
      if (!d || (s.from && d < s.from) || (s.to && d > s.to)) return false
    }
    return true
  })
  const key = s.sort.replace(/^-/, '')
  const col = props.columns.find((c) => c.id === key && c.sortValue)
  if (col) {
    const dir = s.sort.startsWith('-') ? -1 : 1
    rows = [...rows].sort((a, b) => {
      const x = col.sortValue!(a) ?? ''
      const y = col.sortValue!(b) ?? ''
      return (x < y ? -1 : x > y ? 1 : 0) * dir
    })
  }
  const total = rows.length
  const pages = Math.max(1, Math.ceil(total / s.per))
  const page = Math.min(s.page, pages)
  return {
    data: rows.slice((page - 1) * s.per, page * s.per),
    meta: { total, page, per_page: s.per, pages },
  }
}
