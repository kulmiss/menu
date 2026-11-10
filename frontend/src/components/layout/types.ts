import { type LinkProps } from '@tanstack/react-router'
import { type Role } from '@/features/auth/api'

type BaseNavItem = {
  title: string
  badge?: string
  icon?: React.ElementType
  /** Only these roles see the item (server checks again). All when unset. */
  roles?: Role[]
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
