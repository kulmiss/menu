import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Loader2, Pencil, Plus, Tag, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { type TagRef, notesQ, send, tagsQ, usePlatformMutation } from './api'

const TAG_COLORS = [
  '#64748b',
  '#dc2626',
  '#ea580c',
  '#ca8a04',
  '#16a34a',
  '#0891b2',
  '#2563eb',
  '#7c3aed',
  '#db2777',
]

/** A tag: coloured dot + name, readable on any colour (owner, 2026-11-13). */
export function TagChip({
  tag,
  className,
}: {
  tag: TagRef
  className?: string
}) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium',
        className
      )}
      style={{ borderColor: tag.color }}
    >
      <span
        className='size-2 rounded-full'
        style={{ backgroundColor: tag.color }}
        aria-hidden
      />
      {tag.name}
    </span>
  )
}

/** Create, rename, recolour and delete tags. */
export function ManageTagsDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const tags = useQuery(tagsQ())
  const [name, setName] = useState('')
  const [color, setColor] = useState(TAG_COLORS[1])
  const [editing, setEditing] = useState<TagRef | null>(null)
  const [error, setError] = useState<string | null>(null)
  const save = usePlatformMutation(
    (body: { id?: number; name: string; color: string }) =>
      body.id
        ? send('patch', `tags/${body.id}`, body)
        : send('post', 'tags', body)
  )
  const remove = usePlatformMutation(
    (id: number) => send('delete', `tags/${id}`),
    false
  )

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await save.mutateAsync({ id: editing?.id, name: name.trim(), color })
      setName('')
      setEditing(null)
    } catch (err) {
      setError(validationErrors(err)?.name ?? null)
    }
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('platform.crm.manage_tags')}</DialogTitle>
          <DialogDescription>{t('platform.crm.tags_hint')}</DialogDescription>
        </DialogHeader>
        <ul className='grid gap-1'>
          {tags.data?.map((tag) => (
            <li
              key={tag.id}
              className='flex items-center justify-between gap-2'
            >
              <span className='flex items-center gap-2'>
                <TagChip tag={tag} />
                <span className='text-xs text-muted-foreground'>
                  {t('platform.crm.used_by', { count: tag.restaurants ?? 0 })}
                </span>
              </span>
              <span className='flex'>
                <Button
                  type='button'
                  size='icon'
                  variant='ghost'
                  className='size-11 sm:size-9'
                  aria-label={t('platform.crm.rename', { name: tag.name })}
                  onClick={() => {
                    setEditing(tag)
                    setName(tag.name)
                    setColor(tag.color)
                  }}
                >
                  <Pencil />
                </Button>
                <Button
                  type='button'
                  size='icon'
                  variant='ghost'
                  className='size-11 text-destructive sm:size-9'
                  aria-label={t('platform.crm.delete_tag', { name: tag.name })}
                  onClick={() => {
                    if (
                      window.confirm(
                        t('platform.crm.delete_confirm', { name: tag.name })
                      )
                    )
                      remove.mutate(tag.id)
                  }}
                >
                  <Trash2 />
                </Button>
              </span>
            </li>
          ))}
          {tags.data?.length === 0 && (
            <li className='text-sm text-muted-foreground'>
              {t('platform.crm.no_tags')}
            </li>
          )}
        </ul>
        <form className='grid gap-2 border-t pt-3' onSubmit={submit}>
          <Label htmlFor='tag-name'>
            {editing ? t('platform.crm.rename_tag') : t('platform.crm.new_tag')}
          </Label>
          <Input
            id='tag-name'
            maxLength={40}
            value={name}
            placeholder={t('platform.crm.tag_placeholder')}
            onChange={(e) => setName(e.target.value)}
          />
          {error && <p className='text-sm text-destructive'>{error}</p>}
          <div
            className='flex flex-wrap gap-1'
            role='radiogroup'
            aria-label={t('platform.crm.colour')}
          >
            {TAG_COLORS.map((c) => (
              <button
                key={c}
                type='button'
                role='radio'
                aria-checked={color === c}
                aria-label={c}
                onClick={() => setColor(c)}
                className={cn(
                  'grid size-11 place-items-center rounded-full sm:size-8',
                  color === c && 'ring-2 ring-foreground ring-offset-2'
                )}
              >
                <span
                  className='size-6 rounded-full'
                  style={{ backgroundColor: c }}
                />
              </button>
            ))}
          </div>
          <div className='flex gap-2'>
            <Button
              type='submit'
              className='min-h-11 sm:min-h-9'
              disabled={!name.trim() || save.isPending}
            >
              {save.isPending ? <Loader2 className='animate-spin' /> : <Plus />}
              {editing ? t('common.save') : t('platform.crm.add_tag')}
            </Button>
            {editing && (
              <Button
                type='button'
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                onClick={() => {
                  setEditing(null)
                  setName('')
                }}
              >
                {t('common.cancel')}
              </Button>
            )}
          </div>
        </form>
      </DialogContent>
    </Dialog>
  )
}

type CrmProps = {
  id: number
  tags: TagRef[]
  followUpOn: string | null
  followUpReason: string | null
}

/** Restaurant sheet: tags, follow-up and the notes timeline (Super Admin only). */
export function CrmSection({ id, tags, followUpOn, followUpReason }: CrmProps) {
  const { t, i18n } = useTranslation()
  const all = useQuery(tagsQ())
  const notes = useQuery(notesQ(id))
  const [managing, setManaging] = useState(false)
  const [date, setDate] = useState(followUpOn ?? '')
  const [reason, setReason] = useState(followUpReason ?? '')
  const [draft, setDraft] = useState('')
  const [editing, setEditing] = useState<{ id: number; body: string } | null>(
    null
  )
  const selected = new Set(tags.map((g) => g.id))
  const setTags = usePlatformMutation(
    (ids: number[]) => send('put', `restaurants/${id}/tags`, { tag_ids: ids }),
    false
  )
  const follow = usePlatformMutation(
    (body: object) => send('put', `restaurants/${id}/follow-up`, body),
    false
  )
  const addNote = usePlatformMutation(
    (body: string) => send('post', `restaurants/${id}/notes`, { body }),
    false
  )
  const editNote = usePlatformMutation(
    (n: { id: number; body: string }) =>
      send('patch', `notes/${n.id}`, { body: n.body }),
    false
  )
  const deleteNote = usePlatformMutation(
    (noteId: number) => send('delete', `notes/${noteId}`),
    false
  )
  const when = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))

  return (
    <section className='grid gap-4'>
      <h3 className='font-semibold'>{t('platform.crm.title')}</h3>

      <div className='grid gap-2'>
        <div className='flex items-center justify-between gap-2'>
          <span className='text-sm font-medium'>{t('platform.crm.tags')}</span>
          <Button
            type='button'
            size='sm'
            variant='ghost'
            className='min-h-11 sm:min-h-8'
            onClick={() => setManaging(true)}
          >
            <Tag className='size-4' /> {t('platform.crm.manage_tags')}
          </Button>
        </div>
        <div className='flex flex-wrap gap-1.5'>
          {all.data?.map((tag) => {
            const on = selected.has(tag.id)
            return (
              <button
                key={tag.id}
                type='button'
                aria-pressed={on}
                disabled={setTags.isPending}
                onClick={() => {
                  const next = new Set(selected)
                  if (on) next.delete(tag.id)
                  else next.add(tag.id)
                  setTags.mutate([...next])
                }}
                className={cn(
                  'min-h-11 rounded-full sm:min-h-8',
                  !on && 'opacity-60 hover:opacity-100'
                )}
              >
                <TagChip
                  tag={tag}
                  className={on ? 'bg-accent' : 'border-dashed'}
                />
              </button>
            )
          })}
          {all.data?.length === 0 && (
            <span className='text-sm text-muted-foreground'>
              {t('platform.crm.no_tags')}
            </span>
          )}
        </div>
      </div>

      <form
        className='grid gap-2'
        onSubmit={async (e) => {
          e.preventDefault()
          await follow.mutateAsync({ date: date || null, reason })
          toast.success(t('platform.saved'))
        }}
      >
        <span className='text-sm font-medium'>
          {t('platform.crm.follow_up')}
        </span>
        <div className='grid gap-2 sm:grid-cols-[10rem_1fr]'>
          <Input
            type='date'
            aria-label={t('platform.crm.follow_up_date')}
            value={date}
            onChange={(e) => setDate(e.target.value)}
          />
          <Input
            maxLength={200}
            aria-label={t('platform.crm.follow_up_reason')}
            placeholder={t('platform.crm.follow_up_reason')}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
        </div>
        <div className='flex gap-2'>
          <Button
            type='submit'
            size='sm'
            className='min-h-11 sm:min-h-8'
            disabled={follow.isPending}
          >
            {t('common.save')}
          </Button>
          {followUpOn && (
            <Button
              type='button'
              size='sm'
              variant='ghost'
              className='min-h-11 sm:min-h-8'
              onClick={async () => {
                setDate('')
                setReason('')
                await follow.mutateAsync({ date: null })
              }}
            >
              {t('platform.crm.follow_up_clear')}
            </Button>
          )}
        </div>
      </form>

      <div className='grid gap-2'>
        <span className='text-sm font-medium'>{t('platform.crm.notes')}</span>
        <form
          className='grid gap-2'
          onSubmit={async (e) => {
            e.preventDefault()
            if (!draft.trim()) return
            await addNote.mutateAsync(draft.trim())
            setDraft('')
          }}
        >
          <Textarea
            rows={2}
            maxLength={5000}
            aria-label={t('platform.crm.new_note')}
            placeholder={t('platform.crm.new_note')}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
          />
          <Button
            type='submit'
            size='sm'
            className='min-h-11 justify-self-start sm:min-h-8'
            disabled={!draft.trim() || addNote.isPending}
          >
            <Plus className='size-4' /> {t('platform.crm.add_note')}
          </Button>
        </form>
        <ol className='grid gap-2'>
          {notes.data?.map((n) => (
            <li key={n.id} className='rounded-md border p-3'>
              <div className='mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground'>
                <span>
                  {n.author?.name ?? '—'} · {when(n.created_at)}
                  {n.edited ? ` · ${t('platform.crm.edited')}` : ''}
                </span>
                {n.mine && editing?.id !== n.id && (
                  <span className='flex'>
                    <Button
                      type='button'
                      size='sm'
                      variant='ghost'
                      className='min-h-11 sm:min-h-7'
                      onClick={() => setEditing({ id: n.id, body: n.body })}
                    >
                      {t('platform.crm.edit')}
                    </Button>
                    <Button
                      type='button'
                      size='sm'
                      variant='ghost'
                      className='min-h-11 text-destructive sm:min-h-7'
                      onClick={() => {
                        if (window.confirm(t('platform.crm.delete_note')))
                          deleteNote.mutate(n.id)
                      }}
                    >
                      {t('platform.crm.delete')}
                    </Button>
                  </span>
                )}
              </div>
              {editing?.id === n.id ? (
                <form
                  className='grid gap-2'
                  onSubmit={async (e) => {
                    e.preventDefault()
                    await editNote.mutateAsync(editing)
                    setEditing(null)
                  }}
                >
                  <Textarea
                    rows={3}
                    maxLength={5000}
                    aria-label={t('platform.crm.edit')}
                    value={editing.body}
                    onChange={(e) =>
                      setEditing({ ...editing, body: e.target.value })
                    }
                  />
                  <div className='flex gap-2'>
                    <Button
                      type='submit'
                      size='sm'
                      className='min-h-11 sm:min-h-8'
                      disabled={!editing.body.trim()}
                    >
                      {t('common.save')}
                    </Button>
                    <Button
                      type='button'
                      size='sm'
                      variant='ghost'
                      className='min-h-11 sm:min-h-8'
                      onClick={() => setEditing(null)}
                    >
                      {t('common.cancel')}
                    </Button>
                  </div>
                </form>
              ) : (
                <p className='text-sm whitespace-pre-line'>{n.body}</p>
              )}
            </li>
          ))}
          {notes.data?.length === 0 && (
            <li className='text-sm text-muted-foreground'>
              {t('platform.crm.no_notes')}
            </li>
          )}
        </ol>
      </div>
      {managing && <ManageTagsDialog onClose={() => setManaging(false)} />}
    </section>
  )
}
