import { type Role } from '@/features/auth/api'
import { sidebarData } from './data/sidebar-data'
import { type NavItem } from './types'

/**
 * Screens a role may open, from the same lists as the sidebar (pre-launch
 * check: Staff typing a manager address got endless loading boxes, because
 * the server answers 403 — correctly — and the screen kept waiting). The
 * server still checks every request; this only shows "No access" instead.
 */
const extra: { url: string; roles: Role[] }[] = [
  { url: '/admin/print-materials', roles: ['owner', 'manager'] },
  { url: '/admin/settings/billing', roles: ['owner'] },
]

const links = sidebarData.navGroups.flatMap((g) =>
  g.items.flatMap((i: NavItem) =>
    i.items
      ? i.items.map((s) => ({ url: s.url, roles: s.roles ?? i.roles }))
      : [{ url: i.url, roles: i.roles }]
  )
)
const rules: { url: string; roles: Role[] }[] = [
  ...links.flatMap((l) =>
    l.roles ? [{ url: String(l.url), roles: l.roles }] : []
  ),
  ...extra,
]

export function canOpen(path: string, role: Role | null): boolean {
  const clean = path.replace(/\/+$/, '') || '/'
  const rule = rules.find(
    (r) => clean === r.url || clean.startsWith(r.url + '/')
  )
  return !rule || (role !== null && rule.roles.includes(role))
}
