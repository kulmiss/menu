import { useCallback, useState } from 'react'

/**
 * Table state kept in the address (?q=…&page=2&sort=-name&state=active,trial)
 * so a view can be shared and survives a refresh (QUESTIONS #185). Written
 * with history.replaceState: no new history entry per keystroke, and it works
 * the same under any route. Keys are prefixed when one page has two tables.
 */
export type TableState = {
  q: string
  page: number
  per: number
  sort: string
  from: string
  to: string
  filters: Record<string, string[]>
}

const read = (prefix: string, filterIds: string[]): TableState => {
  const p = new URLSearchParams(window.location.search)
  const get = (k: string) => p.get(prefix + k) ?? ''
  const num = (k: string, d: number) => {
    const n = Number(get(k))
    return Number.isFinite(n) && n > 0 ? n : d
  }
  return {
    q: get('q'),
    page: num('page', 1),
    per: [10, 25, 50, 100].includes(num('per', 25)) ? num('per', 25) : 25,
    sort: get('sort'),
    from: get('from'),
    to: get('to'),
    filters: Object.fromEntries(
      filterIds.map((id) => [id, get(id) ? get(id).split(',') : []])
    ),
  }
}

const write = (prefix: string, s: TableState) => {
  const p = new URLSearchParams(window.location.search)
  const set = (k: string, v: string, empty = '') =>
    v && v !== empty ? p.set(prefix + k, v) : p.delete(prefix + k)
  set('q', s.q)
  set('page', String(s.page), '1')
  set('per', String(s.per), '25')
  set('sort', s.sort)
  set('from', s.from)
  set('to', s.to)
  for (const [id, values] of Object.entries(s.filters))
    set(id, values.join(','))
  const qs = p.toString()
  window.history.replaceState(
    window.history.state,
    '',
    `${window.location.pathname}${qs ? `?${qs}` : ''}${window.location.hash}`
  )
}

export function useTableState(prefix: string, filterIds: string[]) {
  const [state, setState] = useState(() => read(prefix, filterIds))
  const update = useCallback(
    (change: Partial<TableState>, keepPage = false) =>
      setState((s) => {
        const next = { ...s, ...change }
        // Any change other than the page itself starts again at page 1.
        if (!keepPage && change.page === undefined) next.page = 1
        write(prefix, next)
        return next
      }),
    [prefix]
  )
  return [state, update] as const
}

/** The state as query parameters for the API (same names the server reads). */
export function toParams(s: TableState, extra?: Record<string, string>) {
  const p = new URLSearchParams()
  if (s.q.trim()) p.set('q', s.q.trim())
  p.set('page', String(s.page))
  p.set('per', String(s.per))
  if (s.sort) p.set('sort', s.sort)
  if (s.from) p.set('from', s.from)
  if (s.to) p.set('to', s.to)
  for (const [id, values] of Object.entries(s.filters))
    if (values.length) p.set(id, values.join(','))
  for (const [k, v] of Object.entries(extra ?? {})) if (v) p.set(k, v)
  return p
}
