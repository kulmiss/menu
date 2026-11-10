import { Link } from '@tanstack/react-router'
import { ChevronsUpDown, LogOut, UserCog } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { initials } from '@/lib/initials'
import useDialogState from '@/hooks/use-dialog-state'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { LazySignOutDialog } from '@/components/lazy-sign-out-dialog'
import { useMe } from '@/features/auth/use-me'

function UserLabel({ name, email }: { name: string; email: string }) {
  return (
    <>
      <Avatar className='h-8 w-8 rounded-lg'>
        <AvatarFallback className='rounded-lg'>{initials(name)}</AvatarFallback>
      </Avatar>
      <div className='grid flex-1 text-start text-sm leading-tight'>
        <span className='truncate font-semibold'>{name}</span>
        <span className='truncate text-xs' dir='ltr'>
          {email}
        </span>
      </div>
    </>
  )
}

export function NavUser() {
  const { t } = useTranslation()
  const { isMobile } = useSidebar()
  const [open, setOpen] = useDialogState()
  const me = useMe()

  return (
    <>
      <SidebarMenu>
        <SidebarMenuItem>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <SidebarMenuButton
                size='lg'
                className='data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
              >
                <UserLabel name={me.name} email={me.email} />
                <ChevronsUpDown className='ms-auto size-4' aria-hidden />
              </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
              className='w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
              side={isMobile ? 'bottom' : 'right'}
              align='end'
              sideOffset={4}
            >
              <DropdownMenuLabel className='p-0 font-normal'>
                <div className='flex items-center gap-2 px-1 py-1.5 text-start text-sm'>
                  <UserLabel name={me.name} email={me.email} />
                </div>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuGroup>
                <DropdownMenuItem asChild className='min-h-11 sm:min-h-8'>
                  <Link to='/admin/settings'>
                    <UserCog />
                    {t('nav.profile')}
                  </Link>
                </DropdownMenuItem>
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                variant='destructive'
                className='min-h-11 sm:min-h-8'
                onClick={() => setOpen(true)}
              >
                <LogOut />
                {t('auth.sign_out')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </SidebarMenuItem>
      </SidebarMenu>

      <LazySignOutDialog open={!!open} onOpenChange={setOpen} />
    </>
  )
}
