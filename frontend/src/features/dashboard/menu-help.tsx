import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileImage, Loader2, Send, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { fitWithin, isHeic } from '@/features/menu/photo'

type MenuRequestRow = {
  id: number
  status: 'new' | 'working' | 'done'
  files: number
  note: string | null
  created_at: string
}

const MAX_FILES = 10
const ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf'

/** Photos are made smaller in the browser (the server needs no 12 MB phone pictures); PDFs go as they are. */
async function prepare(file: File): Promise<File> {
  if (file.type === 'application/pdf') return file
  try {
    const bitmap = await createImageBitmap(file)
    const { width, height } = fitWithin(bitmap.width, bitmap.height)
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    canvas.getContext('2d')!.drawImage(bitmap, 0, 0, width, height)
    bitmap.close()
    const blob = await new Promise<Blob | null>((r) =>
      canvas.toBlob(r, 'image/jpeg', 0.85)
    )
    if (!blob) return file
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
      type: 'image/jpeg',
    })
  } catch {
    return file // the server checks it anyway
  }
}

/**
 * "Add my menu for me" (QUESTIONS #235): the owner sends photos or a PDF of the
 * paper menu; the Super Admin types it in and tells them when it is done.
 */
export default function MenuHelp({ tenantId }: { tenantId: number }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const key = ['tenant', tenantId, 'menu-requests']
  const data = useQuery({
    queryKey: key,
    queryFn: async () =>
      (
        await api.get<{
          data: { enabled: boolean; requests: MenuRequestRow[] }
        }>(tenantUrl(tenantId, 'menu-requests'))
      ).data.data,
  })
  const [open, setOpen] = useState(false)
  const [files, setFiles] = useState<File[]>([])
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const input = useRef<HTMLInputElement>(null)
  const send = useMutation({
    mutationFn: async () => {
      const form = new FormData()
      for (const f of await Promise.all(files.map(prepare)))
        form.append('files[]', f)
      if (note.trim()) form.append('note', note.trim())
      return api.post(tenantUrl(tenantId, 'menu-requests'), form)
    },
    onSuccess: () => {
      setFiles([])
      setNote('')
      setOpen(false)
      toast.success(t('menu_help.sent'))
      void qc.invalidateQueries({ queryKey: key })
    },
    onError: (err) => {
      const found = validationErrors(err)
      setError(found ? Object.values(found)[0] : t('menu_help.failed'))
    },
  })

  const d = data.data
  if (!d?.enabled) return null
  const latest = d.requests[0]
  // Done and nothing else open: the card has done its job.
  if (latest?.status === 'done' && !open) return null

  return (
    <Card className='mt-4'>
      <CardHeader>
        <CardTitle>{t('menu_help.title')}</CardTitle>
        <CardDescription>{t('menu_help.hint')}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {latest && latest.status !== 'done' && !open ? (
          <p className='text-sm' role='status'>
            {t(`menu_help.status_${latest.status}`, { count: latest.files })}
          </p>
        ) : null}
        {!open ? (
          <Button
            className='min-h-11 justify-self-start sm:min-h-9'
            variant={latest ? 'outline' : 'default'}
            onClick={() => setOpen(true)}
          >
            <FileImage aria-hidden /> {t('menu_help.start')}
          </Button>
        ) : (
          <form
            className='grid gap-3'
            onSubmit={(e) => {
              e.preventDefault()
              setError(null)
              if (files.length === 0) return setError(t('menu_help.none'))
              send.mutate()
            }}
          >
            <div className='grid gap-1'>
              <Label htmlFor='mh-files'>{t('menu_help.files')}</Label>
              <input
                ref={input}
                id='mh-files'
                type='file'
                multiple
                accept={ACCEPT}
                className='text-sm file:mr-3 file:min-h-11 file:rounded-md file:border file:bg-background file:px-3 sm:file:min-h-9'
                onChange={(e) => {
                  const picked = [...(e.target.files ?? [])]
                  if (picked.some(isHeic)) setError(t('menu_help.heic'))
                  else setError(null)
                  setFiles((f) =>
                    [...f, ...picked.filter((p) => !isHeic(p))].slice(
                      0,
                      MAX_FILES
                    )
                  )
                  e.target.value = ''
                }}
              />
              <p className='text-sm text-muted-foreground'>
                {t('menu_help.files_hint', { max: MAX_FILES })}
              </p>
            </div>
            {files.length > 0 && (
              <ul className='grid gap-1'>
                {files.map((f, i) => (
                  <li
                    key={`${f.name}-${i}`}
                    className='flex min-h-11 items-center justify-between gap-2 rounded-md border px-3 text-sm sm:min-h-9'
                  >
                    <span className='truncate'>{f.name}</span>
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      className='size-11 sm:size-9'
                      aria-label={t('menu_help.remove', { name: f.name })}
                      onClick={() =>
                        setFiles((all) => all.filter((_, j) => j !== i))
                      }
                    >
                      <X aria-hidden />
                    </Button>
                  </li>
                ))}
              </ul>
            )}
            <div className='grid gap-1'>
              <Label htmlFor='mh-note'>{t('menu_help.note')}</Label>
              <Textarea
                id='mh-note'
                maxLength={1000}
                value={note}
                placeholder={t('menu_help.note_placeholder')}
                onChange={(e) => setNote(e.target.value)}
              />
            </div>
            {error && (
              <p className='text-sm text-destructive' role='alert'>
                {error}
              </p>
            )}
            <div className='flex flex-wrap gap-2'>
              <Button
                type='submit'
                className='min-h-11 sm:min-h-9'
                disabled={send.isPending}
              >
                {send.isPending ? (
                  <Loader2 className='animate-spin' />
                ) : (
                  <Send aria-hidden />
                )}
                {t('menu_help.send', { count: files.length })}
              </Button>
              <Button
                type='button'
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                onClick={() => {
                  setOpen(false)
                  setFiles([])
                  setError(null)
                }}
              >
                {t('common.cancel')}
              </Button>
            </div>
          </form>
        )}
      </CardContent>
    </Card>
  )
}
