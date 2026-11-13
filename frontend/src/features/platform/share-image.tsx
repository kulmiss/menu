import { useRef } from 'react'
import { Loader2, Trash2, Upload } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { send, usePlatformMutation } from './api'

/**
 * Platform settings → "Link preview": the picture chat apps show when a menu
 * without a banner is shared. Cropped to 1200 × 630 on the server.
 */
export function ShareImageCard({
  url,
  custom,
}: {
  url: string
  custom: boolean
}) {
  const { t } = useTranslation()
  const file = useRef<HTMLInputElement>(null)
  const upload = usePlatformMutation((f: File) => {
    const form = new FormData()
    form.append('image', f)
    return send('post', 'settings/share-image', form)
  }, false)
  const remove = usePlatformMutation(
    () => send('delete', 'settings/share-image'),
    false
  )
  const busy = upload.isPending || remove.isPending

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <CardTitle>{t('platform.share.title')}</CardTitle>
        <CardDescription>{t('platform.share.hint')}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-3'>
        <img
          src={url}
          alt={t('platform.share.alt')}
          width={1200}
          height={630}
          className='aspect-[1200/630] h-auto w-full max-w-md rounded-md border bg-muted object-cover'
        />
        <p className='text-sm text-muted-foreground'>
          {custom ? t('platform.share.custom') : t('platform.share.built_in')}
        </p>
        <div className='flex flex-wrap gap-2'>
          <input
            ref={file}
            type='file'
            accept='image/jpeg,image/png,image/webp'
            className='sr-only'
            aria-label={t('platform.share.choose')}
            onChange={async (e) => {
              const f = e.target.files?.[0]
              e.target.value = ''
              if (!f) return
              try {
                await upload.mutateAsync(f)
                toast.success(t('platform.share.saved'))
              } catch {
                toast.error(t('platform.share.failed'))
              }
            }}
          />
          <Button
            type='button'
            variant='outline'
            disabled={busy}
            onClick={() => file.current?.click()}
          >
            {upload.isPending ? (
              <Loader2 className='animate-spin' />
            ) : (
              <Upload />
            )}
            {t('platform.share.upload')}
          </Button>
          {custom && (
            <Button
              type='button'
              variant='ghost'
              disabled={busy}
              onClick={async () => {
                await remove.mutateAsync(undefined)
                toast.success(t('platform.share.reset_done'))
              }}
            >
              <Trash2 /> {t('platform.share.reset')}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
