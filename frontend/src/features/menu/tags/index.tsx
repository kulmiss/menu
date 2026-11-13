import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Check, Loader2, Pencil, Plus, Trash2, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
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
import { Skeleton } from '@/components/ui/skeleton'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { tagsQuery, useDeleteTag, useSaveTag } from '../api'
import { tr, withText } from '../text'
import { type Tag } from '../types'
import { useMenuContext } from '../use-menu-context'

/** Built-in tags (translated by the app) and the restaurant's own. */
export function TagsPage() {
  const { t } = useTranslation()
  const ctx = useMenuContext()
  const tags = useQuery(tagsQuery(ctx.tenantId))
  const save = useSaveTag(ctx.tenantId)
  const remove = useDeleteTag(ctx.tenantId)
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [text, setText] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [deleting, setDeleting] = useState<Tag | null>(null)
  const canEdit = ctx.canManage && ctx.canWrite

  const system = tags.data?.filter((x) => x.is_system) ?? []
  const own = tags.data?.filter((x) => !x.is_system) ?? []

  function start(tag: Tag | null) {
    setEditing(tag ? tag.id : 'new')
    setText(tag ? tr(tag.name, ctx.locale) : '')
    setError(null)
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    if (!text.trim()) return setError(t('validation.menu_name'))
    const tag = own.find((x) => x.id === editing)
    try {
      await save.mutateAsync({
        id: tag?.id,
        name: withText(tag?.name, ctx.locale, text.trim()),
      })
      setEditing(null)
    } catch (err) {
      const fields = validationErrors(err)
      if (fields) setError(fields.name ?? Object.values(fields)[0])
    }
  }

  const form = (
    <form onSubmit={submit} className='flex items-start gap-2 py-2'>
      <div className='grid flex-1 gap-1'>
        <Input
          autoFocus
          value={text}
          maxLength={40}
          onChange={(e) => setText(e.target.value)}
          aria-label={t('tags.name')}
          aria-invalid={error ? true : undefined}
          className='min-h-11 sm:min-h-9'
        />
        {error && <p className='text-sm text-destructive'>{error}</p>}
      </div>
      <Button
        type='submit'
        size='icon'
        className='size-11 sm:size-9'
        aria-label={t('common.save')}
        disabled={save.isPending}
      >
        {save.isPending ? <Loader2 className='animate-spin' /> : <Check />}
      </Button>
      <Button
        type='button'
        variant='ghost'
        size='icon'
        className='size-11 sm:size-9'
        aria-label={t('common.cancel')}
        onClick={() => setEditing(null)}
      >
        <X />
      </Button>
    </form>
  )

  return (
    <>
      <AdminHeader />
      <Main>
        <h1 className='mb-6 text-2xl font-bold tracking-tight'>
          {t('tags.title')}
        </h1>
        {tags.isPending ? (
          <Skeleton className='h-40 w-full' />
        ) : (
          <div className='grid gap-6 md:grid-cols-2'>
            <Card>
              <CardHeader>
                <CardTitle>{t('tags.builtin')}</CardTitle>
                <CardDescription>{t('tags.builtin_intro')}</CardDescription>
              </CardHeader>
              <CardContent className='flex flex-wrap gap-2'>
                {system.map((tag) => (
                  <Badge
                    key={tag.id}
                    variant='secondary'
                    className='px-3 py-1 text-sm'
                  >
                    {t(`tags.${tag.key}`)}
                  </Badge>
                ))}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{t('tags.own')}</CardTitle>
                <CardDescription>{t('tags.own_intro')}</CardDescription>
              </CardHeader>
              <CardContent>
                <ul className='divide-y'>
                  {own.map((tag) =>
                    editing === tag.id ? (
                      <li key={tag.id}>{form}</li>
                    ) : (
                      <li key={tag.id} className='flex items-center gap-1 py-1'>
                        <span className='flex-1 truncate'>
                          {tr(tag.name, ctx.locale)}
                        </span>
                        {canEdit && (
                          <>
                            <Button
                              variant='ghost'
                              size='icon'
                              className='size-11 sm:size-8'
                              aria-label={t('tags.rename_named', {
                                name: tr(tag.name, ctx.locale),
                              })}
                              onClick={() => start(tag)}
                            >
                              <Pencil />
                            </Button>
                            <Button
                              variant='ghost'
                              size='icon'
                              className='size-11 sm:size-8'
                              aria-label={t('tags.delete_named', {
                                name: tr(tag.name, ctx.locale),
                              })}
                              onClick={() => setDeleting(tag)}
                            >
                              <Trash2 />
                            </Button>
                          </>
                        )}
                      </li>
                    )
                  )}
                </ul>
                {own.length === 0 && editing !== 'new' && (
                  <p className='text-sm text-muted-foreground'>
                    {t('tags.none')}
                  </p>
                )}
                {editing === 'new' && form}
                {canEdit && editing === null && (
                  <Button
                    variant='outline'
                    className='mt-2 min-h-11 sm:min-h-9'
                    onClick={() => start(null)}
                  >
                    <Plus /> {t('tags.add')}
                  </Button>
                )}
              </CardContent>
            </Card>
          </div>
        )}
        <ConfirmDialog
          open={deleting !== null}
          onOpenChange={(o) => !o && setDeleting(null)}
          title={t('tags.delete')}
          desc={t('tags.delete_confirm', {
            name: deleting ? tr(deleting.name, ctx.locale) : '',
          })}
          confirmText={t('menu.delete')}
          destructive
          isLoading={remove.isPending}
          handleConfirm={() =>
            deleting &&
            remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
          }
        />
      </Main>
    </>
  )
}
