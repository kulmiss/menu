import { useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Download, FileSpreadsheet, Loader2, Upload } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { validationErrors } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { menuKeys } from '../api'
import { useMenuContext } from '../use-menu-context'
import {
  commitImport,
  downloadSheet,
  type ImportAction,
  type ImportPreview,
  previewImport,
} from './api'

const ACTION_STYLE: Record<ImportAction, string> = {
  create:
    'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
  update: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100',
  unchanged: 'bg-muted text-muted-foreground',
  error: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100',
}

/** Import from a spreadsheet (preview first) and export (PLAN.md §7.2.5). */
export function ImportExportPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const qc = useQueryClient()
  const input = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const [onlyProblems, setOnlyProblems] = useState(false)
  const [downloading, setDownloading] = useState<string | null>(null)

  const upload = useMutation({
    mutationFn: (file: File) => previewImport(ctx.tenantId, file),
    meta: { inlineErrors: true },
    onMutate: () => {
      setFileError(null)
      setPreview(null)
    },
    onSuccess: (p) => {
      setPreview(p)
      setOnlyProblems(p.summary.errors > 0)
    },
    onError: (error) => setFileError(validationErrors(error)?.file ?? null),
    onSettled: () => {
      if (input.current) input.current.value = ''
    },
  })

  const commit = useMutation({
    mutationFn: (token: string) => commitImport(ctx.tenantId, token),
    meta: { inlineErrors: true },
    onSuccess: (s) => {
      toast.success(t('import.done', { created: s.create, updated: s.update }))
      setPreview(null)
      void qc.invalidateQueries({ queryKey: menuKeys.all(ctx.tenantId) })
    },
    onError: (error) => setFileError(validationErrors(error)?.token ?? null),
  })

  async function download(kind: 'export' | 'template', format: 'csv' | 'xlsx') {
    setDownloading(`${kind}-${format}`)
    try {
      await downloadSheet(ctx.tenantId, kind, format)
    } finally {
      setDownloading(null)
    }
  }

  const downloadButton = (
    kind: 'export' | 'template',
    format: 'csv' | 'xlsx'
  ) => (
    <Button
      variant='outline'
      className='min-h-11 sm:min-h-9'
      disabled={downloading !== null}
      onClick={() => download(kind, format)}
    >
      {downloading === `${kind}-${format}` ? (
        <Loader2 className='animate-spin' />
      ) : (
        <Download />
      )}
      {format === 'xlsx' ? t('import.excel') : t('import.csv')}
    </Button>
  )

  const rows =
    preview?.rows.filter((r) => !onlyProblems || r.action === 'error') ?? []
  const s = preview?.summary

  return (
    <>
      <AdminHeader />
      <Main>
        <h1 className='text-2xl font-bold tracking-tight'>
          {t('import.title')}
        </h1>
        <p className='mb-6 text-muted-foreground'>{t('import.intro')}</p>

        <div className='grid gap-6 lg:grid-cols-2'>
          <Card>
            <CardHeader>
              <CardTitle>{t('import.export_title')}</CardTitle>
              <CardDescription>{t('import.export_intro')}</CardDescription>
            </CardHeader>
            <CardContent className='flex flex-wrap gap-2'>
              {downloadButton('export', 'xlsx')}
              {downloadButton('export', 'csv')}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t('import.import_title')}</CardTitle>
              <CardDescription>{t('import.import_intro')}</CardDescription>
            </CardHeader>
            <CardContent className='grid gap-4'>
              <div className='flex flex-wrap items-center gap-2 text-sm'>
                <span className='text-muted-foreground'>
                  {t('import.template')}
                </span>
                {downloadButton('template', 'xlsx')}
                {downloadButton('template', 'csv')}
              </div>
              <details className='text-sm text-muted-foreground'>
                <summary className='min-h-11 cursor-pointer py-2 sm:min-h-0'>
                  {t('import.how')}
                </summary>
                <ul className='mt-1 list-disc space-y-1 ps-5'>
                  <li>
                    {t('import.how_match', {
                      language: ctx.locale.toUpperCase(),
                    })}
                  </li>
                  <li>{t('import.how_blank')}</li>
                  <li>{t('import.how_sizes')}</li>
                  <li>{t('import.how_lists')}</li>
                </ul>
              </details>
              <div>
                <Button
                  className='min-h-11 sm:min-h-9'
                  disabled={upload.isPending || !ctx.canWrite}
                  onClick={() => input.current?.click()}
                >
                  {upload.isPending ? (
                    <Loader2 className='animate-spin' />
                  ) : (
                    <Upload />
                  )}
                  {t('import.choose_file')}
                </Button>
                <input
                  ref={input}
                  type='file'
                  accept='.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                  className='sr-only'
                  tabIndex={-1}
                  aria-label={t('import.choose_file')}
                  onChange={(e) => {
                    const file = e.target.files?.[0]
                    if (file) upload.mutate(file)
                  }}
                />
              </div>
              {fileError && (
                <Alert variant='destructive' role='alert'>
                  <AlertDescription>{fileError}</AlertDescription>
                </Alert>
              )}
            </CardContent>
          </Card>
        </div>

        {preview && s && (
          <section aria-labelledby='import-preview' className='mt-6 grid gap-4'>
            <h2 id='import-preview' className='text-lg font-semibold'>
              {t('import.preview')}
            </h2>
            <dl className='grid grid-cols-2 gap-2 sm:grid-cols-4'>
              {(['create', 'update', 'unchanged', 'error'] as const).map(
                (a) => (
                  <div
                    key={a}
                    className={cn('rounded-md px-3 py-2', ACTION_STYLE[a])}
                  >
                    <dt className='text-xs'>{t(`import.action_${a}`)}</dt>
                    <dd className='text-2xl font-semibold tabular-nums'>
                      {a === 'error' ? s.errors : s[a]}
                    </dd>
                  </div>
                )
              )}
            </dl>
            {(s.new_menus > 0 || s.new_categories > 0 || s.new_tags > 0) && (
              <p className='text-sm text-muted-foreground'>
                {t('import.new_parents', {
                  menus: s.new_menus,
                  categories: s.new_categories,
                  tags: s.new_tags,
                })}
              </p>
            )}

            <label className='flex min-h-11 items-center gap-2 text-sm'>
              <Switch
                checked={onlyProblems}
                onCheckedChange={setOnlyProblems}
              />
              {t('import.only_problems')}
            </label>

            <ul className='divide-y rounded-md border'>
              {rows.length === 0 && (
                <li className='p-3 text-sm text-muted-foreground'>
                  {t('import.nothing')}
                </li>
              )}
              {rows.slice(0, 500).map((r) => (
                <li key={r.line} className='grid gap-1 p-3 text-sm'>
                  <div className='flex flex-wrap items-center gap-2'>
                    <Badge className={cn('border-0', ACTION_STYLE[r.action])}>
                      {t(`import.action_${r.action}`)}
                    </Badge>
                    <span className='text-xs text-muted-foreground tabular-nums'>
                      {t('import.line', { line: r.line })}
                    </span>
                    <span className='min-w-0 font-medium break-words'>
                      {[r.menu, r.category, r.item].filter(Boolean).join(' › ')}
                    </span>
                  </div>
                  {r.changes.length > 0 && (
                    <p className='text-muted-foreground'>
                      {t('import.changes', {
                        fields: r.changes
                          .map((c) => t(`import.col_${c}`))
                          .join(', '),
                      })}
                    </p>
                  )}
                  {r.errors.map((e, i) => (
                    <p key={i} className='text-destructive'>
                      {e}
                    </p>
                  ))}
                </li>
              ))}
            </ul>
            {rows.length > 500 && (
              <p className='text-sm text-muted-foreground'>
                {t('import.more_rows', { count: rows.length - 500 })}
              </p>
            )}

            <div className='sticky bottom-0 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur'>
              <p className='me-auto text-sm'>
                {s.errors > 0
                  ? t('import.fix_first', { count: s.errors })
                  : t('import.ready', { created: s.create, updated: s.update })}
              </p>
              <Button
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                onClick={() => setPreview(null)}
              >
                {t('common.cancel')}
              </Button>
              <Button
                className='min-h-11 sm:min-h-9'
                disabled={
                  s.errors > 0 ||
                  s.create + s.update === 0 ||
                  commit.isPending ||
                  !ctx.canWrite
                }
                onClick={() => commit.mutate(preview.token)}
              >
                {commit.isPending ? (
                  <Loader2 className='animate-spin' />
                ) : (
                  <FileSpreadsheet />
                )}
                {t('import.confirm')}
              </Button>
            </div>
          </section>
        )}
      </Main>
    </>
  )
}
