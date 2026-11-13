import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { ChevronsUpDown, Check, Store } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useTenantStore } from '@/stores/tenant-store'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { useCurrentTenant, useMe } from '@/features/auth/use-me'

/** Restaurant switcher: every restaurant the user belongs to (from /me). */
export function TeamSwitcher() {
  const { t } = useTranslation()
  const { isMobile, setOpenMobile } = useSidebar()
  const me = useMe()
  const current = useCurrentTenant()
  const setCurrentTenantId = useTenantStore((s) => s.setCurrentTenantId)
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  if (!current) return null

  const choose = (id: number) => {
    if (id === current.id) return
    setCurrentTenantId(id)
    // Drop the previous restaurant's data and start from its dashboard.
    queryClient.removeQueries({ queryKey: ['tenant'] })
    setOpenMobile(false)
    void navigate({ to: '/admin' })
  }

  const single = me.tenants.length < 2
  const label = (
    <>
      <div className='flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground'>
        <Store className='size-4' aria-hidden />
      </div>
      <div className='grid flex-1 text-start text-sm leading-tight'>
        <span className='truncate font-semibold'>{current.name}</span>
        <span className='truncate text-xs'>
          {current.membership ? t(`roles.${current.membership.role}`) : ''}
        </span>
      </div>
    </>
  )

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        {single ? (
          <SidebarMenuButton size='lg' asChild>
            <div>{label}</div>
          </SidebarMenuButton>
        ) : (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <SidebarMenuButton
                size='lg'
                aria-label={t('switcher.label')}
                className='data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
              >
                {label}
                <ChevronsUpDown className='ms-auto' aria-hidden />
              </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
              className='w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
              align='start'
              side={isMobile ? 'bottom' : 'right'}
              sideOffset={4}
            >
              <DropdownMenuLabel className='text-xs text-muted-foreground'>
                {t('switcher.title')}
              </DropdownMenuLabel>
              {me.tenants.map((tenant) => (
                <DropdownMenuItem
                  key={tenant.id}
                  onClick={() => choose(tenant.id)}
                  className='min-h-11 gap-2 p-2 sm:min-h-8'
                >
                  <div className='flex size-6 items-center justify-center rounded-sm border'>
                    <Store className='size-4 shrink-0' aria-hidden />
                  </div>
                  <span className='flex-1 truncate'>{tenant.name}</span>
                  {tenant.id === current.id && (
                    <Check
                      className='size-4'
                      aria-label={t('switcher.current')}
                    />
                  )}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
