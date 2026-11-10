import { useState } from 'react'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { slugify } from '@/lib/slug'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { tr, withText } from '@/features/menu/text'
import { type Menu } from '@/features/menu/types'
import { type Branch, useMenuBranches, useSaveBranch } from './api'

type Props = {
  tenantId: number
  locale: string
  branch: Branch | null
  branches: Branch[]
  menus: Menu[]
  onClose: () => void
}

/** Create or edit a branch, and choose which menus it shows. */
export function BranchDialog(props: Props) {
  return (
    <Dialog open onOpenChange={(open) => !open && props.onClose()}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-lg'>
        <BranchForm {...props} />
      </DialogContent>
    </Dialog>
  )
}

function BranchForm({
  tenantId,
  locale,
  branch,
  branches,
  menus,
  onClose,
}: Props) {
  const { t } = useTranslation()
  const save = useSaveBranch(tenantId)
  const menuBranches = useMenuBranches(tenantId)
  const [name, setName] = useState(branch ? tr(branch.name, locale) : '')
  const [slug, setSlug] = useState(branch?.slug ?? '')
  const [slugTouched, setSlugTouched] = useState(!!branch)
  const [address, setAddress] = useState(
    branch ? tr(branch.address, locale) : ''
  )
  const [phone, setPhone] = useState(branch?.phone ?? '')
  const [maps, setMaps] = useState(branch?.maps_url ?? '')
  const [active, setActive] = useState(branch?.is_active ?? true)
  const [isDefault, setIsDefault] = useState(branch?.is_default ?? false)
  const shownAt = (m: Menu) =>
    !m.branch_ids?.length || (!!branch && m.branch_ids.includes(branch.id))
  const [shown, setShown] = useState<Record<number, boolean>>(() =>
    Object.fromEntries(menus.map((m) => [m.id, branch ? shownAt(m) : true]))
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const busy = save.isPending || menuBranches.isPending

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      const saved = await save.mutateAsync({
        id: branch?.id,
        name: withText(branch?.name, locale, name.trim()),
        slug,
        address: withText(branch?.address, locale, address.trim()),
        phone: phone.trim() || null,
        maps_url: maps.trim() || null,
        is_active: active,
        ...(isDefault && !branch?.is_default ? { is_default: true } : {}),
      })
      // Menus limited per branch: [] means "every branch" (QUESTIONS #59).
      const all = [...branches.map((b) => b.id), ...(branch ? [] : [saved.id])]
      for (const m of menus) {
        const before = shownAt(m)
        if (before === shown[m.id] && branch) continue
        const current = m.branch_ids?.length ? m.branch_ids : all
        const next = shown[m.id]
          ? [...new Set([...current, saved.id])]
          : current.filter((id) => id !== saved.id)
        const ids = next.length === all.length ? [] : next
        if (JSON.stringify(ids) !== JSON.stringify(m.branch_ids ?? [])) {
          await menuBranches.mutateAsync({ menu: m.id, ids })
        }
      }
      onClose()
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const field = (key: string) =>
    errors[key] ? (
      <p className='text-sm text-destructive'>{errors[key]}</p>
    ) : null

  return (
    <form onSubmit={submit} className='grid gap-4'>
      <DialogHeader>
        <DialogTitle>
          {branch ? t('branches.edit') : t('branches.new')}
        </DialogTitle>
      </DialogHeader>
      <div className='grid gap-2'>
        <Label htmlFor='b-name'>{t('branches.name')}</Label>
        <Input
          id='b-name'
          value={name}
          maxLength={80}
          autoFocus
          onChange={(e) => {
            setName(e.target.value)
            if (!slugTouched) setSlug(slugify(e.target.value).slice(0, 40))
          }}
        />
        {field('name')}
      </div>
      <div className='grid gap-2'>
        <Label htmlFor='b-slug'>{t('branches.slug')}</Label>
        <Input
          id='b-slug'
          value={slug}
          maxLength={40}
          className='font-mono'
          onChange={(e) => {
            setSlugTouched(true)
            setSlug(e.target.value.toLowerCase())
          }}
        />
        <p className='text-xs text-muted-foreground'>
          {branch?.is_default
            ? t('branches.slug_main')
            : t('branches.slug_hint')}
        </p>
        {field('slug')}
      </div>
      <div className='grid gap-2'>
        <Label htmlFor='b-address'>{t('branches.address')}</Label>
        <Input
          id='b-address'
          value={address}
          maxLength={300}
          onChange={(e) => setAddress(e.target.value)}
        />
        {field('address')}
      </div>
      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='grid gap-2'>
          <Label htmlFor='b-phone'>{t('branches.phone')}</Label>
          <Input
            id='b-phone'
            type='tel'
            value={phone}
            maxLength={30}
            onChange={(e) => setPhone(e.target.value)}
          />
          {field('phone')}
        </div>
        <div className='grid gap-2'>
          <Label htmlFor='b-maps'>{t('branches.maps')}</Label>
          <Input
            id='b-maps'
            type='url'
            value={maps}
            placeholder='https://'
            onChange={(e) => setMaps(e.target.value)}
          />
          {field('maps_url')}
        </div>
      </div>
      {!branch?.is_default && (
        <>
          <div className='flex items-center justify-between gap-4'>
            <Label htmlFor='b-active'>{t('branches.active')}</Label>
            <Switch
              id='b-active'
              checked={active}
              onCheckedChange={setActive}
            />
          </div>
          {branch && (
            <div className='flex items-center justify-between gap-4'>
              <Label htmlFor='b-default'>{t('branches.make_main')}</Label>
              <Switch
                id='b-default'
                checked={isDefault}
                onCheckedChange={setIsDefault}
              />
            </div>
          )}
          {field('is_active')}
        </>
      )}
      {menus.length > 0 && (
        <fieldset className='grid gap-2'>
          <legend className='mb-1 text-sm font-medium'>
            {t('branches.menus')}
          </legend>
          {menus.map((m) => (
            <label
              key={m.id}
              className='flex min-h-11 items-center gap-3 text-sm sm:min-h-8'
            >
              <Checkbox
                checked={shown[m.id]}
                onCheckedChange={(on) =>
                  setShown((s) => ({ ...s, [m.id]: on === true }))
                }
              />
              {tr(m.name, locale)}
            </label>
          ))}
        </fieldset>
      )}
      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>
          {t('common.cancel')}
        </Button>
        <Button type='submit' disabled={busy || !name.trim()}>
          {busy && <Loader2 className='animate-spin' />}
          {t('common.save')}
        </Button>
      </DialogFooter>
    </form>
  )
}
