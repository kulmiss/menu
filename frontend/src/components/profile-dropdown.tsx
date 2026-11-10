import { Link } from '@tanstack/react-router'
import { LogOut, UserCog } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { initials } from '@/lib/initials'
import useDialogState from '@/hooks/use-dialog-state'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { SignOutDialog } from '@/components/sign-out-dialog'
import { useMe } from '@/features/auth/use-me'

export function ProfileDropdown() {
  const { t } = useTranslation()
  const [open, setOpen] = useDialogState()
  const me = useMe()

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            variant='ghost'
            className='relative size-11 rounded-full sm:size-8'
            aria-label={t('nav.user_menu')}
          >
            <Avatar className='h-8 w-8'>
              <AvatarFallback>{initials(me.name)}</AvatarFallback>
            </Avatar>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent className='w-56' align='end' forceMount>
          <DropdownMenuLabel className='font-normal'>
            <div className='flex flex-col gap-1.5'>
              <p className='text-sm leading-none font-medium'>{me.name}</p>
              <p
                className='text-xs leading-none text-muted-foreground'
                dir='ltr'
              >
                {me.email}
              </p>
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

      <SignOutDialog open={!!open} onOpenChange={setOpen} />
    </>
  )
}
