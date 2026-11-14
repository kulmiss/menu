import { useState } from 'react'
import { useQueries, useQuery } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useFeature } from '@/features/auth/use-me'
import { menusQuery, treeQuery } from '@/features/menu/api'
import { MoneyInput } from '@/features/menu/components/money-input'
import { formatMoney } from '@/features/menu/money'
import { tr } from '@/features/menu/text'
import { type ItemSummary } from '@/features/menu/types'
import {
  type Branch,
  type Override,
  overridesQuery,
  useSaveOverrides,
} from './api'

type Props = {
  tenantId: number
  locale: string
  currency: string
  branch: Branch
  title: string
  onClose: () => void
}

/** A branch's own prices, sold-out and hidden items; blank = as the main menu. */
export function OverridesSheet(props: Props) {
  const { t } = useTranslation()
  const { tenantId, branch } = props
  const saved = useQuery(overridesQuery(tenantId, branch.id))
  const menus = useQuery(menusQuery(tenantId))
  const trees = useQueries({
    queries: (menus.data ?? []).map((m) => treeQuery(tenantId, m.id)),
  })
  const ready = saved.data && menus.data && trees.every((q) => q.data)

  return (
    <Sheet open onOpenChange={(open) => !open && props.onClose()}>
      <SheetContent className='w-full overflow-y-auto sm:max-w-2xl'>
        <SheetHeader>
          <SheetTitle>
            {t('branches.overrides_of', { name: props.title })}
          </SheetTitle>
          <SheetDescription>{t('branches.overrides_hint')}</SheetDescription>
        </SheetHeader>
        {ready ? (
          <OverridesForm
            {...props}
            initial={saved.data}
            sections={trees.flatMap((q) =>
              (q.data?.categories ?? []).map((c) => ({
                id: c.id,
                name: `${tr(q.data!.menu.name, props.locale)} · ${tr(c.name, props.locale)}`,
                items: c.items,
              }))
            )}
          />
        ) : (
          <div className='grid gap-2 px-4'>
            <Skeleton className='h-10' />
            <Skeleton className='h-10' />
            <Skeleton className='h-10' />
          </div>
        )}
      </SheetContent>
    </Sheet>
  )
}

type Section = { id: number; name: string; items: ItemSummary[] }

function OverridesForm({
  tenantId,
  locale,
  currency,
  branch,
  initial,
  sections,
  onClose,
}: Props & { initial: Override[]; sections: Section[] }) {
  const { t } = useTranslation()
  const soldOutOn = useFeature('sold_out')
  const save = useSaveOverrides(tenantId, branch.id)
  const [rows, setRows] = useState<Record<number, Override>>(() =>
    Object.fromEntries(initial.map((o) => [o.item_id, o]))
  )

  const row = (id: number): Override =>
    rows[id] ?? {
      item_id: id,
      price_minor: null,
      is_sold_out: null,
      is_hidden: null,
    }
  const set = (id: number, patch: Partial<Override>) =>
    setRows((r) => ({ ...r, [id]: { ...row(id), ...patch } }))

  async function submit() {
    await save.mutateAsync(Object.values(rows))
    toast.success(t('branches.overrides_saved'))
    onClose()
  }

  return (
    <>
      <div className='grid gap-6 px-4'>
        {sections.map((section) => (
          <section key={section.id} className='grid gap-1'>
            <h3 className='text-sm font-semibold'>{section.name}</h3>
            {section.items.map((item) => {
              const o = row(item.id)
              const sized = item.variants_count > 0
              return (
                <div
                  key={item.id}
                  className='grid grid-cols-1 items-center gap-2 border-b py-2 sm:grid-cols-[1fr_11rem_9rem_auto]'
                >
                  <div className='min-w-0'>
                    <p className='truncate text-sm font-medium'>
                      {tr(item.name, locale)}
                    </p>
                    <p className='text-xs text-muted-foreground'>
                      {t('branches.main_price')}{' '}
                      {formatMoney(
                        sized ? item.from_price_minor : item.price_minor,
                        currency,
                        locale
                      ) || '—'}
                    </p>
                  </div>
                  {sized ? (
                    <div className='grid gap-1'>
                      {(item.variants ?? []).map((v) => (
                        <div key={v.id} className='flex items-center gap-2'>
                          <span className='w-16 truncate text-xs text-muted-foreground'>
                            {tr(v.name, locale)}
                          </span>
                          <MoneyInput
                            label={t('branches.size_price', {
                              name: tr(item.name, locale),
                              size: tr(v.name, locale),
                            })}
                            hideLabel
                            className='flex-1'
                            value={o.sizes?.[v.id] ?? null}
                            currency={currency}
                            onChange={(p) =>
                              set(item.id, { sizes: { ...o.sizes, [v.id]: p } })
                            }
                          />
                        </div>
                      ))}
                    </div>
                  ) : (
                    <MoneyInput
                      label={t('branches.branch_price', {
                        name: tr(item.name, locale),
                      })}
                      hideLabel
                      value={o.price_minor}
                      currency={currency}
                      onChange={(v) => set(item.id, { price_minor: v })}
                    />
                  )}
                  {soldOutOn && (
                    <Select
                      value={
                        o.is_sold_out === null
                          ? 'same'
                          : o.is_sold_out
                            ? 'sold'
                            : 'available'
                      }
                      onValueChange={(v) =>
                        set(item.id, {
                          is_sold_out: v === 'same' ? null : v === 'sold',
                        })
                      }
                    >
                      <SelectTrigger
                        aria-label={t('branches.availability', {
                          name: tr(item.name, locale),
                        })}
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='same'>
                          {t('branches.same')}
                        </SelectItem>
                        <SelectItem value='sold'>
                          {t('branches.sold_out')}
                        </SelectItem>
                        <SelectItem value='available'>
                          {t('branches.available')}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  )}
                  <label className='flex min-h-11 items-center gap-2 text-sm sm:min-h-8'>
                    <Checkbox
                      checked={o.is_hidden === true}
                      onCheckedChange={(on) =>
                        set(item.id, { is_hidden: on === true ? true : null })
                      }
                    />
                    {t('branches.hide')}
                  </label>
                </div>
              )
            })}
          </section>
        ))}
        {sections.length === 0 && (
          <p className='text-sm text-muted-foreground'>
            {t('branches.no_items')}
          </p>
        )}
      </div>
      <SheetFooter className='sticky bottom-0 border-t bg-background'>
        <Button onClick={submit} disabled={save.isPending}>
          {save.isPending && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </SheetFooter>
    </>
  )
}
