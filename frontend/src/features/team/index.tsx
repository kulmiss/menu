import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { Lock, LogOut, Trash2, UserPlus, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useTenantStore } from '@/stores/tenant-store'
import { initials } from '@/lib/initials'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { type Role, type Tenant } from '@/features/auth/api'
import {
  isManagerOrOwner,
  manageableRoles,
  useCurrentTenant,
  useMe,
} from '@/features/auth/use-me'
import {
  invitationsQuery,
  type Invitation,
  type Member,
  membersQuery,
  useRemoveMember,
  useRevokeInvitation,
  useUpdateMember,
} from './api'
import { InviteDialog } from './invite-dialog'

export function TeamPage() {
  const { t } = useTranslation()
  const tenant = useCurrentTenant() as Tenant
  const role = tenant.membership?.role ?? null
  const [inviting, setInviting] = useState(false)
  const canInvite = manageableRoles(role).length > 0

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-end justify-between gap-3'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('team.title')}
            </h1>
            <p className='text-muted-foreground'>
              {t('team.intro', { restaurant: tenant.name })}
            </p>
          </div>
          {canInvite && (
            <Button
              className='min-h-11 sm:min-h-9'
              onClick={() => setInviting(true)}
            >
              <UserPlus />
              {t('team.invite')}
            </Button>
          )}
        </div>

        {isManagerOrOwner(role) ? (
          <div className='grid gap-6'>
            <Members tenant={tenant} role={role} />
            <Invitations tenantId={tenant.id} role={role} />
          </div>
        ) : (
          <NoAccess />
        )}

        {canInvite && (
          <InviteDialog
            tenantId={tenant.id}
            roles={manageableRoles(role)}
            open={inviting}
            onOpenChange={setInviting}
          />
        )}
      </Main>
    </>
  )
}

function NoAccess() {
  const { t } = useTranslation()
  return (
    <div className='flex flex-col items-center gap-2 py-16 text-center text-muted-foreground'>
      <Lock className='size-8' aria-hidden />
      <p>{t('errors.page.forbidden.text')}</p>
    </div>
  )
}

function ListSkeleton() {
  return (
    <div className='grid gap-3' aria-busy='true'>
      <Skeleton className='h-14 w-full' />
      <Skeleton className='h-14 w-full' />
    </div>
  )
}

function Members({ tenant, role }: { tenant: Tenant; role: Role | null }) {
  const { t } = useTranslation()
  const members = useQuery(membersQuery(tenant.id))

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('team.members')}</CardTitle>
      </CardHeader>
      <CardContent>
        {members.isPending ? (
          <ListSkeleton />
        ) : members.isError ? (
          <p className='text-sm text-destructive'>{t('errors.generic')}</p>
        ) : (
          <ul className='divide-y'>
            {members.data.map((m) => (
              <MemberRow key={m.id} member={m} tenant={tenant} role={role} />
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

function MemberRow({
  member,
  tenant,
  role,
}: {
  member: Member
  tenant: Tenant
  role: Role | null
}) {
  const { t } = useTranslation()
  const me = useMe()
  const navigate = useNavigate()
  const setCurrentTenantId = useTenantStore((s) => s.setCurrentTenantId)
  const update = useUpdateMember(tenant.id)
  const remove = useRemoveMember(tenant.id)
  const [confirming, setConfirming] = useState(false)

  const isSelf = member.user.id === me.id
  const roles = manageableRoles(role)
  const canManage = !isSelf && roles.includes(member.role)
  const canLeave = isSelf && member.role !== 'owner'

  function confirmRemove() {
    remove.mutate(
      { id: member.id, self: isSelf },
      {
        onSuccess: () => {
          setConfirming(false)
          if (isSelf) {
            setCurrentTenantId(null)
            void navigate({ to: '/admin' })
          }
        },
      }
    )
  }

  return (
    <li className='flex flex-wrap items-center gap-x-3 gap-y-2 py-3'>
      <Avatar className='size-9'>
        <AvatarFallback>{initials(member.user.name)}</AvatarFallback>
      </Avatar>
      <div className='min-w-0 flex-1 basis-40'>
        <p className='truncate font-medium'>
          {member.user.name}
          {isSelf && (
            <span className='ms-1 text-sm font-normal text-muted-foreground'>
              ({t('team.you')})
            </span>
          )}
        </p>
        <p className='truncate text-sm text-muted-foreground' dir='ltr'>
          {member.user.email}
        </p>
      </div>

      <div className='flex flex-wrap items-center gap-3'>
        {member.role === 'staff' && (
          <label className='flex min-h-11 items-center gap-2 text-sm sm:min-h-9'>
            <Switch
              checked={member.can_edit_prices}
              disabled={!canManage || update.isPending}
              onCheckedChange={(v) =>
                update.mutate({ id: member.id, can_edit_prices: v })
              }
            />
            {t('team.can_edit_prices')}
          </label>
        )}

        {canManage ? (
          <Select
            value={member.role}
            disabled={update.isPending}
            onValueChange={(v) =>
              update.mutate({ id: member.id, role: v as Role })
            }
          >
            <SelectTrigger
              className='min-h-11 w-32 sm:min-h-9'
              aria-label={t('team.role_of', { name: member.user.name })}
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {roles.map((r) => (
                <SelectItem key={r} value={r}>
                  {t(`roles.${r}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Badge variant='secondary'>{t(`roles.${member.role}`)}</Badge>
        )}

        {(canManage || canLeave) && (
          <Button
            variant='ghost'
            size='icon'
            className='size-11 sm:size-9'
            aria-label={
              canLeave
                ? t('team.leave')
                : t('team.remove_named', { name: member.user.name })
            }
            onClick={() => setConfirming(true)}
          >
            {canLeave ? <LogOut /> : <Trash2 />}
          </Button>
        )}
      </div>

      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title={canLeave ? t('team.leave') : t('team.remove')}
        desc={
          canLeave
            ? t('team.leave_confirm', { restaurant: tenant.name })
            : t('team.remove_confirm', { name: member.user.name })
        }
        confirmText={canLeave ? t('team.leave') : t('team.remove')}
        destructive
        isLoading={remove.isPending}
        handleConfirm={confirmRemove}
      />
    </li>
  )
}

function Invitations({
  tenantId,
  role,
}: {
  tenantId: number
  role: Role | null
}) {
  const { t } = useTranslation()
  const invitations = useQuery(invitationsQuery(tenantId))

  if (invitations.isSuccess && invitations.data.length === 0) return null

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('team.pending')}</CardTitle>
        <CardDescription>{t('team.pending_intro')}</CardDescription>
      </CardHeader>
      <CardContent>
        {invitations.isPending ? (
          <ListSkeleton />
        ) : invitations.isError ? (
          <p className='text-sm text-destructive'>{t('errors.generic')}</p>
        ) : (
          <ul className='divide-y'>
            {invitations.data.map((inv) => (
              <InvitationRow
                key={inv.id}
                invitation={inv}
                tenantId={tenantId}
                role={role}
              />
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

function InvitationRow({
  invitation,
  tenantId,
  role,
}: {
  invitation: Invitation
  tenantId: number
  role: Role | null
}) {
  const { t, i18n } = useTranslation()
  const revoke = useRevokeInvitation(tenantId)
  const canRevoke = manageableRoles(role).includes(invitation.role)
  const expires = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'medium',
  }).format(new Date(invitation.expires_at))

  return (
    <li className='flex flex-wrap items-center gap-x-3 gap-y-1 py-3'>
      <div className='min-w-0 flex-1 basis-40'>
        <p className='truncate font-medium' dir='ltr'>
          {invitation.email}
        </p>
        <p className='text-sm text-muted-foreground'>
          {t('team.expires', { date: expires })}
        </p>
      </div>
      <Badge variant='outline'>{t(`roles.${invitation.role}`)}</Badge>
      {canRevoke && (
        <Button
          variant='ghost'
          size='icon'
          className='size-11 sm:size-9'
          aria-label={t('team.revoke_named', { email: invitation.email })}
          disabled={revoke.isPending}
          onClick={() => revoke.mutate(invitation.id)}
        >
          <X />
        </Button>
      )}
    </li>
  )
}
