import {
  LayoutDashboard,
  Palette,
  Settings,
  UserCog,
  Users,
  Wrench,
} from 'lucide-react'
import { type SidebarData } from '../types'

/**
 * Navigation for the restaurant admin. Every `title` is an i18n key; it is
 * translated (and filtered by role) by `useSidebarData()` before rendering.
 */
export const sidebarData: SidebarData = {
  navGroups: [
    {
      title: 'nav.general',
      items: [
        { title: 'nav.dashboard', url: '/admin', icon: LayoutDashboard },
        {
          title: 'nav.team',
          url: '/admin/team',
          icon: Users,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.settings',
          icon: Settings,
          items: [
            { title: 'nav.profile', url: '/admin/settings', icon: UserCog },
            {
              title: 'nav.account',
              url: '/admin/settings/account',
              icon: Wrench,
            },
            {
              title: 'nav.appearance',
              url: '/admin/settings/appearance',
              icon: Palette,
            },
          ],
        },
      ],
    },
  ],
}
