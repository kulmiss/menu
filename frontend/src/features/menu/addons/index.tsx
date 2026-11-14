import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl, validationErrors } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useFeature } from '@/features/auth/use-me'
import {
  addonGroupsQuery,
  type AddonGroupInput,
  menuKeys,
  useDeleteAddonGroup,
  useSaveAddonGroup,
} from '../api'
import { MoneyInput } from '../components/money-input'
import { TranslatableInput } from '../components/translatable-input'
import { formatMoney } from '../money'
import { tr } from '../text'
import { type AddonGroup } from '../types'
import { type MenuContext, useMenuContext } from '../use-menu-context'

/** Reusable add-on groups such as "Extra toppings" (PLAN.md §7.2.4). */
export function AddonGroupsPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const groups = useQuery(addonGroupsQuery(ctx.tenantId))
  const [editing, setEditing] = useState<AddonGroup | 'new' | null>(null)
  const [deleting, setDeleting] = useState<AddonGroup | null>(null)
  const remove = useDeleteAddonGroup(ctx.tenantId)

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-end justify-between gap-3'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('addons.title')}
            </h1>
            <p className='text-muted-foreground'>{t('addons.intro')}</p>
          </div>
          {ctx.canManage && ctx.canWrite && (
            <Button
              className='min-h-11 sm:min-h-9'
              onClick={() => setEditing('new')}
            >
              <Plus /> {t('addons.new')}
            </Button>
          )}
        </div>

        {groups.isPending ? (
          <Skeleton className='h-40 w-full' />
        ) : groups.isError ? (
          <p className='text-destructive'>{t('errors.generic')}</p>
        ) : groups.data.length === 0 ? (
          <p className='py-12 text-center text-muted-foreground'>
            {t('addons.empty')}
          </p>
        ) : (
          <div className='grid gap-4 md:grid-cols-2'>
            {groups.data.map((g) => (
              <GroupCard
                key={g.id}
                group={g}
                ctx={ctx}
                onEdit={() => setEditing(g)}
                onDelete={() => setDeleting(g)}
              />
            ))}
          </div>
        )}

        <GroupDialog
          ctx={ctx}
          group={editing === 'new' ? null : editing}
          open={editing !== null}
          onOpenChange={(o) => !o && setEditing(null)}
        />
        <ConfirmDialog
          open={deleting !== null}
          onOpenChange={(o) => !o && setDeleting(null)}
          title={t('addons.delete')}
          desc={t('addons.delete_confirm', {
            name: deleting ? tr(deleting.name, ctx.locale) : '',
            count: deleting?.items_count ?? 0,
          })}
          confirmText={t('menu.delete')}
          destructive
          isLoading={remove.isPending}
          handleConfirm={() =>
            deleting &&
            remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
          }
        />
      </Main>
    </>
  )
}

function GroupCard({
  group,
  ctx,
  onEdit,
  onDelete,
}: {
  group: AddonGroup
  ctx: MenuContext
  onEdit: () => void
  onDelete: () => void
}) {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const name = tr(group.name, ctx.locale)
  const soldOutOn = useFeature('sold_out')
  const soldOut = useMutation({
    mutationFn: (v: { id: number; is_sold_out: boolean }) =>
      api.patch(tenantUrl(ctx.tenantId, `addon-options/${v.id}/sold-out`), {
        is_sold_out: v.is_sold_out,
      }),
    onSettled: () =>
      qc.invalidateQueries({ queryKey: menuKeys.addonGroups(ctx.tenantId) }),
  })

  return (
    <Card>
      <CardHeader className='flex flex-row items-start gap-2'>
        <div className='min-w-0 flex-1'>
          <CardTitle className='truncate'>{name}</CardTitle>
          <p className='text-sm text-muted-foreground'>
            {t('addons.choices', {
              min: group.min_choices,
              max: group.max_choices ?? t('addons.any'),
            })}
            {' · '}
            {t('addons.used_by', { count: group.items_count ?? 0 })}
          </p>
        </div>
        {ctx.canManage && ctx.canWrite && (
          <>
            <Button
              variant='ghost'
              size='icon'
              className='size-11 sm:size-8'
              aria-label={t('addons.edit_named', { name })}
              onClick={onEdit}
            >
              <Pencil />
            </Button>
            <Button
              variant='ghost'
              size='icon'
              className='size-11 sm:size-8'
              aria-label={t('addons.delete_named', { name })}
              onClick={onDelete}
            >
              <Trash2 />
            </Button>
          </>
        )}
      </CardHeader>
      <CardContent>
        <ul className='divide-y'>
          {group.options.map((o) => (
            <li key={o.id} className='flex items-center gap-2 py-1'>
              <span className='flex-1 truncate'>{tr(o.name, ctx.locale)}</span>
              <span className='text-sm text-muted-foreground tabular-nums'>
                {o.price_minor > 0
                  ? `+${formatMoney(o.price_minor, ctx.currency, i18n.language)}`
                  : ''}
              </span>
              {soldOutOn && (
                <Switch
                  checked={!o.is_sold_out}
                  disabled={!ctx.canWrite || soldOut.isPending}
                  onCheckedChange={(available) =>
                    soldOut.mutate({ id: o.id!, is_sold_out: !available })
                  }
                  aria-label={t('menu.available_named', {
                    name: tr(o.name, ctx.locale),
                  })}
                  className='my-2.5'
                />
              )}
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}

type GroupDialogProps = {
  ctx: MenuContext
  group: AddonGroup | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

function GroupDialog(props: GroupDialogProps) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className='max-h-[90svh] overflow-y-auto sm:max-w-lg'>
        {/* Mounted on open, so every opening starts from the saved group. */}
        <GroupForm {...props} />
      </DialogContent>
    </Dialog>
  )
}

function GroupForm({ ctx, group, onOpenChange }: GroupDialogProps) {
  const { t } = useTranslation()
  const save = useSaveAddonGroup(ctx.tenantId)
  const [draft, setDraft] = useState<AddonGroupInput>(() =>
    group
      ? {
          name: group.name,
          min_choices: group.min_choices,
          max_choices: group.max_choices,
          options: group.options,
        }
      : {
          name: {},
          min_choices: 0,
          max_choices: null,
          options: [{ name: {}, price_minor: 0, is_sold_out: false }],
        }
  )
  const [errors, setErrors] = useState<Record<string, string>>({})

  const setOption = (
    i: number,
    patch: Partial<AddonGroupInput['options'][number]>
  ) =>
    setDraft((d) => ({
      ...d,
      options: d.options.map((o, j) => (j === i ? { ...o, ...patch } : o)),
    }))

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await save.mutateAsync({ id: group?.id, ...draft })
      onOpenChange(false)
    } catch (error) {
      const fields = validationErrors(error)
      if (fields) setErrors(fields)
    }
  }

  return (
    <>
      <form onSubmit={submit} className='grid gap-4'>
        <DialogHeader className='text-start'>
          <DialogTitle>
            {group ? t('addons.edit') : t('addons.new')}
          </DialogTitle>
        </DialogHeader>
        <TranslatableInput
          label={t('menu.name')}
          locale={ctx.locale}
          value={draft.name}
          onChange={(name) => setDraft((d) => ({ ...d, name }))}
          maxLength={80}
          error={errors.name}
          placeholder={t('addons.name_hint')}
        />
        <div className='grid grid-cols-2 gap-4'>
          <Label className='grid gap-2'>
            {t('addons.min')}
            <Input
              type='number'
              inputMode='numeric'
              min={0}
              value={draft.min_choices}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  min_choices: Number(e.target.value) || 0,
                }))
              }
              className='min-h-11 font-normal sm:min-h-9'
            />
          </Label>
          <Label className='grid gap-2'>
            {t('addons.max')}
            <Input
              type='number'
              inputMode='numeric'
              min={1}
              placeholder={t('addons.any')}
              value={draft.max_choices ?? ''}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  max_choices:
                    e.target.value === '' ? null : Number(e.target.value),
                }))
              }
              aria-invalid={errors.max_choices ? true : undefined}
              className='min-h-11 font-normal sm:min-h-9'
            />
          </Label>
        </div>
        {errors.max_choices && (
          <p className='text-sm text-destructive'>{errors.max_choices}</p>
        )}
        <p className='text-xs text-muted-foreground'>
          {t('addons.choices_hint')}
        </p>

        <fieldset className='grid gap-3'>
          <legend className='mb-2 text-sm font-medium'>
            {t('addons.options')}
          </legend>
          {draft.options.map((o, i) => (
            <div
              key={o.id ?? `new-${i}`}
              className='grid grid-cols-[1fr_auto] items-end gap-2 rounded-md border p-3'
            >
              <TranslatableInput
                label={t('addons.option_n', { n: i + 1 })}
                locale={ctx.locale}
                value={o.name}
                onChange={(name) => setOption(i, { name })}
                maxLength={80}
                error={errors[`options.${i}.name`]}
              />
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='size-11 sm:size-9'
                aria-label={t('addons.remove_option')}
                onClick={() =>
                  setDraft((d) => ({
                    ...d,
                    options: d.options.filter((_, j) => j !== i),
                  }))
                }
              >
                <Trash2 />
              </Button>
              <MoneyInput
                className='col-span-2'
                label={t('addons.extra_price')}
                currency={ctx.currency}
                value={o.price_minor}
                onChange={(p) => setOption(i, { price_minor: p ?? 0 })}
                error={errors[`options.${i}.price_minor`]}
              />
            </div>
          ))}
          <Button
            type='button'
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={() =>
              setDraft((d) => ({
                ...d,
                options: [
                  ...d.options,
                  { name: {}, price_minor: 0, is_sold_out: false },
                ],
              }))
            }
          >
            <Plus /> {t('addons.add_option')}
          </Button>
        </fieldset>
        <DialogFooter>
          <Button
            type='submit'
            className='min-h-11 sm:min-h-9'
            disabled={save.isPending}
          >
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('common.save')}
          </Button>
        </DialogFooter>
      </form>
    </>
  )
}
