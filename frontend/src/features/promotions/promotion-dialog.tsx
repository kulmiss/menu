import { useState } from 'react'
import { useQueries, useQuery } from '@tanstack/react-query'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { branchesQuery } from '@/features/branches/api'
import { menusQuery, treeQuery } from '@/features/menu/api'
import { PhotoField } from '@/features/menu/components/photo-field'
import { tr, withText } from '@/features/menu/text'
import { type Media } from '@/features/menu/types'
import { type Promotion, fromInput, toInput, useSavePromotion } from './api'

type Props = {
  tenantId: number
  locale: string
  promotion: Promotion | null
  onClose: () => void
}

/** Create or edit a special or an announcement. */
export function PromotionDialog({
  tenantId,
  locale,
  promotion,
  onClose,
}: Props) {
  const { t } = useTranslation()
  const save = useSavePromotion(tenantId)
  const branches = useQuery(branchesQuery(tenantId))
  const menus = useQuery(menusQuery(tenantId))
  const trees = useQueries({
    queries: (menus.data ?? []).map((m) => treeQuery(tenantId, m.id)),
  })
  const items = trees.flatMap(
    (q) => q.data?.categories.flatMap((c) => c.items) ?? []
  )

  const [type, setType] = useState<Promotion['type']>(
    promotion?.type ?? 'special'
  )
  const [title, setTitle] = useState(
    promotion ? tr(promotion.title, locale) : ''
  )
  const [body, setBody] = useState(promotion ? tr(promotion.body, locale) : '')
  const [media, setMedia] = useState<Media | null>(promotion?.media ?? null)
  const [item, setItem] = useState(
    promotion?.item_id ? String(promotion.item_id) : 'none'
  )
  const [branch, setBranch] = useState(
    promotion?.branch_id ? String(promotion.branch_id) : 'all'
  )
  const [starts, setStarts] = useState(toInput(promotion?.starts_at ?? null))
  const [ends, setEnds] = useState(toInput(promotion?.ends_at ?? null))
  const [active, setActive] = useState(promotion?.is_active ?? true)
  const [errors, setErrors] = useState<Record<string, string>>({})

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    try {
      await save.mutateAsync({
        id: promotion?.id,
        type,
        title: withText(promotion?.title, locale, title.trim()),
        body: withText(promotion?.body, locale, body.trim()),
        media_id: type === 'special' ? (media?.id ?? null) : null,
        item_id: type === 'special' && item !== 'none' ? Number(item) : null,
        branch_id: branch === 'all' ? null : Number(branch),
        starts_at: fromInput(starts),
        ends_at: fromInput(ends),
        is_active: active,
      })
      onClose()
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
    }
  }

  const error = (k: string) =>
    errors[k] && <p className='text-sm text-destructive'>{errors[k]}</p>

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-lg'>
        <form onSubmit={submit} className='grid gap-4'>
          <DialogHeader>
            <DialogTitle>
              {promotion ? t('promotions.edit') : t('promotions.new')}
            </DialogTitle>
          </DialogHeader>
          <div className='grid gap-1.5'>
            <Label>{t('promotions.type')}</Label>
            <Select
              value={type}
              onValueChange={(v) => setType(v as Promotion['type'])}
            >
              <SelectTrigger aria-label={t('promotions.type')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='special'>
                  {t('promotions.types.special')}
                </SelectItem>
                <SelectItem value='announcement'>
                  {t('promotions.types.announcement')}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className='grid gap-1.5'>
            <Label htmlFor='pr-title'>{t('promotions.title_label')}</Label>
            <Input
              id='pr-title'
              value={title}
              maxLength={80}
              onChange={(e) => setTitle(e.target.value)}
            />
            {error('title')}
          </div>
          <div className='grid gap-1.5'>
            <Label htmlFor='pr-body'>{t('promotions.body')}</Label>
            <Textarea
              id='pr-body'
              rows={2}
              value={body}
              maxLength={500}
              onChange={(e) => setBody(e.target.value)}
            />
          </div>
          {type === 'special' && (
            <>
              <div className='grid gap-1.5'>
                <Label>{t('promotions.item')}</Label>
                <Select value={item} onValueChange={setItem}>
                  <SelectTrigger aria-label={t('promotions.item')}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='none'>
                      {t('promotions.no_item')}
                    </SelectItem>
                    {items.map((i) => (
                      <SelectItem key={i.id} value={String(i.id)}>
                        {tr(i.name, locale)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className='text-xs text-muted-foreground'>
                  {t('promotions.item_hint')}
                </p>
              </div>
              <PhotoField
                tenantId={tenantId}
                media={media}
                aspect={3 / 2}
                label={t('promotions.photo')}
                onChange={setMedia}
              />
            </>
          )}
          <div className='grid gap-4 sm:grid-cols-2'>
            <div className='grid gap-1.5'>
              <Label htmlFor='pr-starts'>{t('promotions.starts')}</Label>
              <Input
                id='pr-starts'
                type='datetime-local'
                value={starts}
                onChange={(e) => setStarts(e.target.value)}
              />
            </div>
            <div className='grid gap-1.5'>
              <Label htmlFor='pr-ends'>{t('promotions.ends')}</Label>
              <Input
                id='pr-ends'
                type='datetime-local'
                value={ends}
                min={starts}
                onChange={(e) => setEnds(e.target.value)}
              />
              {error('ends_at')}
            </div>
          </div>
          {(branches.data?.length ?? 0) > 1 && (
            <div className='grid gap-1.5'>
              <Label>{t('promotions.branch')}</Label>
              <Select value={branch} onValueChange={setBranch}>
                <SelectTrigger aria-label={t('promotions.branch')}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='all'>
                    {t('promotions.all_branches')}
                  </SelectItem>
                  {branches.data?.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      {tr(b.name, locale)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          <div className='flex items-center justify-between gap-4'>
            <Label htmlFor='pr-active'>{t('promotions.active')}</Label>
            <Switch
              id='pr-active'
              checked={active}
              onCheckedChange={setActive}
            />
          </div>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type='submit' disabled={save.isPending || !title.trim()}>
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
