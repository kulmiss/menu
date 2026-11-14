import { createFileRoute } from '@tanstack/react-router'
import { NewsSetting, ProfileSettings } from '@/features/settings/profile'

export const Route = createFileRoute('/admin/settings/')({
  component: function ProfileRoute() {
    return (
      <>
        <ProfileSettings />
        <NewsSetting />
      </>
    )
  },
})
