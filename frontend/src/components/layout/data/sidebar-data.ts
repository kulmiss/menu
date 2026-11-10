import {
  BookOpen,
  FileSpreadsheet,
  LayoutDashboard,
  BarChart3,
  Megaphone,
  Info,
  MapPin,
  QrCode,
  Palette,
  ListPlus,
  Tags,
  Settings,
  ShieldCheck,
  UserCog,
  Users,
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
          title: 'nav.insights',
          url: '/admin/insights',
          icon: BarChart3,
          roles: ['owner', 'manager'],
        },
      ],
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
          title: 'nav.promotions',
          url: '/admin/promotions',
          icon: Megaphone,
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
          title: 'nav.info',
          url: '/admin/info',
          icon: Info,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.branches',
          url: '/admin/branches',
          icon: MapPin,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.qr_codes',
          url: '/admin/qr-codes',
          icon: QrCode,
          roles: ['owner', 'manager'],
        },
        {
          title: 'nav.appearance',
          url: '/admin/appearance',
          icon: Palette,
          roles: ['owner', 'manager'],
        },
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
              title: 'nav.security',
              url: '/admin/settings/security',
              icon: ShieldCheck,
            },
          ],
        },
      ],
    },
  ],
}
