import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { type NavItem, type SidebarData } from '../types'
import { sidebarData } from './sidebar-data'

/** Sidebar data with every title translated into the current UI language. */
export function useSidebarData(): SidebarData {
  const { t, i18n } = useTranslation()

  return useMemo(() => {
    const translateItem = (item: NavItem): NavItem =>
      item.items
        ? {
            ...item,
            title: t(item.title),
            items: item.items.map((sub) => ({ ...sub, title: t(sub.title) })),
          }
        : { ...item, title: t(item.title) }

    return {
      ...sidebarData,
      teams: sidebarData.teams.map((team) => ({ ...team, name: t(team.name) })),
      navGroups: sidebarData.navGroups.map((group) => ({
        ...group,
        title: t(group.title),
        items: group.items.map(translateItem),
      })),
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [t, i18n.language])
}
