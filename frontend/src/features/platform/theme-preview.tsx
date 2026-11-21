import { useState } from 'react'
import { Eye, Image as ImageIcon, ImageOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useMediaQuery } from '@/hooks/use-mobile'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  DIRECTION_STYLES,
  type MenuStyle,
  type PreviewScheme,
} from '@/features/appearance/api'
import {
  PhonePreview,
  PreviewSwitch,
} from '@/features/appearance/phone-preview'
import { usePreviewSrc } from '@/features/appearance/use-preview-src'
import { themePreviewUrl } from './api'

type ThemeValues = { style: string; primary: string; font: string }
/** Changes that reload the phone at once; a typed colour waits for a pause. */
const INSTANT = ['style', 'font', 'scheme', 'dir', 'photos']

/**
 * The theme being made, on a demo menu in the owner's phone frame (owner,
 * 2026-11-21): beside the form on a computer (sticky), behind a "Preview"
 * button full screen on a phone or tablet. Nothing is saved.
 */
export function ThemePreview({ values }: { values: ThemeValues }) {
  const { t } = useTranslation()
  const wide = useMediaQuery('(min-width: 1024px)')
  const [open, setOpen] = useState(false)
  if (wide) {
    return (
      <aside
        aria-label={t('platform.themes.preview_title')}
        className='sticky top-20'
      >
        {/* Short screens: the phone shrinks so its bottom stays in view. */}
        <PreviewBody
          values={values}
          frameClassName='h-[min(640px,calc(100dvh-19rem))] min-h-80'
        />
      </aside>
    )
  }
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button type='button' variant='outline' className='min-h-11 w-full'>
          <Eye aria-hidden /> {t('platform.themes.preview')}
        </Button>
      </DialogTrigger>
      <DialogContent className='flex h-dvh max-h-dvh w-screen max-w-none flex-col gap-3 overflow-y-auto rounded-none p-4 sm:max-w-none'>
        <DialogHeader>
          <DialogTitle>{t('platform.themes.preview_title')}</DialogTitle>
          <DialogDescription>
            {t('platform.themes.preview_hint')}
          </DialogDescription>
        </DialogHeader>
        {open && (
          <PreviewBody
            values={values}
            frameClassName='h-[min(640px,calc(100dvh-14rem))]'
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

function PreviewBody({
  values,
  frameClassName,
}: {
  values: ThemeValues
  frameClassName?: string
}) {
  const { t } = useTranslation()
  const [scheme, setScheme] = useState<PreviewScheme>('light')
  const [dir, setDir] = useState<'ltr' | 'rtl'>('ltr')
  const [photos, setPhotos] = useState(true)
  // Right to left exists only in the styles with "Menu direction" (H and M–T).
  const rtlOk = DIRECTION_STYLES.includes(values.style as MenuStyle)
  const shownDir = rtlOk ? dir : 'ltr'
  const preview = usePreviewSrc(
    themePreviewUrl(values, { scheme, dir: shownDir, photos }),
    INSTANT
  )
  return (
    <PhonePreview
      {...preview}
      scheme={scheme}
      onScheme={setScheme}
      title={t('platform.themes.preview_title')}
      hint={
        rtlOk
          ? t('platform.themes.preview_hint')
          : `${t('platform.themes.preview_hint')} ${t('platform.themes.rtl_off')}`
      }
      frameClassName={frameClassName}
      controls={
        <>
          <PreviewSwitch
            label={t('platform.themes.dir_label')}
            value={shownDir}
            onChange={setDir}
            options={[
              { value: 'ltr', label: t('platform.themes.ltr') },
              {
                value: 'rtl',
                label: t('platform.themes.rtl'),
                disabled: !rtlOk,
                title: rtlOk ? undefined : t('platform.themes.rtl_off'),
              },
            ]}
          />
          <PreviewSwitch
            label={t('platform.themes.photos_label')}
            value={photos ? 'on' : 'off'}
            onChange={(v) => setPhotos(v === 'on')}
            options={[
              {
                value: 'on',
                label: t('platform.themes.photos_on'),
                icon: <ImageIcon className='size-4' aria-hidden />,
              },
              {
                value: 'off',
                label: t('platform.themes.photos_off'),
                icon: <ImageOff className='size-4' aria-hidden />,
              },
            ]}
          />
        </>
      }
    />
  )
}
