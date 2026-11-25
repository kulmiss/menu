import { describe, expect, it } from 'vitest'
import { sidebarData } from './sidebar-data'
import { filterNav } from './use-sidebar-data'

const titles = (role: Parameters<typeof filterNav>[1]) =>
  filterNav(sidebarData.navGroups, role, (k) => k).flatMap((g) =>
    g.items.map((i) => i.title)
  )

describe('sidebar navigation', () => {
  it('shows the team page to owners and managers only', () => {
    expect(titles('owner')).toContain('nav.team')
    expect(titles('manager')).toContain('nav.team')
    expect(titles('staff')).not.toContain('nav.team')
  })

  it('removes the Team page entirely when the Super Admin switches Team off', () => {
    expect(titles('owner')).toContain('nav.team')
    const off = filterNav(sidebarData.navGroups, 'owner', (k) => k, {
      team: false,
    }).flatMap((g) => g.items.map((i) => i.title))
    // Not merely badged "Upgrade" — gone from the menu.
    expect(off).not.toContain('nav.team')
  })

  it('hides Branches when the plan allows only one branch', () => {
    const titlesAt = (limit: number | null) =>
      filterNav(sidebarData.navGroups, 'owner', (k) => k, {}, limit).flatMap(
        (g) => g.items.map((i) => i.title)
      )
    // One-branch plan: no Branches item (the address lives in Restaurant info).
    expect(titlesAt(1)).not.toContain('nav.branches')
    // More than one, or unlimited: Branches stays.
    expect(titlesAt(3)).toContain('nav.branches')
    expect(titlesAt(null)).toContain('nav.branches')
  })

  it('keeps features the plan lacks in the menu, marked "Upgrade"', () => {
    const items = (features?: Record<string, boolean>) =>
      filterNav(sidebarData.navGroups, 'owner', (k) => k, features).flatMap(
        (g) => g.items.flatMap((i) => [i, ...(i.items ?? [])])
      )
    const insights = (f?: Record<string, boolean>) =>
      items(f).find((i) => i.title === 'nav.insights')
    expect(insights({ insights: false })?.badge).toBe('nav.upgrade')
    expect(insights({ insights: true })?.badge).toBeUndefined()
    expect(insights(undefined)?.badge).toBeUndefined()
    // Roles still hide what isn't yours.
    expect(
      filterNav(sidebarData.navGroups, 'staff', (k) => k, {
        insights: false,
      }).flatMap((g) => g.items.map((i) => i.title))
    ).not.toContain('nav.team')
  })

  it('points every link into /admin', () => {
    const urls = sidebarData.navGroups.flatMap((g) =>
      g.items.flatMap((i) => (i.items ? i.items.map((s) => s.url) : [i.url]))
    )
    expect(urls.every((u) => String(u).startsWith('/admin'))).toBe(true)
  })
})
