import {
  BookOpen,
  FileSpreadsheet,
  LayoutDashboard,
  ListPlus,
  Tags,
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
      items: [{ title: 'nav.dashboard', url: '/admin', icon: LayoutDashboard }],
    },
    {
      title: 'nav.menu_group',
      items: [
        { title: 'nav.menu', url: '/admin/menu', icon: BookOpen },
        {
          title: 'nav.addons',
          url: '/admin/addons',
          icon: ListPlus,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.tags',
          url: '/admin/tags',
          icon: Tags,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.import',
          url: '/admin/import',
          icon: FileSpreadsheet,
          roles: ['owner', 'manager'],
        },
      ],
    },
    {
      title: 'nav.restaurant',
      items: [
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
