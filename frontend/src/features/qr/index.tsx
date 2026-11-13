import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Copy, Loader2, MapPin, Plus, Printer, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { DataTable } from '@/components/data-table'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import {
  type QrCodeRow,
  branchesQuery,
  downloadUrl,
  qrCodesQuery,
  useAddQrCode,
  useDeleteQrCode,
} from '@/features/branches/api'
import { tr } from '@/features/menu/text'
import { useMenuContext } from '@/features/menu/use-menu-context'

/** QR codes (PLAN.md screen 8): every code, downloads, table cards. */
export function QrCodesPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const codes = useQuery(qrCodesQuery(ctx.tenantId))
  const branches = useQuery(branchesQuery(ctx.tenantId))
  const add = useAddQrCode(ctx.tenantId)
  const remove = useDeleteQrCode(ctx.tenantId)
  const [label, setLabel] = useState('')
  const [branchId, setBranchId] = useState('all')
  const [error, setError] = useState<string | null>(null)
  const [deleting, setDeleting] = useState<QrCodeRow | null>(null)
  const canEdit = ctx.canManage && ctx.canWrite

  const tableCount = (id: number) =>
    codes.data?.filter((c) => c.kind === 'table' && c.branch_id === id)
      .length ?? 0

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await add.mutateAsync({
        label: label.trim(),
        branch_id: branchId === 'all' ? null : Number(branchId),
      })
      setLabel('')
    } catch (err) {
      const fields = validationErrors(err)
      setError(fields ? Object.values(fields)[0] : null)
    }
  }

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-4 flex flex-wrap items-start justify-between gap-3'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('qr.title')}
            </h1>
            <p className='text-muted-foreground'>{t('qr.description')}</p>
          </div>
          <Button asChild className='min-h-11'>
            <Link
              to='/admin/print-materials'
              title={t('qr.print_materials_hint')}
            >
              <Printer /> {t('qr.print_materials')}
            </Link>
          </Button>
        </div>
        <div className='grid gap-4'>
          <DataTable<QrCodeRow>
            id='qr-codes'
            rows={codes.data}
            loading={codes.isPending}
            error={codes.isError}
            onRetry={() => codes.refetch()}
            rowId={(c) => c.id}
            csvName='qr-codes'
            searchPlaceholder={t('qr.search')}
            filters={[
              {
                id: 'kind',
                label: t('qr.kind'),
                options: (['restaurant', 'branch', 'table', 'extra'] as const)
                  .filter((k) => codes.data?.some((c) => c.kind === k))
                  .map((k) => ({ value: k, label: t(`qr.kinds.${k}`) })),
                match: (c, v) => v.includes(c.kind),
              },
              ...((branches.data?.length ?? 0) > 1
                ? [
                    {
                      id: 'branch',
                      label: t('qr.branch'),
                      options: (branches.data ?? []).map((b) => ({
                        value: tr(b.name, ctx.locale),
                        label: tr(b.name, ctx.locale),
                      })),
                      match: (c: QrCodeRow, v: string[]) =>
                        v.includes(c.branch),
                    },
                  ]
                : []),
            ]}
            bulk={(rows) => (
              <Button asChild size='sm' className='min-h-11 sm:min-h-8'>
                <Link
                  to='/admin/print-materials'
                  search={{ codes: rows.map((r) => r.id).join(',') }}
                >
                  <Printer aria-hidden /> {t('qr.print_selected')}
                </Link>
              </Button>
            )}
            columns={[
              {
                id: 'code',
                label: t('qr.code'),
                primary: true,
                text: (c) => c.description,
                sortValue: (c) => c.description.toLowerCase(),
                cell: (c) => (
                  <span className='flex min-w-0 items-center gap-3'>
                    <img
                      src={downloadUrl(ctx.tenantId, `qr-codes/${c.id}.svg`)}
                      alt=''
                      width={48}
                      height={48}
                      loading='lazy'
                      className='size-12 flex-none rounded border bg-white'
                    />
                    <span className='font-medium'>{c.description}</span>
                  </span>
                ),
              },
              {
                id: 'kind',
                label: t('qr.kind'),
                text: (c) => t(`qr.kinds.${c.kind}`),
                sortValue: (c) => c.kind,
                cell: (c) => (
                  <Badge variant='outline'>{t(`qr.kinds.${c.kind}`)}</Badge>
                ),
              },
              {
                id: 'branch',
                label: t('qr.branch'),
                hidden: (branches.data?.length ?? 0) <= 1,
                text: (c) => c.branch,
                sortValue: (c) => c.branch,
                cell: (c) => (
                  <span className='inline-flex items-center gap-1'>
                    <MapPin className='size-3.5' aria-hidden /> {c.branch}
                  </span>
                ),
              },
              {
                id: 'url',
                label: t('qr.address'),
                text: (c) => c.url,
                cell: (c) => (
                  <button
                    type='button'
                    className='inline-flex min-h-11 items-center gap-1 text-start font-mono text-xs text-muted-foreground hover:text-foreground sm:min-h-8'
                    onClick={async (e) => {
                      e.stopPropagation()
                      await navigator.clipboard?.writeText(c.url)
                      toast.success(t('qr.copied'))
                    }}
                  >
                    {c.url.replace(/^https?:\/\//, '')}{' '}
                    <Copy className='size-3 flex-none' aria-hidden />
                  </button>
                ),
              },
              {
                id: 'files',
                label: t('qr.files'),
                cell: (code) => (
                  <span className='flex flex-wrap gap-1'>
                    {(['pdf', 'png', 'svg'] as const).map((f) => (
                      <Button
                        key={f}
                        asChild
                        size='sm'
                        variant={f === 'pdf' ? 'default' : 'outline'}
                        className='min-h-11 sm:min-h-8'
                      >
                        <a
                          href={downloadUrl(
                            ctx.tenantId,
                            `qr-codes/${code.id}.${f}`
                          )}
                          download
                          aria-label={`${f.toUpperCase()} · ${code.description}`}
                        >
                          {f.toUpperCase()}
                        </a>
                      </Button>
                    ))}
                    {code.kind === 'extra' && canEdit && (
                      <Button
                        size='sm'
                        variant='ghost'
                        className='min-h-11 sm:min-h-8'
                        aria-label={t('qr.delete', { name: code.description })}
                        onClick={() => setDeleting(code)}
                      >
                        <Trash2 />
                      </Button>
                    )}
                  </span>
                ),
              },
            ]}
          />

          <Card>
            <CardHeader>
              <CardTitle>{t('qr.tables')}</CardTitle>
              <CardDescription>{t('qr.tables_hint')}</CardDescription>
            </CardHeader>
            <CardContent className='grid gap-2'>
              {branches.data?.map((b) => (
                <div
                  key={b.id}
                  className='flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0'
                >
                  <span className='text-sm'>
                    <span className='font-medium'>
                      {tr(b.name, ctx.locale)}
                    </span>{' '}
                    <span className='text-muted-foreground'>
                      {t('branches.tables_count', { count: tableCount(b.id) })}
                    </span>
                  </span>
                  {tableCount(b.id) > 0 && (
                    <Button asChild size='sm' variant='outline'>
                      <a
                        href={downloadUrl(
                          ctx.tenantId,
                          `branches/${b.id}/table-cards.pdf`
                        )}
                        download
                      >
                        <Printer /> {t('branches.print_cards')}
                      </a>
                    </Button>
                  )}
                </div>
              ))}
            </CardContent>
          </Card>

          {canEdit && (
            <Card>
              <CardHeader>
                <CardTitle>{t('qr.extra')}</CardTitle>
                <CardDescription>{t('qr.extra_hint')}</CardDescription>
              </CardHeader>
              <CardContent>
                <form
                  onSubmit={submit}
                  className='flex flex-wrap items-end gap-2'
                >
                  <div className='grid min-w-48 flex-1 gap-1'>
                    <Label htmlFor='qr-label'>{t('qr.label')}</Label>
                    <Input
                      id='qr-label'
                      value={label}
                      maxLength={60}
                      placeholder={t('qr.label_placeholder')}
                      onChange={(e) => setLabel(e.target.value)}
                    />
                  </div>
                  {(branches.data?.length ?? 0) > 1 && (
                    <div className='grid gap-1'>
                      <Label>{t('qr.opens')}</Label>
                      <Select value={branchId} onValueChange={setBranchId}>
                        <SelectTrigger
                          className='w-48'
                          aria-label={t('qr.opens')}
                        >
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value='all'>
                            {t('qr.main_branch')}
                          </SelectItem>
                          {branches.data?.map((b) => (
                            <SelectItem key={b.id} value={String(b.id)}>
                              {tr(b.name, ctx.locale)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                  <Button
                    type='submit'
                    disabled={add.isPending || !label.trim()}
                  >
                    {add.isPending ? (
                      <Loader2 className='animate-spin' />
                    ) : (
                      <Plus />
                    )}
                    {t('qr.add')}
                  </Button>
                </form>
                {error && (
                  <p className='mt-2 text-sm text-destructive'>{error}</p>
                )}
              </CardContent>
            </Card>
          )}
          <p className='text-sm text-muted-foreground'>{t('qr.print_tip')}</p>
        </div>
      </Main>
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={t('qr.delete_title')}
        desc={t('qr.delete_desc')}
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
