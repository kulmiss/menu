import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowDown,
  ArrowUp,
  ExternalLink,
  Megaphone,
  Pencil,
  Plus,
  Sparkles,
  Trash2,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant } from '@/features/auth/use-me'
import { tr } from '@/features/menu/text'
import { useMenuContext } from '@/features/menu/use-menu-context'
import {
  type Promotion,
  promotionsQuery,
  useDeletePromotion,
  useReorderPromotions,
} from './api'
import { PricePeriodsSection } from './price-periods'
import { PromotionDialog } from './promotion-dialog'

/** Promotions (PLAN.md screen 11): specials and announcements with dates. */
export function PromotionsPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const tenant = useCurrentTenant()
  const list = useQuery(promotionsQuery(ctx.tenantId))
  const remove = useDeletePromotion(ctx.tenantId)
  const reorder = useReorderPromotions(ctx.tenantId)
  const [editing, setEditing] = useState<Promotion | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Promotion | null>(null)
  const canEdit = ctx.canManage && ctx.canWrite
  const rows = list.data ?? []

  const move = (i: number, by: number) => {
    const ids = rows.map((r) => r.id)
    ;[ids[i], ids[i + by]] = [ids[i + by], ids[i]]
    reorder.mutate(ids)
  }

  const when = (p: Promotion) =>
    p.starts_at || p.ends_at
      ? `${p.starts_at ?? t('promotions.now')} → ${p.ends_at ?? t('promotions.no_end')}`
      : t('promotions.always')

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('promotions.title')}
            </h1>
            <p className='text-muted-foreground'>
              {t('promotions.description')}
            </p>
          </div>
          <div className='flex gap-2'>
            {tenant && (
              <Button asChild variant='outline'>
                <a href={`/${tenant.slug}`} target='_blank' rel='noreferrer'>
                  <ExternalLink /> {t('promotions.view_menu')}
                </a>
              </Button>
            )}
            {canEdit && (
              <Button onClick={() => setEditing('new')}>
                <Plus /> {t('promotions.new')}
              </Button>
            )}
          </div>
        </div>
        {list.isPending ? (
          <Skeleton className='h-32' />
        ) : rows.length === 0 ? (
          <p className='rounded-md border p-6 text-center text-muted-foreground'>
            {t('promotions.empty')}
          </p>
        ) : (
          <div className='grid gap-3'>
            {rows.map((p, i) => (
              <Card key={p.id} className='py-3'>
                <CardContent className='flex flex-wrap items-center gap-3'>
                  {p.type === 'special' ? (
                    <Sparkles className='size-5 text-primary' />
                  ) : (
                    <Megaphone className='size-5 text-primary' />
                  )}
                  {p.media?.urls['320'] && (
                    <img
                      src={p.media.urls['320']}
                      alt=''
                      className='size-12 rounded object-cover'
                    />
                  )}
                  <div className='min-w-0 flex-1'>
                    <div className='flex flex-wrap items-center gap-2'>
                      <p className='font-medium'>{tr(p.title, ctx.locale)}</p>
                      <Badge variant='outline'>
                        {t(`promotions.types.${p.type}`)}
                      </Badge>
                      {!p.is_active && (
                        <Badge variant='secondary'>{t('promotions.off')}</Badge>
                      )}
                    </div>
                    <p className='text-sm text-muted-foreground'>{when(p)}</p>
                  </div>
                  {canEdit && (
                    <div className='flex gap-1'>
                      <Button
                        variant='ghost'
                        size='icon'
                        disabled={i === 0}
                        aria-label={t('promotions.up', {
                          name: tr(p.title, ctx.locale),
                        })}
                        onClick={() => move(i, -1)}
                      >
                        <ArrowUp />
                      </Button>
                      <Button
                        variant='ghost'
                        size='icon'
                        disabled={i === rows.length - 1}
                        aria-label={t('promotions.down', {
                          name: tr(p.title, ctx.locale),
                        })}
                        onClick={() => move(i, 1)}
                      >
                        <ArrowDown />
                      </Button>
                      <Button
                        variant='ghost'
                        size='icon'
                        aria-label={t('promotions.edit_one', {
                          name: tr(p.title, ctx.locale),
                        })}
                        onClick={() => setEditing(p)}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        variant='ghost'
                        size='icon'
                        aria-label={t('promotions.delete', {
                          name: tr(p.title, ctx.locale),
                        })}
                        onClick={() => setDeleting(p)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
        <PricePeriodsSection
          tenantId={ctx.tenantId}
          locale={ctx.locale}
          currency={tenant?.currency_code ?? ''}
          canEdit={canEdit && ctx.canEditPrices}
          included={tenant?.features?.price_periods !== false}
        />
      </Main>
      {editing !== null && (
        <PromotionDialog
          tenantId={ctx.tenantId}
          locale={ctx.locale}
          promotion={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={t('promotions.delete_title')}
        desc={t('promotions.delete_desc')}
        destructive
        confirmText={t('branches.delete')}
        isLoading={remove.isPending}
        handleConfirm={async () => {
          if (deleting) await remove.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </>
  )
}
