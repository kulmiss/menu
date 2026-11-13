import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import {
  Ban,
  CheckCircle2,
  Eye,
  EyeOff,
  ImageOff,
  Percent,
  Trash2,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type Col, DataTable, type Page } from '@/components/data-table'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { menuKeys, menusQuery, useBulkItems, useBulkPrice } from './api'
import { formatMoney } from './money'
import { tr } from './text'
import { type Translations } from './types'
import { useMenuContext } from './use-menu-context'

type DishRow = {
  id: number
  name: Translations
  category: { id: number; name: Translations }
  menu: { id: number; name: Translations }
  price_minor: number | null
  from_minor: number | null
  sizes: number
  is_sold_out: boolean
  is_visible: boolean
  photo: string | null
  updated_at: string | null
}

/**
 * Menu → All dishes: every dish of every menu in one table (owner, 2026-11-13,
 * QUESTIONS #185). The menu editor stays the place to build menus; this is for
 * finding, checking and changing many dishes at once.
 */
export function DishesPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const navigate = useNavigate()
  const menus = useQuery(menusQuery(ctx.tenantId))
  const money = (m: number | null) => formatMoney(m, ctx.currency, ctx.locale)

  const columns: Col<DishRow>[] = [
    {
      id: 'name',
      label: t('dishes.dish'),
      primary: true,
      sort: 'name',
      text: (d) => tr(d.name, ctx.locale),
      cell: (d) => (
        <span className='flex min-w-0 items-center gap-3'>
          {d.photo ? (
            <img
              src={d.photo}
              alt=''
              width={40}
              height={40}
              loading='lazy'
              className='size-10 flex-none rounded object-cover'
            />
          ) : (
            <span className='flex size-10 flex-none items-center justify-center rounded bg-muted text-muted-foreground'>
              <ImageOff className='size-4' aria-hidden />
            </span>
          )}
          <span className='font-medium'>{tr(d.name, ctx.locale)}</span>
        </span>
      ),
    },
    {
      id: 'category',
      label: t('dishes.category'),
      sort: 'category',
      text: (d) => tr(d.category.name, ctx.locale),
      cell: (d) => (
        <span>
          {tr(d.category.name, ctx.locale)}
          <span className='block text-xs text-muted-foreground'>
            {tr(d.menu.name, ctx.locale)}
          </span>
        </span>
      ),
    },
    {
      id: 'price',
      label: t('dishes.price'),
      sort: 'price',
      align: 'end',
      text: (d) => (d.price_minor ?? d.from_minor ?? 0) / 100,
      cell: (d) =>
        d.price_minor !== null
          ? money(d.price_minor)
          : d.from_minor !== null
            ? t('dishes.from', { price: money(d.from_minor) })
            : '—',
    },
    {
      id: 'status',
      label: t('dishes.status'),
      text: (d) =>
        [
          d.is_sold_out && t('dishes.statuses.sold_out'),
          !d.is_visible && t('dishes.statuses.hidden'),
        ]
          .filter(Boolean)
          .join(', '),
      cell: (d) => (
        <span className='flex flex-wrap gap-1'>
          {d.is_sold_out && (
            <Badge variant='destructive'>{t('dishes.statuses.sold_out')}</Badge>
          )}
          {!d.is_visible && (
            <Badge variant='secondary'>{t('dishes.statuses.hidden')}</Badge>
          )}
          {d.is_visible && !d.is_sold_out && (
            <Badge variant='outline'>{t('dishes.statuses.on_menu')}</Badge>
          )}
        </span>
      ),
    },
    {
      id: 'updated_at',
      label: t('dishes.updated'),
      sort: 'updated_at',
      hidden: true,
      text: (d) => d.updated_at?.slice(0, 10) ?? '',
      cell: (d) =>
        d.updated_at ? new Date(d.updated_at).toLocaleDateString() : '—',
    },
  ]

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {t('dishes.title')}
          </h1>
          <p className='text-muted-foreground'>{t('dishes.description')}</p>
        </div>
        <DataTable<DishRow>
          id='dishes'
          queryKey={[...menuKeys.all(ctx.tenantId), 'dishes']}
          fetch={async (params) =>
            (
              await api.get<Page<DishRow>>(
                tenantUrl(ctx.tenantId, `items?${params}`)
              )
            ).data
          }
          csvUrl={
            ctx.canManage
              ? (params) =>
                  `/api/v1/t/${ctx.tenantId}/items?format=csv&${params}`
              : undefined
          }
          columns={columns}
          rowId={(d) => d.id}
          searchPlaceholder={t('dishes.search')}
          dateLabel={t('dishes.updated')}
          filters={[
            {
              id: 'menu',
              label: t('dishes.menu'),
              options: (menus.data ?? []).map((m) => ({
                value: String(m.id),
                label: tr(m.name, ctx.locale),
              })),
            },
            {
              id: 'status',
              label: t('dishes.status'),
              options: (
                ['sold_out', 'hidden', 'no_photo', 'new', 'popular'] as const
              ).map((s) => ({ value: s, label: t(`dishes.statuses.${s}`) })),
            },
          ]}
          onRowClick={(d) =>
            navigate({
              to: '/admin/menu',
              search: { menu: d.menu.id, item: d.id },
            })
          }
          empty={t('dishes.empty')}
          bulk={
            ctx.canWrite
              ? (rows, clear) => <DishBulk rows={rows} onDone={clear} />
              : undefined
          }
        />
      </Main>
    </>
  )
}

function DishBulk({ rows, onDone }: { rows: DishRow[]; onDone: () => void }) {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const bulk = useBulkItems(ctx.tenantId)
  const price = useBulkPrice(ctx.tenantId)
  const [deleting, setDeleting] = useState(false)
  const [percent, setPercent] = useState('10')
  const ids = rows.map((r) => r.id)
  const run = async (
    action: 'sold_out' | 'available' | 'show' | 'hide' | 'delete'
  ) => {
    await bulk.mutateAsync({ ids, action })
    toast.success(t(`dishes.done.${action}`, { count: ids.length }))
    onDone()
  }
  const btn = 'min-h-11 sm:min-h-8'
  return (
    <>
      <Button
        size='sm'
        variant='outline'
        className={btn}
        onClick={() => run('sold_out')}
      >
        <Ban aria-hidden /> {t('dishes.actions.sold_out')}
      </Button>
      <Button
        size='sm'
        variant='outline'
        className={btn}
        onClick={() => run('available')}
      >
        <CheckCircle2 aria-hidden /> {t('dishes.actions.available')}
      </Button>
      {ctx.canManage && (
        <>
          <Button
            size='sm'
            variant='outline'
            className={btn}
            onClick={() => run('hide')}
          >
            <EyeOff aria-hidden /> {t('dishes.actions.hide')}
          </Button>
          <Button
            size='sm'
            variant='outline'
            className={btn}
            onClick={() => run('show')}
          >
            <Eye aria-hidden /> {t('dishes.actions.show')}
          </Button>
        </>
      )}
      {ctx.canEditPrices && (
        <Popover>
          <PopoverTrigger asChild>
            <Button size='sm' variant='outline' className={btn}>
              <Percent aria-hidden /> {t('dishes.actions.price')}
            </Button>
          </PopoverTrigger>
          <PopoverContent className='grid w-60 gap-2'>
            <Label htmlFor='dish-percent'>{t('dishes.percent')}</Label>
            <Input
              id='dish-percent'
              type='number'
              inputMode='decimal'
              min={-90}
              max={500}
              value={percent}
              onChange={(e) => setPercent(e.target.value)}
            />
            <p className='text-xs text-muted-foreground'>
              {t('dishes.percent_hint')}
            </p>
            <Button
              disabled={!Number(percent) || price.isPending}
              onClick={async () => {
                await price.mutateAsync({
                  ids,
                  percent: Number(percent),
                  round_to: 0,
                })
                toast.success(t('dishes.done.price', { count: ids.length }))
                onDone()
              }}
            >
              {t('dishes.apply')}
            </Button>
          </PopoverContent>
        </Popover>
      )}
      {ctx.canManage && (
        <Button
          size='sm'
          variant='ghost'
          className={`${btn} text-destructive`}
          onClick={() => setDeleting(true)}
        >
          <Trash2 aria-hidden /> {t('dishes.actions.delete')}
        </Button>
      )}
      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title={t('dishes.delete_title', { count: ids.length })}
        desc={t('dishes.delete_desc')}
        confirmText={t('dishes.actions.delete')}
        destructive
        isLoading={bulk.isPending}
        handleConfirm={async () => {
          await run('delete')
          setDeleting(false)
        }}
      />
    </>
  )
}
