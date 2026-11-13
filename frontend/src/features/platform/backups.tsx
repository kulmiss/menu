import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  DatabaseBackup,
  Download,
  Loader2,
  Trash2,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { type BackupRow, backupsQ, send, usePlatformMutation } from './api'
import { PageTitle } from './layout'

const bytes = (n: number | null) => {
  if (!n) return '—'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)))
  return `${(n / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`
}

/** Super Admin → Backups (owner, 2026-11-13). No restore button: see docs/deploy.md. */
export function BackupsPage() {
  const { t, i18n } = useTranslation()
  const data = useQuery({ ...backupsQ(), refetchInterval: 15_000 })
  const [downloading, setDownloading] = useState<BackupRow | null>(null)
  const start = usePlatformMutation(() => send('post', 'backups'), false)
  const remove = usePlatformMutation(
    (id: number) => send('delete', `backups/${id}`),
    false
  )
  const when = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))
  const d = data.data

  return (
    <>
      <PageTitle
        title={t('platform.nav.backups')}
        hint={t('platform.backups.hint')}
      >
        <Button
          disabled={start.isPending}
          onClick={async () => {
            try {
              await start.mutateAsync(undefined)
              toast.success(t('platform.backups.started'))
            } catch {
              // the global handler shows "already running"
            }
          }}
        >
          {start.isPending ? (
            <Loader2 className='animate-spin' />
          ) : (
            <DatabaseBackup />
          )}
          {t('platform.backups.now')}
        </Button>
      </PageTitle>
      {!d ? (
        <Skeleton className='h-64' />
      ) : (
        <div className='grid gap-4'>
          {d.problem && (
            <Alert variant='destructive' role='alert'>
              <AlertTriangle />
              <AlertDescription>{d.problem}</AlertDescription>
            </Alert>
          )}
          <p className='text-sm text-muted-foreground'>
            {t('platform.backups.disk', {
              free: bytes(d.disk_free),
              total: bytes(d.disk_total),
            })}
          </p>
          <div className='overflow-x-auto rounded-md border'>
            <table className='w-full text-sm'>
              <thead className='bg-muted/50 text-xs text-muted-foreground'>
                <tr>
                  {['date', 'size', 'location', 'status', 'actions'].map(
                    (h) => (
                      <th key={h} className='p-2 text-start'>
                        {t(`platform.backups.col.${h}`)}
                      </th>
                    )
                  )}
                </tr>
              </thead>
              <tbody>
                {d.backups.map((b) => (
                  <tr key={b.id} className='border-t align-top'>
                    <td className='p-2 whitespace-nowrap'>
                      {when(b.created_at)}
                      <div className='text-xs text-muted-foreground'>
                        {t(`platform.backups.kind.${b.kind}`)}
                      </div>
                    </td>
                    <td className='p-2 tabular-nums'>{bytes(b.size)}</td>
                    <td className='p-2'>
                      {b.status === 'ok' && t('platform.backups.server')}
                      {b.offsite === 'uploaded' &&
                        ` + ${t('platform.backups.offsite')}`}
                      {b.offsite === 'failed' && (
                        <span className='text-destructive'>
                          {' '}
                          · {t('platform.backups.offsite_failed')}
                        </span>
                      )}
                    </td>
                    <td className='p-2'>
                      <Badge
                        variant={
                          b.status === 'failed'
                            ? 'destructive'
                            : b.status === 'ok'
                              ? 'secondary'
                              : 'outline'
                        }
                      >
                        {t(`platform.backups.status.${b.status}`)}
                      </Badge>
                      {b.error && (
                        <p className='mt-1 max-w-xs text-xs text-muted-foreground'>
                          {b.error}
                        </p>
                      )}
                    </td>
                    <td className='p-1'>
                      <span className='flex'>
                        {b.status === 'ok' && (
                          <Button
                            size='sm'
                            variant='ghost'
                            className='min-h-11 sm:min-h-8'
                            onClick={() => setDownloading(b)}
                          >
                            <Download className='size-4' />{' '}
                            {t('platform.backups.download')}
                          </Button>
                        )}
                        {!['queued', 'running'].includes(b.status) && (
                          <Button
                            size='sm'
                            variant='ghost'
                            className='min-h-11 text-destructive sm:min-h-8'
                            aria-label={t('platform.backups.delete')}
                            onClick={() => {
                              if (
                                window.confirm(
                                  t('platform.backups.delete_confirm')
                                )
                              )
                                remove.mutate(b.id)
                            }}
                          >
                            <Trash2 className='size-4' />
                          </Button>
                        )}
                      </span>
                    </td>
                  </tr>
                ))}
                {d.backups.length === 0 && (
                  <tr>
                    <td
                      colSpan={5}
                      className='p-6 text-center text-muted-foreground'
                    >
                      {t('platform.backups.none')}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <p className='text-sm text-muted-foreground'>
            {t('platform.backups.restore_note')}
          </p>
          <BackupSettings initial={d.settings} />
        </div>
      )}
      {downloading && (
        <DownloadDialog
          backup={downloading}
          onClose={() => setDownloading(null)}
        />
      )}
    </>
  )
}

function DownloadDialog({
  backup,
  onClose,
}: {
  backup: BackupRow
  onClose: () => void
}) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<'password' | 'code'>('password')
  const [value, setValue] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('platform.backups.confirm_title')}</DialogTitle>
          <DialogDescription>
            {t('platform.backups.confirm_hint')}
          </DialogDescription>
        </DialogHeader>
        <form
          className='grid gap-3'
          onSubmit={async (e) => {
            e.preventDefault()
            setBusy(true)
            setError(null)
            try {
              const res = await api.post<{ data: { url: string } }>(
                `/platform/backups/${backup.id}/link`,
                { [mode]: value }
              )
              window.location.assign(res.data.data.url)
              onClose()
            } catch (err) {
              const f = validationErrors(err)
              setError(f?.password ?? f?.code ?? t('errors.generic'))
            } finally {
              setBusy(false)
            }
          }}
        >
          <Label htmlFor='backup-secret'>
            {mode === 'password'
              ? t('platform.backups.password')
              : t('platform.backups.code')}
          </Label>
          <Input
            id='backup-secret'
            type={mode === 'password' ? 'password' : 'text'}
            inputMode={mode === 'code' ? 'numeric' : undefined}
            autoComplete={
              mode === 'password' ? 'current-password' : 'one-time-code'
            }
            value={value}
            aria-invalid={!!error}
            onChange={(e) => setValue(e.target.value)}
          />
          {error && <p className='text-sm text-destructive'>{error}</p>}
          <Button
            type='button'
            variant='link'
            className='min-h-11 justify-self-start px-0'
            onClick={() => {
              setMode(mode === 'password' ? 'code' : 'password')
              setValue('')
              setError(null)
            }}
          >
            {mode === 'password'
              ? t('platform.backups.use_code')
              : t('platform.backups.use_password')}
          </Button>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type='submit' disabled={!value || busy}>
              {busy ? <Loader2 className='animate-spin' /> : <Download />}
              {t('platform.backups.download')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

type Settings = {
  backup_enabled: boolean
  backup_keep_daily: number
  backup_keep_weekly: number
  backup_keep_monthly: number
  backup_s3_enabled: boolean
  backup_s3_endpoint: string
  backup_s3_region: string
  backup_s3_bucket: string
  backup_s3_prefix: string
  backup_s3_key: string
  backup_s3_secret_set: boolean
}

function BackupSettings({ initial }: { initial: Settings }) {
  const { t } = useTranslation()
  const save = usePlatformMutation((body: object) =>
    send('put', 'backups/settings', body)
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [v, setV] = useState({ ...initial, backup_s3_secret: '' })
  const num = (
    k: 'backup_keep_daily' | 'backup_keep_weekly' | 'backup_keep_monthly'
  ) => (
    <div className='grid gap-1'>
      <Label htmlFor={`bk-${k}`}>{t(`platform.backups.${k}`)}</Label>
      <Input
        id={`bk-${k}`}
        type='number'
        min={0}
        value={v[k]}
        onChange={(e) => setV({ ...v, [k]: Number(e.target.value) })}
      />
      {errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>}
    </div>
  )
  const text = (
    k:
      | 'backup_s3_endpoint'
      | 'backup_s3_region'
      | 'backup_s3_bucket'
      | 'backup_s3_prefix'
      | 'backup_s3_key',
    placeholder = ''
  ) => (
    <div className='grid gap-1'>
      <Label htmlFor={`bk-${k}`}>{t(`platform.backups.${k}`)}</Label>
      <Input
        id={`bk-${k}`}
        dir='ltr'
        autoComplete='off'
        placeholder={placeholder}
        value={v[k] ?? ''}
        onChange={(e) => setV({ ...v, [k]: e.target.value })}
      />
      {errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>}
    </div>
  )

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.backups.settings')}</CardTitle>
        <CardDescription>{t('platform.backups.settings_hint')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form
          className='grid gap-4'
          onSubmit={async (e) => {
            e.preventDefault()
            setErrors({})
            try {
              await save.mutateAsync(v)
              setV({ ...v, backup_s3_secret: '' })
              toast.success(t('platform.saved'))
            } catch (err) {
              setErrors(validationErrors(err) ?? {})
            }
          }}
        >
          <div className='flex items-center justify-between gap-4'>
            <Label htmlFor='bk-enabled'>{t('platform.backups.enabled')}</Label>
            <Switch
              id='bk-enabled'
              checked={v.backup_enabled}
              onCheckedChange={(on) => setV({ ...v, backup_enabled: on })}
            />
          </div>
          <div className='grid gap-3 sm:grid-cols-3'>
            {num('backup_keep_daily')}
            {num('backup_keep_weekly')}
            {num('backup_keep_monthly')}
          </div>
          <fieldset className='grid gap-3 rounded-md border p-3'>
            <legend className='px-1 text-sm font-medium'>
              {t('platform.backups.offsite_title')}
            </legend>
            <div className='flex items-center justify-between gap-4'>
              <Label htmlFor='bk-s3'>
                {t('platform.backups.backup_s3_enabled')}
              </Label>
              <Switch
                id='bk-s3'
                checked={v.backup_s3_enabled}
                onCheckedChange={(on) => setV({ ...v, backup_s3_enabled: on })}
              />
            </div>
            {errors.backup_s3_enabled && (
              <p className='text-sm text-destructive'>
                {errors.backup_s3_enabled}
              </p>
            )}
            <div className='grid gap-3 sm:grid-cols-2'>
              {text(
                'backup_s3_endpoint',
                'https://s3.eu-central-003.backblazeb2.com'
              )}
              {text('backup_s3_region', 'auto')}
              {text('backup_s3_bucket')}
              {text('backup_s3_prefix', 'menu-backups')}
              {text('backup_s3_key')}
              <div className='grid gap-1'>
                <Label htmlFor='bk-secret'>
                  {t('platform.backups.backup_s3_secret')}
                </Label>
                <Input
                  id='bk-secret'
                  type='password'
                  autoComplete='new-password'
                  placeholder={
                    initial.backup_s3_secret_set
                      ? t('platform.signup.secret_saved')
                      : ''
                  }
                  value={v.backup_s3_secret}
                  onChange={(e) =>
                    setV({ ...v, backup_s3_secret: e.target.value })
                  }
                />
              </div>
            </div>
            <p className='text-sm text-muted-foreground'>
              {t('platform.backups.offsite_hint')}
            </p>
          </fieldset>
          <Button
            type='submit'
            className='min-h-11 justify-self-start sm:min-h-9'
            disabled={save.isPending}
          >
            {save.isPending && <Loader2 className='animate-spin' />}
            {t('common.save')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
