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

  it('points every link into /admin', () => {
    const urls = sidebarData.navGroups.flatMap((g) =>
      g.items.flatMap((i) => (i.items ? i.items.map((s) => s.url) : [i.url]))
    )
    expect(urls.every((u) => String(u).startsWith('/admin'))).toBe(true)
  })
})
