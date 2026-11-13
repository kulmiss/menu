import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ExternalLink, MoreVertical, Plus, Table2, Tags } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { menusQuery } from '@/features/menu/api'
import { tr } from '@/features/menu/text'
import { useMenuContext } from '@/features/menu/use-menu-context'
import { type Branch, branchesQuery, useDeleteBranch } from './api'
import { BranchDialog } from './branch-dialog'
import { OverridesSheet } from './overrides-sheet'
import { TablesSheet } from './tables-sheet'

type Open =
  | { kind: 'edit'; branch: Branch | null }
  | { kind: 'tables'; branch: Branch }
  | { kind: 'overrides'; branch: Branch }
  | { kind: 'delete'; branch: Branch }
  | null

/** Branches (PLAN.md screen 7): info, tables, own prices, menus shown. */
export function BranchesPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const branches = useQuery(branchesQuery(ctx.tenantId))
  const menus = useQuery(menusQuery(ctx.tenantId))
  const remove = useDeleteBranch(ctx.tenantId)
  const [open, setOpen] = useState<Open>(null)
  const canEdit = ctx.canManage && ctx.canWrite
  const name = (b: Branch) => tr(b.name, ctx.locale)

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('branches.title')}
            </h1>
            <p className='text-muted-foreground'>{t('branches.description')}</p>
          </div>
          {canEdit && (
            <Button onClick={() => setOpen({ kind: 'edit', branch: null })}>
              <Plus /> {t('branches.new')}
            </Button>
          )}
        </div>
        {branches.isPending ? (
          <div className='grid gap-3'>
            <Skeleton className='h-24' />
            <Skeleton className='h-24' />
          </div>
        ) : (
          <div className='grid gap-3'>
            {branches.data?.map((b) => (
              <Card key={b.id} className='py-4'>
                <CardContent className='flex flex-wrap items-center gap-3'>
                  <div className='min-w-0 flex-1'>
                    <div className='flex flex-wrap items-center gap-2'>
                      <h2 className='font-semibold'>{name(b)}</h2>
                      {b.is_default && <Badge>{t('branches.main')}</Badge>}
                      {!b.is_active && (
                        <Badge variant='outline'>
                          {t('branches.inactive')}
                        </Badge>
                      )}
                    </div>
                    <a
                      href={b.url}
                      target='_blank'
                      rel='noreferrer'
                      className='inline-flex items-center gap-1 text-sm break-all text-muted-foreground hover:underline'
                    >
                      {b.url.replace(/^https?:\/\//, '')}{' '}
                      <ExternalLink className='size-3' />
                    </a>
                    {tr(b.address, ctx.locale) && (
                      <p className='text-sm text-muted-foreground'>
                        {tr(b.address, ctx.locale)}
                      </p>
                    )}
                  </div>
                  <Button
                    variant='outline'
                    disabled={!canEdit}
                    onClick={() => setOpen({ kind: 'tables', branch: b })}
                  >
                    <Table2 />{' '}
                    {t('branches.tables_count', { count: b.tables_count })}
                  </Button>
                  <Button
                    variant='outline'
                    disabled={!canEdit}
                    onClick={() => setOpen({ kind: 'overrides', branch: b })}
                  >
                    <Tags /> {t('branches.prices')}
                  </Button>
                  {canEdit && (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button
                          variant='ghost'
                          size='icon'
                          aria-label={t('branches.more', { name: name(b) })}
                        >
                          <MoreVertical />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align='end'>
                        <DropdownMenuItem
                          onSelect={() => setOpen({ kind: 'edit', branch: b })}
                        >
                          {t('branches.edit')}
                        </DropdownMenuItem>
                        {!b.is_default && (
                          <DropdownMenuItem
                            variant='destructive'
                            onSelect={() =>
                              setOpen({ kind: 'delete', branch: b })
                            }
                          >
                            {t('branches.delete')}
                          </DropdownMenuItem>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </Main>

      {open?.kind === 'edit' && branches.data && (
        <BranchDialog
          tenantId={ctx.tenantId}
          locale={ctx.locale}
          branch={open.branch}
          branches={branches.data}
          menus={menus.data ?? []}
          onClose={() => setOpen(null)}
        />
      )}
      {open?.kind === 'tables' && (
        <TablesSheet
          tenantId={ctx.tenantId}
          branch={open.branch}
          title={name(open.branch)}
          onClose={() => setOpen(null)}
        />
      )}
      {open?.kind === 'overrides' && (
        <OverridesSheet
          tenantId={ctx.tenantId}
          locale={ctx.locale}
          currency={ctx.currency}
          branch={open.branch}
          title={name(open.branch)}
          onClose={() => setOpen(null)}
        />
      )}
      <ConfirmDialog
        open={open?.kind === 'delete'}
        onOpenChange={(o) => !o && setOpen(null)}
        title={t('branches.delete_title', {
          name: open?.kind === 'delete' ? name(open.branch) : '',
        })}
        desc={t('branches.delete_desc')}
        destructive
        confirmText={t('branches.delete')}
        isLoading={remove.isPending}
        handleConfirm={async () => {
          if (open?.kind === 'delete') await remove.mutateAsync(open.branch.id)
          setOpen(null)
        }}
      />
    </>
  )
}
