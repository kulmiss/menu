import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { type Role } from '@/features/auth/api'
import { useCurrentTenant, useRole } from '@/features/auth/use-me'
import { type NavGroup, type NavItem, type SidebarData } from '../types'
import { sidebarData } from './sidebar-data'

type Features = Partial<Record<string, boolean>> | undefined

const allowed = (
  item: { roles?: Role[]; feature?: string },
  role: Role | null,
  features?: Features
) =>
  (!item.roles || (role !== null && item.roles.includes(role))) &&
  (!item.feature || features?.[item.feature] !== false)

/** Navigation translated into the UI language and filtered by role. */
export function filterNav(
  groups: NavGroup[],
  role: Role | null,
  t: (key: string) => string,
  features?: Features
): NavGroup[] {
  const translateItem = (item: NavItem): NavItem =>
    item.items
      ? {
          ...item,
          title: t(item.title),
          items: item.items
            .filter((sub) => allowed(sub, role, features))
            .map((sub) => ({ ...sub, title: t(sub.title) })),
        }
      : { ...item, title: t(item.title) }

  return groups
    .map((group) => ({
      ...group,
      title: t(group.title),
      items: group.items
        .filter((i) => allowed(i, role, features))
        .map(translateItem),
    }))
    .filter((group) => group.items.length > 0)
}

export function useSidebarData(): SidebarData {
  const { t, i18n } = useTranslation()
  const role = useRole()
  const features = useCurrentTenant()?.features

  return useMemo(
    () => ({ navGroups: filterNav(sidebarData.navGroups, role, t, features) }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, i18n.language, role, features]
  )
}
