import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download, Loader2, Plus, Printer, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { ConfirmDialog } from '@/components/confirm-dialog'
import {
  type Branch,
  type DiningTable,
  downloadUrl,
  tablesQuery,
  useAddTables,
  useDeleteTable,
} from './api'

type Props = {
  tenantId: number
  branch: Branch
  title: string
  onClose: () => void
}

/** A branch's tables: add one or a numbered range, print their cards. */
export function TablesSheet({ tenantId, branch, title, onClose }: Props) {
  const { t } = useTranslation()
  const tables = useQuery(tablesQuery(tenantId, branch.id))
  const add = useAddTables(tenantId, branch.id)
  const remove = useDeleteTable(tenantId)
  const [prefix, setPrefix] = useState(t('branches.table_prefix'))
  const [from, setFrom] = useState('1')
  const [to, setTo] = useState('10')
  const [label, setLabel] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [deleting, setDeleting] = useState<DiningTable | null>(null)

  async function addRange(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      await add.mutateAsync({
        prefix: prefix.trim(),
        from: Number(from),
        to: Number(to),
      })
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  async function addOne(e: React.FormEvent) {
    e.preventDefault()
    if (!label.trim()) return
    setErrors({})
    try {
      await add.mutateAsync({ label: label.trim() })
      setLabel('')
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const count = tables.data?.length ?? 0

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent className='w-full overflow-y-auto sm:max-w-md'>
        <SheetHeader>
          <SheetTitle>{t('branches.tables_of', { name: title })}</SheetTitle>
          <SheetDescription>{t('branches.tables_hint')}</SheetDescription>
        </SheetHeader>
        <div className='grid gap-6 px-4 pb-6'>
          <form onSubmit={addRange} className='grid gap-2'>
            <p className='text-sm font-medium'>{t('branches.add_range')}</p>
            <div className='grid grid-cols-[1fr_5rem_5rem] gap-2'>
              <div className='grid gap-1'>
                <Label htmlFor='t-prefix' className='text-xs'>
                  {t('branches.prefix')}
                </Label>
                <Input
                  id='t-prefix'
                  value={prefix}
                  maxLength={20}
                  onChange={(e) => setPrefix(e.target.value)}
                />
              </div>
              <div className='grid gap-1'>
                <Label htmlFor='t-from' className='text-xs'>
                  {t('branches.from')}
                </Label>
                <Input
                  id='t-from'
                  inputMode='numeric'
                  value={from}
                  onChange={(e) => setFrom(e.target.value.replace(/\D/g, ''))}
                />
              </div>
              <div className='grid gap-1'>
                <Label htmlFor='t-to' className='text-xs'>
                  {t('branches.to')}
                </Label>
                <Input
                  id='t-to'
                  inputMode='numeric'
                  value={to}
                  onChange={(e) => setTo(e.target.value.replace(/\D/g, ''))}
                />
              </div>
            </div>
            {(errors.to || errors.from) && (
              <p className='text-sm text-destructive'>
                {errors.to ?? errors.from}
              </p>
            )}
            {errors.code && (
              <p className='text-sm text-destructive'>{errors.code}</p>
            )}
            <Button
              type='submit'
              variant='secondary'
              disabled={add.isPending || !from || !to}
            >
              {add.isPending ? <Loader2 className='animate-spin' /> : <Plus />}
              {t('branches.add_tables')}
            </Button>
          </form>
          <form onSubmit={addOne} className='flex items-end gap-2'>
            <div className='grid flex-1 gap-1'>
              <Label htmlFor='t-label' className='text-xs'>
                {t('branches.one_table')}
              </Label>
              <Input
                id='t-label'
                value={label}
                maxLength={30}
                placeholder={t('branches.one_table_placeholder')}
                onChange={(e) => setLabel(e.target.value)}
              />
            </div>
            <Button
              type='submit'
              variant='outline'
              disabled={add.isPending || !label.trim()}
              aria-label={t('branches.add_one')}
            >
              <Plus />
            </Button>
          </form>
          {errors.label && (
            <p className='text-sm text-destructive'>{errors.label}</p>
          )}

          <div className='grid gap-2'>
            <div className='flex items-center justify-between gap-2'>
              <p className='text-sm font-medium'>
                {t('branches.tables_count', { count })}
              </p>
              {count > 0 && (
                <Button asChild size='sm'>
                  <a
                    href={downloadUrl(
                      tenantId,
                      `branches/${branch.id}/table-cards.pdf`
                    )}
                    download
                  >
                    <Printer /> {t('branches.print_cards')}
                  </a>
                </Button>
              )}
            </div>
            {tables.isPending ? (
              <Skeleton className='h-24' />
            ) : (
              <ul className='divide-y rounded-md border'>
                {tables.data?.map((table) => (
                  <li
                    key={table.id}
                    className='flex items-center gap-2 px-3 py-1.5 text-sm'
                  >
                    <span className='flex-1 font-medium'>{table.label}</span>
                    <span className='font-mono text-xs text-muted-foreground'>
                      {table.qr_code}
                    </span>
                    {table.qr_id && (
                      <Button
                        asChild
                        variant='ghost'
                        size='icon'
                        className='size-9'
                      >
                        <a
                          href={downloadUrl(
                            tenantId,
                            `qr-codes/${table.qr_id}.pdf`
                          )}
                          download
                          aria-label={t('branches.download_card', {
                            label: table.label,
                          })}
                        >
                          <Download />
                        </a>
                      </Button>
                    )}
                    <Button
                      variant='ghost'
                      size='icon'
                      className='size-9'
                      aria-label={t('branches.delete_table', {
                        label: table.label,
                      })}
                      onClick={() => setDeleting(table)}
                    >
                      <Trash2 />
                    </Button>
                  </li>
                ))}
                {count === 0 && (
                  <li className='px-3 py-4 text-center text-sm text-muted-foreground'>
                    {t('branches.no_tables')}
                  </li>
                )}
              </ul>
            )}
          </div>
        </div>
        <ConfirmDialog
          open={deleting !== null}
          onOpenChange={(open) => !open && setDeleting(null)}
          title={t('branches.delete_table_title', {
            label: deleting?.label ?? '',
          })}
          desc={t('branches.delete_table_desc')}
          destructive
          confirmText={t('branches.delete')}
          isLoading={remove.isPending}
          handleConfirm={async () => {
            if (deleting) await remove.mutateAsync(deleting.id)
            setDeleting(null)
          }}
        />
      </SheetContent>
    </Sheet>
  )
}
