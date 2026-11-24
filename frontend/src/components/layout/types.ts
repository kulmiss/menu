import { type LinkProps } from '@tanstack/react-router'
import { type Role } from '@/features/auth/api'

type BaseNavItem = {
  title: string
  badge?: string
  icon?: React.ElementType
  /** Only these roles see the item (server checks again). All when unset. */
  roles?: Role[]
  /** Hidden when the restaurant's plan has this feature switched off. */
  feature?: string
  /** When true, the item is removed entirely (not just marked "Upgrade") if `feature` is off. */
  hideWhenOff?: boolean
  /** When true, the item is removed if the plan allows only a single branch (branch_limit === 1). */
  hideWhenSingleBranch?: boolean
}

type NavLink = BaseNavItem & {
  url: LinkProps['to'] | (string & {})
  items?: never
}

type NavCollapsible = BaseNavItem & {
  items: (BaseNavItem & { url: LinkProps['to'] | (string & {}) })[]
  url?: never
}

type NavItem = NavCollapsible | NavLink

type NavGroup = {
  title: string
  items: NavItem[]
}

type SidebarData = {
  navGroups: NavGroup[]
}

export type { SidebarData, NavGroup, NavItem, NavCollapsible, NavLink }
