import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, RotateCcw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type HelpAudience } from '@/features/help/api'
import '@/features/help/help.css'
import { pkeys, send, usePlatformMutation } from './api'
import { PageTitle } from './layout'

/**
 * Super Admin → Help articles (backend QUESTIONS #194): every article with
 * its "Was this helpful?" counts; change one with a live preview, add new
 * ones, reset to the shipped text.
 */
type Row = {
  slug: string
  title: string
  topic: string
  topic_title: string
  features: string[]
  roles: string[]
  source: 'default' | 'changed' | 'custom'
  hidden: boolean
  yes: number
  no: number
}
type Meta = { topics: { key: string; title: string }[]; features: string[] }
type Article = {
  slug: string
  title: string
  topic: string
  summary: string
  body: string
  features: string[]
  roles: string[]
  pages: string[]
  app: string[]
  keywords: string[]
  order: number
  hidden: boolean
  source?: Row['source']
}

const list = (s: string) =>
  s
    .split(',')
    .map((x) => x.trim())
    .filter(Boolean)

export function HelpArticlesPage() {
  const { t } = useTranslation()
  const [audience, setAudience] = useState<HelpAudience>('restaurant')
  const [editing, setEditing] = useState<string | null>(null)
  const q = useQuery({
    queryKey: [...pkeys.all, 'help-articles', audience],
    queryFn: async () =>
      (
        await api.get<{ data: Row[]; meta: Meta }>(
          `/super/help-articles?audience=${audience}`
        )
      ).data,
  })
  return (
    <>
      <PageTitle
        title={t('platform.nav.help-articles')}
        hint={t('platform.help.articles_hint')}
      >
        <Button
          className='min-h-11 sm:min-h-9'
          onClick={() => setEditing('')}
          disabled={!q.data}
        >
          <Plus />
          {t('platform.help.new')}
        </Button>
      </PageTitle>
      <Tabs
        value={audience}
        onValueChange={(v) => setAudience(v as HelpAudience)}
        className='mb-4'
      >
        <TabsList>
          <TabsTrigger value='restaurant' className='min-h-11 sm:min-h-8'>
            {t('platform.help.restaurant')}
          </TabsTrigger>
          <TabsTrigger value='platform' className='min-h-11 sm:min-h-8'>
            {t('platform.help.platform')}
          </TabsTrigger>
        </TabsList>
      </Tabs>
      {!q.data ? (
        <Skeleton className='h-64 w-full' />
      ) : (
        <div className='overflow-x-auto rounded-lg border'>
          <table className='w-full text-sm'>
            <thead className='bg-muted/50 text-start'>
              <tr>
                <th className='px-3 py-2 text-start'>
                  {t('platform.help.col.title')}
                </th>
                <th className='hidden px-3 py-2 text-start md:table-cell'>
                  {t('platform.help.col.topic')}
                </th>
                <th className='px-3 py-2 text-start'>
                  {t('platform.help.col.source')}
                </th>
                <th className='px-3 py-2 text-end'>
                  {t('platform.help.col.helpful')}
                </th>
              </tr>
            </thead>
            <tbody className='divide-y'>
              {q.data.data.map((r) => (
                <tr key={r.slug}>
                  <td className='px-3 py-1'>
                    <button
                      type='button'
                      className='min-h-11 text-start font-medium hover:underline'
                      onClick={() => setEditing(r.slug)}
                    >
                      {r.title}
                    </button>
                    {r.features.length > 0 && (
                      <span className='block text-xs text-muted-foreground'>
                        {t('platform.help.only_when', {
                          features: r.features.join(', '),
                        })}
                      </span>
                    )}
                  </td>
                  <td className='hidden px-3 py-1 text-muted-foreground md:table-cell'>
                    {r.topic_title}
                  </td>
                  <td className='px-3 py-1'>
                    <Badge
                      variant={r.source === 'default' ? 'secondary' : 'default'}
                    >
                      {t(`platform.help.source.${r.source}`)}
                    </Badge>{' '}
                    {r.hidden && (
                      <Badge variant='outline'>
                        {t('platform.help.hidden')}
                      </Badge>
                    )}
                  </td>
                  <td className='px-3 py-1 text-end tabular-nums'>
                    <span
                      aria-label={t('platform.help.yes_no', {
                        yes: r.yes,
                        no: r.no,
                      })}
                    >
                      👍 {r.yes} · 👎 {r.no}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {editing !== null && q.data && (
        <Editor
          audience={audience}
          slug={editing}
          meta={q.data.meta}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  )
}

function Editor({
  audience,
  slug,
  meta,
  onClose,
}: {
  audience: HelpAudience
  slug: string
  meta: Meta
  onClose: () => void
}) {
  const { t } = useTranslation()
  const isNew = slug === ''
  const one = useQuery({
    queryKey: [...pkeys.all, 'help-article', audience, slug],
    enabled: !isNew,
    queryFn: async () =>
      (
        await api.get<{ data: Article & { default: Article | null } }>(
          `/super/help-articles/${audience}/${slug}`
        )
      ).data.data,
  })
  const blank: Article = {
    slug: '',
    title: '',
    topic: meta.topics[0]?.key ?? '',
    summary: '',
    body: '',
    features: [],
    roles: [],
    pages: [],
    app: [],
    keywords: [],
    order: 50,
    hidden: false,
  }
  // What the Super Admin typed; until then, the saved article.
  const [draft, setDraft] = useState<Article | null>(null)
  const v = draft ?? (isNew ? blank : (one.data ?? null))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [resetting, setResetting] = useState(false)
  const preview = usePreview(v?.body ?? '')
  const save = usePlatformMutation((a: Article) =>
    send('put', `help-articles/${audience}/${a.slug}`, a)
  )
  const reset = usePlatformMutation(() =>
    send('delete', `help-articles/${audience}/${slug}`)
  )
  const set = <K extends keyof Article>(k: K, value: Article[K]) =>
    setDraft(v ? { ...v, [k]: value } : null)

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='max-h-[92svh] overflow-y-auto sm:max-w-5xl'>
        <DialogHeader>
          <DialogTitle>
            {isNew ? t('platform.help.new') : v?.title || slug}
          </DialogTitle>
          <DialogDescription>
            {t('platform.help.editor_hint')}
          </DialogDescription>
        </DialogHeader>
        {!v ? (
          <Skeleton className='h-80 w-full' />
        ) : (
          <form
            id='help-article'
            className='grid gap-4 lg:grid-cols-2'
            onSubmit={async (e) => {
              e.preventDefault()
              setErrors({})
              try {
                await save.mutateAsync(v)
                toast.success(t('platform.help.saved'))
                onClose()
              } catch (x) {
                const f = validationErrors(x)
                if (f)
                  setErrors(
                    Object.fromEntries(
                      Object.entries(f).map(([k, m]) => [
                        k,
                        Array.isArray(m) ? String(m[0]) : String(m),
                      ])
                    )
                  )
              }
            }}
          >
            <div className='grid content-start gap-3'>
              {isNew && (
                <Field
                  id='h-slug'
                  label={t('platform.help.slug')}
                  error={errors.slug}
                >
                  <Input
                    id='h-slug'
                    required
                    pattern='[a-z0-9][a-z0-9\-]+'
                    value={v.slug}
                    onChange={(e) => set('slug', e.target.value.toLowerCase())}
                  />
                </Field>
              )}
              <Field
                id='h-title'
                label={t('platform.help.col.title')}
                error={errors.title}
              >
                <Input
                  id='h-title'
                  required
                  maxLength={160}
                  value={v.title}
                  onChange={(e) => set('title', e.target.value)}
                />
              </Field>
              <div className='grid gap-3 sm:grid-cols-2'>
                <Field id='h-topic' label={t('platform.help.col.topic')}>
                  <Select
                    value={v.topic}
                    onValueChange={(x) => set('topic', x)}
                  >
                    <SelectTrigger id='h-topic' className='min-h-11 sm:min-h-9'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {meta.topics.map((x) => (
                        <SelectItem key={x.key} value={x.key}>
                          {x.title}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
                <Field
                  id='h-features'
                  label={t('platform.help.features')}
                  hint={t('platform.help.features_hint')}
                  error={errors['features.0']}
                >
                  <Input
                    id='h-features'
                    value={v.features.join(', ')}
                    onChange={(e) => set('features', list(e.target.value))}
                  />
                </Field>
              </div>
              <Field id='h-summary' label={t('platform.help.summary')}>
                <Input
                  id='h-summary'
                  maxLength={300}
                  value={v.summary}
                  onChange={(e) => set('summary', e.target.value)}
                />
              </Field>
              <Field
                id='h-body'
                label={t('platform.help.body')}
                hint={t('platform.help.body_hint')}
                error={errors.body}
              >
                <Textarea
                  id='h-body'
                  required
                  rows={18}
                  className='font-mono text-[13px]'
                  value={v.body}
                  onChange={(e) => set('body', e.target.value)}
                />
              </Field>
              <div className='grid gap-3 sm:grid-cols-2'>
                <Field
                  id='h-pages'
                  label={t('platform.help.pages')}
                  hint={t('platform.help.pages_hint')}
                  error={errors['pages.0']}
                >
                  <Input
                    id='h-pages'
                    value={v.pages.join(', ')}
                    onChange={(e) => set('pages', list(e.target.value))}
                  />
                </Field>
                <Field
                  id='h-roles'
                  label={t('platform.help.roles')}
                  hint={t('platform.help.roles_hint')}
                >
                  <Input
                    id='h-roles'
                    value={v.roles.join(', ')}
                    onChange={(e) => set('roles', list(e.target.value))}
                  />
                </Field>
              </div>
              <label className='flex min-h-11 items-center justify-between gap-3'>
                <span className='text-sm font-medium'>
                  {t('platform.help.hide')}
                </span>
                <Switch
                  checked={v.hidden}
                  onCheckedChange={(x) => set('hidden', x)}
                />
              </label>
            </div>
            <section
              aria-label={t('platform.help.preview')}
              className='min-w-0'
            >
              <p className='mb-2 text-sm font-medium'>
                {t('platform.help.preview')}
              </p>
              {preview.unknown.length > 0 && (
                <p role='alert' className='mb-2 text-sm text-destructive'>
                  {t('platform.help.unknown', {
                    names: preview.unknown.join(', '),
                  })}
                </p>
              )}
              <div
                className='help-prose help-preview max-h-[60svh] overflow-y-auto rounded-lg border p-4'
                // Rendered by our server with raw HTML escaped.
                dangerouslySetInnerHTML={{ __html: preview.html }}
              />
            </section>
          </form>
        )}
        <DialogFooter className='gap-2'>
          {v?.source && v.source !== 'default' && (
            <Button
              type='button'
              variant='outline'
              className='me-auto min-h-11 sm:min-h-9'
              onClick={() => setResetting(true)}
            >
              <RotateCcw />
              {v.source === 'changed'
                ? t('platform.help.reset')
                : t('platform.help.delete')}
            </Button>
          )}
          <Button type='button' variant='outline' onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button
            type='submit'
            form='help-article'
            disabled={save.isPending || !v}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
      <ConfirmDialog
        open={resetting}
        onOpenChange={setResetting}
        title={
          v?.source === 'changed'
            ? t('platform.help.reset')
            : t('platform.help.delete')
        }
        desc={
          v?.source === 'changed'
            ? t('platform.help.reset_confirm')
            : t('platform.help.delete_confirm')
        }
        destructive
        handleConfirm={async () => {
          await reset.mutateAsync(undefined)
          setResetting(false)
          onClose()
        }}
      />
    </Dialog>
  )
}

/** Server preview: placeholders filled in, feature blocks applied (debounced). */
function usePreview(body: string) {
  const [state, setState] = useState({ html: '', unknown: [] as string[] })
  const text = body
  useEffect(() => {
    if (!text.trim()) return
    const id = window.setTimeout(async () => {
      try {
        const r = await api.post<{ data: { html: string; unknown: string[] } }>(
          '/super/help-articles/preview',
          { body: text }
        )
        setState(r.data.data)
      } catch {
        /* keep the last preview */
      }
    }, 400)
    return () => window.clearTimeout(id)
  }, [text])
  return text.trim() ? state : { html: '', unknown: [] as string[] }
}

function Field({
  id,
  label,
  hint,
  error,
  children,
}: {
  id: string
  label: string
  hint?: string
  error?: string
  children: React.ReactNode
}) {
  return (
    <div className='grid gap-1.5'>
      <Label htmlFor={id}>{label}</Label>
      {children}
      {hint && <p className='text-xs text-muted-foreground'>{hint}</p>}
      {error && <p className='text-sm text-destructive'>{error}</p>}
    </div>
  )
}
