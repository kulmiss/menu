import { useCallback, useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { meQueryOptions } from '@/features/auth/api'

type OpenMap<K extends string> = Record<K, boolean>

function read<K extends string>(key: string, defaults: OpenMap<K>) {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return defaults
    const saved = JSON.parse(raw) as Partial<Record<string, unknown>>
    const out = { ...defaults }
    for (const k of Object.keys(defaults) as K[]) {
      if (typeof saved[k] === 'boolean') out[k] = saved[k]
    }
    return out
  } catch {
    // Storage blocked (private mode, a strict browser) or a broken value: the defaults.
    return defaults
  }
}

function write(key: string, value: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    /* storage blocked: just not remembered */
  }
}

/**
 * Which sections of a page are open, remembered per user on this device
 * (owner, 2026-11-21). `ids` maps each section to its element id, so a link
 * to `#<id>` (also on page load) opens that section and scrolls to it.
 */
export function useOpenSections<K extends string>(
  page: string,
  defaults: OpenMap<K>,
  ids: Record<K, string>
) {
  const qc = useQueryClient()
  const userId = qc.getQueryData(meQueryOptions.queryKey)?.id ?? 'guest'
  const key = `menu:sections:${page}:${userId}`
  const [open, setOpenState] = useState<OpenMap<K>>(() => read(key, defaults))

  const save = useCallback(
    (next: OpenMap<K>) => {
      setOpenState(next)
      write(key, next)
    },
    [key]
  )

  const setOpen = useCallback(
    (section: K, value: boolean) =>
      setOpenState((current) => {
        if (current[section] === value) return current
        const next = { ...current, [section]: value }
        write(key, next)
        return next
      }),
    [key]
  )

  const setAll = useCallback(
    (value: boolean) =>
      save(
        Object.fromEntries(
          Object.keys(defaults).map((k) => [k, value])
        ) as OpenMap<K>
      ),
    [defaults, save]
  )

  /** Opens a section and scrolls to it once it has opened. */
  const reveal = useCallback(
    (section: K, focus = false) => {
      setOpen(section, true)
      requestAnimationFrame(() =>
        requestAnimationFrame(() => {
          const el = document.getElementById(ids[section])
          if (!el) return
          const reduce = window.matchMedia?.(
            '(prefers-reduced-motion: reduce)'
          ).matches
          el.scrollIntoView({
            behavior: reduce ? 'auto' : 'smooth',
            block: 'start',
          })
          if (focus) {
            const target =
              el.querySelector<HTMLElement>('[aria-invalid="true"]') ??
              el.querySelector<HTMLElement>('[data-section-trigger]')
            target?.focus({ preventScroll: true })
          }
        })
      )
    },
    [ids, setOpen]
  )

  // A link to a section, on load or later, opens it.
  useEffect(() => {
    const fromHash = () => {
      const hash = decodeURIComponent(window.location.hash.slice(1))
      const section = (Object.keys(ids) as K[]).find((k) => ids[k] === hash)
      if (section) reveal(section)
    }
    fromHash()
    window.addEventListener('hashchange', fromHash)
    return () => window.removeEventListener('hashchange', fromHash)
  }, [ids, reveal])

  const allOpen = (Object.keys(open) as K[]).every((k) => open[k])
  return { open, setOpen, setAll, allOpen, reveal }
}
