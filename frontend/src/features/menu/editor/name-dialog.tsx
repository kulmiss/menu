import { useState } from 'react'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { PhotoField } from '../components/photo-field'
import { TranslatableInput } from '../components/translatable-input'
import { type Media, type Translations } from '../types'

export type NameValues = {
  name: Translations
  description?: Translations
  is_visible: boolean
  media?: Media | null
}

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  locale: string
  initial: NameValues
  /** Categories have a description; menus don't. */
  withDescription?: boolean
  /** Needed for the category photo upload. */
  tenantId?: number
  onSave: (values: NameValues) => Promise<unknown>
}

/** Create or rename a menu or category. */
export function NameDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        {/* Mounted on open, so every opening starts from `initial`. */}
        <NameForm {...props} />
      </DialogContent>
    </Dialog>
  )
}

function NameForm({
  tenantId,
  onOpenChange,
  title,
  locale,
  initial,
  withDescription,
  onSave,
}: Props) {
  const { t } = useTranslation()
  const [values, setValues] = useState(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    if (!values.name[locale]?.trim()) {
      setErrors({ name: t('validation.menu_name') })
      return
    }
    setPending(true)
    try {
      await onSave(values)
      onOpenChange(false)
    } catch (error) {
      // Other failures are already shown as a toast (see main.tsx).
      const fields = validationErrors(error)
      if (fields) setErrors(fields)
    } finally {
      setPending(false)
    }
  }

  return (
    <form onSubmit={submit} className='grid gap-4'>
      <DialogHeader className='text-start'>
        <DialogTitle>{title}</DialogTitle>
      </DialogHeader>
      <TranslatableInput
        label={t('menu.name')}
        locale={locale}
        value={values.name}
        onChange={(name) => setValues((v) => ({ ...v, name }))}
        maxLength={80}
        error={errors.name}
        autoFocus
      />
      {withDescription && tenantId !== undefined && (
        <PhotoField
          tenantId={tenantId}
          label={t('photo.label')}
          aspect={16 / 9}
          media={values.media ?? null}
          onChange={(media) => setValues((v) => ({ ...v, media }))}
        />
      )}
      {withDescription && (
        <TranslatableInput
          label={t('menu.description')}
          locale={locale}
          value={values.description ?? {}}
          onChange={(description) => setValues((v) => ({ ...v, description }))}
          maxLength={500}
          multiline
          error={errors.description}
        />
      )}
      <div className='flex items-center justify-between gap-4'>
        <Label htmlFor='name-dialog-visible'>
          {t('menu.visible_to_guests')}
        </Label>
        <Switch
          id='name-dialog-visible'
          checked={values.is_visible}
          onCheckedChange={(is_visible) =>
            setValues((v) => ({ ...v, is_visible }))
          }
        />
      </div>
      <DialogFooter>
        <Button
          type='submit'
          className='min-h-11 sm:min-h-9'
          disabled={pending}
        >
          {pending && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </DialogFooter>
    </form>
  )
}
