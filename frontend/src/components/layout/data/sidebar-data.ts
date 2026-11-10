import { LayoutDashboard, Palette, Settings, UserCog, Wrench } from 'lucide-react'
import { Logo } from '@/assets/logo'
import { type SidebarData } from '../types'

/**
 * Navigation for the restaurant admin. Every `title` is an i18n key; it is
 * translated by `useSidebarData()` before rendering.
 * The user and restaurant entries are replaced with live data from the API in
 * Partition 2 (auth & tenancy).
 */
export const sidebarData: SidebarData = {
  user: {
    name: '',
    email: '',
    avatar: '',
  },
  teams: [
    {
      name: 'app.name',
      logo: Logo,
      plan: '',
    },
  ],
  navGroups: [
    {
      title: 'nav.general',
      items: [
        {
          title: 'nav.dashboard',
          url: '/',
          icon: LayoutDashboard,
        },
        {
          title: 'nav.settings',
          icon: Settings,
          items: [
            { title: 'nav.profile', url: '/settings', icon: UserCog },
            { title: 'nav.account', url: '/settings/account', icon: Wrench },
            {
              title: 'nav.appearance',
              url: '/settings/appearance',
              icon: Palette,
            },
          ],
        },
      ],
    },
  ],
}
