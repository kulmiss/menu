import { createFileRoute } from '@tanstack/react-router'
import { InvitationPage } from '@/features/auth/invitation'

export const Route = createFileRoute('/auth/invite/$token')({
  component: function InvitationRoute() {
    const { token } = Route.useParams()
    return <InvitationPage token={token} />
  },
})
