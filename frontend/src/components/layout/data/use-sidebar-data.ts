import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { type Role } from '@/features/auth/api'
import { useCurrentTenant, useRole } from '@/features/auth/use-me'
import { type NavGroup, type NavItem, type SidebarData } from '../types'
import { sidebarData } from './sidebar-data'

type Features = Partial<Record<string, boolean>> | undefined

const allowed = (item: { roles?: Role[] }, role: Role | null) =>
  !item.roles || (role !== null && item.roles.includes(role))

/** A feature the plan doesn't include stays in the menu, marked "Upgrade" (QUESTIONS #184). */
const locked = (item: { feature?: string }, features?: Features) =>
  !!item.feature && features?.[item.feature] === false

/**
 * Some items are removed entirely, not marked "Upgrade" (QUESTIONS #263):
 * a Super-Admin switch that is off (`hideWhenOff`), or a plan that allows only
 * one branch (`hideWhenSingleBranch`, branch_limit === 1).
 */
const hidden = (
  item: {
    feature?: string
    hideWhenOff?: boolean
    hideWhenSingleBranch?: boolean
  },
  features?: Features,
  branchLimit?: number | null
) =>
  (!!item.hideWhenOff &&
    !!item.feature &&
    features?.[item.feature] === false) ||
  (!!item.hideWhenSingleBranch && branchLimit === 1)

/** Navigation translated into the UI language, filtered by role; features not in the plan are marked. */
export function filterNav(
  groups: NavGroup[],
  role: Role | null,
  t: (key: string) => string,
  features?: Features,
  branchLimit?: number | null
): NavGroup[] {
  const translateItem = (item: NavItem): NavItem =>
    item.items
      ? {
          ...item,
          title: t(item.title),
          items: item.items
            .filter(
              (sub) => allowed(sub, role) && !hidden(sub, features, branchLimit)
            )
            .map((sub) => ({
              ...sub,
              title: t(sub.title),
              badge: locked(sub, features) ? t('nav.upgrade') : sub.badge,
            })),
        }
      : {
          ...item,
          title: t(item.title),
          badge: locked(item, features) ? t('nav.upgrade') : item.badge,
        }

  return groups
    .map((group) => ({
      ...group,
      title: t(group.title),
      items: group.items
        .filter((i) => allowed(i, role) && !hidden(i, features, branchLimit))
        .map(translateItem),
    }))
    .filter((group) => group.items.length > 0)
}

export function useSidebarData(): SidebarData {
  const { t, i18n } = useTranslation()
  const role = useRole()
  const tenant = useCurrentTenant()
  const features = tenant?.features
  const branchLimit = tenant?.branch_limit ?? null

  return useMemo(
    () => ({
      navGroups: filterNav(
        sidebarData.navGroups,
        role,
        t,
        features,
        branchLimit
      ),
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, i18n.language, role, features, branchLimit]
  )
}
