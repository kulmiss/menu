import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowDown,
  ArrowUp,
  ExternalLink,
  History,
  Loader2,
  Monitor,
  Moon,
  Plus,
  RotateCcw,
  Smartphone,
  Sun,
  Trash2,
  Upload,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { validationErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { cn } from '@/lib/utils'
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
import { ScaledFrame } from '@/features/appearance/desktop-preview'
import { SortableItem, SortableList } from '@/features/menu/editor/sortable'
import { send, usePlatformMutation } from './api'
import {
  type FooterColumn,
  type FooterContent,
  type FooterData,
  type FooterLink,
  footerQ,
  move,
  newId,
  previewUrl,
} from './footer-data'
import { PageTitle } from './layout'

/** A link to a web address instead of a page of this site. */
const URL_CHOICE = '__url'

type Errors = Record<string, string>

/**
 * Super Admin → Footer (owner, 2026-11-18, QUESTIONS #230): every part of
 * the website footer, as a draft with a live preview, then Publish. Earlier
 * versions come back in one click.
 */
export function FooterEditorPage() {
  const { t } = useTranslation()
  const footer = useQuery(footerQ())
  return (
    <>
      <PageTitle
        title={t('platform.nav.footer')}
        hint={t('platform.footer_editor.hint')}
      >
        <Button asChild variant='outline' className='min-h-11 sm:min-h-9'>
          <a href='/' target='_blank' rel='noopener'>
            <ExternalLink aria-hidden />
            {t('platform.footer_editor.open_site')}
          </a>
        </Button>
      </PageTitle>
      {footer.data ? (
        <FooterEditor data={footer.data} />
      ) : (
        <Skeleton className='h-96' />
      )}
    </>
  )
}

export function FooterEditor({ data }: { data: FooterData }) {
  const { t, i18n } = useTranslation()
  const [v, setV] = useState<FooterContent>(data.draft)
  const [saved, setSaved] = useState<FooterContent>(data.draft)
  const [changed, setChanged] = useState(data.changed)
  const [versions, setVersions] = useState(data.versions)
  const [errors, setErrors] = useState<Errors>({})
  const [frame, setFrame] = useState(0)
  const dirty = JSON.stringify(v) !== JSON.stringify(saved)

  const save = usePlatformMutation((body: FooterContent) =>
    send('put', 'footer', body)
  )
  const publish = usePlatformMutation(() => send('post', 'footer/publish'))
  const discard = usePlatformMutation(() => send('delete', 'footer/draft'))
  const restore = usePlatformMutation((id: number) =>
    send('post', `footer/versions/${id}/restore`)
  )
  const busy =
    save.isPending ||
    publish.isPending ||
    discard.isPending ||
    restore.isPending

  /** Takes the server's answer: the cleaned draft, versions, preview. */
  function took(res: unknown) {
    const next = (res as { data: { data: FooterData } }).data.data
    setV(next.draft)
    setSaved(next.draft)
    setChanged(next.changed)
    setVersions(next.versions)
    setErrors({})
    setFrame((f) => f + 1)
  }

  async function saveDraft(): Promise<boolean> {
    try {
      took(await save.mutateAsync(v))
      return true
    } catch (err) {
      setErrors(validationErrors(err) ?? {})
      toast.error(t('platform.footer_editor.fix_errors'))
      return false
    }
  }

  async function onSave() {
    if (await saveDraft()) toast.success(t('platform.footer_editor.saved'))
  }

  async function onPublish() {
    if (dirty && !(await saveDraft())) return
    try {
      took(await publish.mutateAsync(undefined))
      toast.success(t('platform.footer_editor.published'))
    } catch (err) {
      toast.error(
        Object.values(validationErrors(err) ?? {})[0] ??
          t('platform.footer_editor.publish_failed')
      )
    }
  }

  async function onDiscard() {
    took(await discard.mutateAsync(undefined))
    toast.success(t('platform.footer_editor.discarded'))
  }

  async function onRestore(id: number) {
    took(await restore.mutateAsync(id))
    toast.success(t('platform.footer_editor.restored', { id }))
  }

  const err = (key: string) =>
    errors[key] ? (
      <p className='text-sm text-destructive' role='alert'>
        {errors[key]}
      </p>
    ) : null
  const set = <K extends keyof FooterContent>(k: K, val: FooterContent[K]) =>
    setV({ ...v, [k]: val })
  const setColumn = (i: number, c: FooterColumn) =>
    set(
      'columns',
      v.columns.map((x, j) => (j === i ? c : x))
    )
  const pages = data.pages.filter((p) => data.available.includes(p.key))
  const when = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })

  return (
    <div className='grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] xl:grid-cols-[minmax(0,1fr)_minmax(0,32rem)]'>
      <div className='min-w-0 space-y-4'>
        <Card>
          <CardContent className='flex flex-wrap items-center gap-2'>
            <p
              className='me-auto text-sm'
              role='status'
              data-testid='footer-state'
            >
              {dirty
                ? t('platform.footer_editor.state_unsaved')
                : changed
                  ? t('platform.footer_editor.state_draft')
                  : t('platform.footer_editor.state_live')}
            </p>
            {changed && !dirty && (
              <Button
                variant='ghost'
                className='min-h-11 sm:min-h-9'
                onClick={onDiscard}
                disabled={busy}
              >
                <RotateCcw aria-hidden />
                {t('platform.footer_editor.discard')}
              </Button>
            )}
            <Button
              variant='outline'
              className='min-h-11 sm:min-h-9'
              onClick={onSave}
              disabled={busy || !dirty}
            >
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('platform.footer_editor.save')}
            </Button>
            <Button
              className='min-h-11 sm:min-h-9'
              onClick={onPublish}
              disabled={busy || (!dirty && !changed)}
            >
              {publish.isPending ? (
                <Loader2 className='animate-spin' />
              ) : (
                <Upload aria-hidden />
              )}
              {t('platform.footer_editor.publish')}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.footer_editor.parts')}</CardTitle>
            <CardDescription>
              {t('platform.footer_editor.parts_hint')}
            </CardDescription>
          </CardHeader>
          <CardContent className='grid gap-x-10 gap-y-1 sm:grid-cols-2'>
            <Toggle
              id='ft-art'
              label={t('platform.footer_editor.illustration')}
              checked={v.illustration}
              onChange={(on) => set('illustration', on)}
            />
            <Toggle
              id='ft-contact'
              label={t('platform.footer_editor.contact')}
              checked={v.contact.on}
              onChange={(on) => set('contact', { ...v.contact, on })}
            />
            <Toggle
              id='ft-app'
              label={t('platform.footer_editor.app')}
              checked={v.app.on}
              onChange={(on) => set('app', { ...v.app, on })}
            />
            <Toggle
              id='ft-trust'
              label={t('platform.footer_editor.trust')}
              checked={v.trust.on}
              onChange={(on) => set('trust', { ...v.trust, on })}
            />
            <Toggle
              id='ft-top'
              label={t('platform.footer_editor.back_to_top')}
              checked={v.bottom.back_to_top}
              onChange={(on) => set('bottom', { ...v.bottom, back_to_top: on })}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.footer_editor.columns')}</CardTitle>
            <CardDescription>
              {t('platform.footer_editor.columns_hint')}
            </CardDescription>
          </CardHeader>
          <CardContent className='space-y-3'>
            {err('columns')}
            <SortableList
              ids={v.columns.map((c) => c.id)}
              onReorder={(ids) =>
                set(
                  'columns',
                  ids.map((id) => v.columns.find((c) => c.id === id)!)
                )
              }
            >
              <ul className='space-y-3'>
                {v.columns.map((c, i) => (
                  <SortableItem
                    key={c.id}
                    id={c.id}
                    handleLabel={t('platform.footer_editor.drag_column', {
                      name: c.title,
                    })}
                    className='rounded-lg border bg-card p-3'
                  >
                    {(handle) => (
                      <ColumnEditor
                        column={c}
                        index={i}
                        count={v.columns.length}
                        handle={handle}
                        pages={pages}
                        maxLinks={data.limits.links}
                        errors={errors}
                        onChange={(next) => setColumn(i, next)}
                        onMove={(by) => set('columns', move(v.columns, i, by))}
                        onRemove={() =>
                          set(
                            'columns',
                            v.columns.filter((_, j) => j !== i)
                          )
                        }
                      />
                    )}
                  </SortableItem>
                ))}
              </ul>
            </SortableList>
            <Button
              variant='outline'
              className='min-h-11 sm:min-h-9'
              disabled={v.columns.length >= data.limits.columns}
              onClick={() =>
                set('columns', [
                  ...v.columns,
                  {
                    id: newId('c'),
                    title: t('platform.footer_editor.new_column'),
                    on: true,
                    links: [],
                  },
                ])
              }
            >
              <Plus aria-hidden />
              {t('platform.footer_editor.add_column')}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.footer_editor.contact')}</CardTitle>
            <CardDescription>
              {t('platform.footer_editor.contact_hint')}
            </CardDescription>
          </CardHeader>
          <CardContent className='grid gap-3 sm:grid-cols-2'>
            {(
              [
                ['title', 'text'],
                ['whatsapp', 'tel'],
                ['phone', 'tel'],
                ['email', 'email'],
                ['address', 'text'],
                ['hours', 'text'],
              ] as const
            ).map(([k, type]) => (
              <Field
                key={k}
                id={`ft-contact-${k}`}
                label={t(`platform.footer_editor.contact_${k}`)}
                type={type}
                value={v.contact[k]}
                error={errors[`contact.${k}`]}
                onChange={(val) => set('contact', { ...v.contact, [k]: val })}
              />
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.footer_editor.socials')}</CardTitle>
            <CardDescription>
              {t('platform.footer_editor.socials_hint')}
            </CardDescription>
          </CardHeader>
          <CardContent className='grid gap-3 sm:grid-cols-2'>
            {data.socials.map((k) => (
              <Field
                key={k}
                id={`ft-social-${k}`}
                label={t(`platform.footer_editor.social.${k}`)}
                type='url'
                placeholder='https://'
                value={v.socials[k] ?? ''}
                error={errors[`socials.${k}`]}
                onChange={(val) => set('socials', { ...v.socials, [k]: val })}
              />
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.footer_editor.more')}</CardTitle>
          </CardHeader>
          <CardContent className='grid gap-3 sm:grid-cols-2'>
            <Field
              id='ft-play'
              label={t('platform.footer_editor.play_url')}
              hint={t('platform.footer_editor.play_hint')}
              type='url'
              placeholder='https://play.google.com/store/apps/details?id='
              value={v.app.play_url}
              error={errors['app.play_url']}
              onChange={(val) => set('app', { ...v.app, play_url: val })}
            />
            <Field
              id='ft-trust-text'
              label={t('platform.footer_editor.trust_text')}
              hint={t('platform.footer_editor.trust_hint')}
              value={v.trust.text}
              error={errors['trust.text']}
              onChange={(val) => set('trust', { ...v.trust, text: val })}
            />
            <Field
              id='ft-company'
              label={t('platform.footer_editor.company')}
              value={v.bottom.company}
              error={errors['bottom.company']}
              onChange={(val) => set('bottom', { ...v.bottom, company: val })}
            />
            <Field
              id='ft-made-in'
              label={t('platform.footer_editor.made_in')}
              placeholder={t('platform.footer_editor.made_in_example')}
              value={v.bottom.made_in}
              error={errors['bottom.made_in']}
              onChange={(val) => set('bottom', { ...v.bottom, made_in: val })}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className='flex items-center gap-2'>
              <History className='size-4' aria-hidden />
              {t('platform.footer_editor.history')}
            </CardTitle>
            <CardDescription>
              {t('platform.footer_editor.history_hint')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {versions.length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                {t('platform.footer_editor.no_versions')}
              </p>
            ) : (
              <ul className='divide-y'>
                {versions.map((r) => (
                  <li
                    key={r.id}
                    className='flex flex-wrap items-center gap-2 py-2 text-sm'
                  >
                    <span className='font-medium'>
                      {t('platform.footer_editor.version', { id: r.id })}
                    </span>
                    {r.live && (
                      <Badge>{t('platform.footer_editor.live')}</Badge>
                    )}
                    <span className='text-muted-foreground'>
                      {when.format(new Date(r.at))}
                      {r.by ? ` · ${r.by}` : ''}
                      {r.restored_from
                        ? ` · ${t('platform.footer_editor.restored_from', { id: r.restored_from })}`
                        : ''}
                    </span>
                    {!r.live && (
                      <Button
                        variant='outline'
                        size='sm'
                        className='ms-auto min-h-11 sm:min-h-8'
                        disabled={busy}
                        onClick={() => onRestore(r.id)}
                        aria-label={t('platform.footer_editor.restore_n', {
                          id: r.id,
                        })}
                      >
                        <RotateCcw aria-hidden />
                        {t('platform.footer_editor.restore')}
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <FooterPreview frame={frame} dirty={dirty} />
    </div>
  )
}

function ColumnEditor({
  column: c,
  index,
  count,
  handle,
  pages,
  maxLinks,
  errors,
  onChange,
  onMove,
  onRemove,
}: {
  column: FooterColumn
  index: number
  count: number
  handle: React.ReactNode
  pages: FooterData['pages']
  maxLinks: number
  errors: Errors
  onChange: (c: FooterColumn) => void
  onMove: (by: -1 | 1) => void
  onRemove: () => void
}) {
  const { t } = useTranslation()
  const key = `columns.${index}`
  const setLink = (j: number, l: FooterLink) =>
    onChange({ ...c, links: c.links.map((x, k) => (k === j ? l : x)) })
  return (
    <div className='space-y-3'>
      <div className='flex flex-wrap items-end gap-2'>
        {handle}
        <div className='min-w-40 flex-1 space-y-1'>
          <Label htmlFor={`ft-col-${c.id}`}>
            {t('platform.footer_editor.column_title')}
          </Label>
          <Input
            id={`ft-col-${c.id}`}
            value={c.title}
            maxLength={60}
            aria-invalid={!!errors[`${key}.title`]}
            className='min-h-11 sm:min-h-9'
            onChange={(e) => onChange({ ...c, title: e.target.value })}
          />
        </div>
        <div className='ms-auto flex items-center gap-1 sm:min-h-9'>
          <Switch
            checked={c.on}
            onCheckedChange={(on) => onChange({ ...c, on })}
            aria-label={t('platform.footer_editor.show_column', {
              name: c.title,
            })}
            className='me-1'
          />
          <MoveButtons
            name={c.title}
            first={index === 0}
            last={index === count - 1}
            onMove={onMove}
          />
          <Button
            variant='ghost'
            size='icon'
            className='size-11 text-destructive sm:size-8'
            onClick={onRemove}
            aria-label={t('platform.footer_editor.remove_column', {
              name: c.title,
            })}
          >
            <Trash2 />
          </Button>
        </div>
      </div>
      {errors[`${key}.title`] && (
        <p className='text-sm text-destructive' role='alert'>
          {errors[`${key}.title`]}
        </p>
      )}
      {errors[`${key}.links`] && (
        <p className='text-sm text-destructive' role='alert'>
          {errors[`${key}.links`]}
        </p>
      )}
      <SortableList
        ids={c.links.map((l) => l.id)}
        onReorder={(ids) =>
          onChange({
            ...c,
            links: ids.map((id) => c.links.find((l) => l.id === id)!),
          })
        }
      >
        <ul className='space-y-2 ps-2 sm:ps-6'>
          {c.links.map((l, j) => (
            <SortableItem
              key={l.id}
              id={l.id}
              handleLabel={t('platform.footer_editor.drag_link', {
                name: l.label,
              })}
              className='rounded-md bg-muted/50 p-2'
            >
              {(linkHandle) => (
                <LinkEditor
                  link={l}
                  errorKey={`${key}.links.${j}`}
                  errors={errors}
                  handle={linkHandle}
                  pages={pages}
                  first={j === 0}
                  last={j === c.links.length - 1}
                  onChange={(next) => setLink(j, next)}
                  onMove={(by) =>
                    onChange({ ...c, links: move(c.links, j, by) })
                  }
                  onRemove={() =>
                    onChange({
                      ...c,
                      links: c.links.filter((_, k) => k !== j),
                    })
                  }
                />
              )}
            </SortableItem>
          ))}
        </ul>
      </SortableList>
      <Button
        variant='ghost'
        className='min-h-11 sm:min-h-9'
        disabled={c.links.length >= maxLinks}
        onClick={() =>
          onChange({
            ...c,
            links: [
              ...c.links,
              {
                id: newId('l'),
                label: t('platform.footer_editor.new_link'),
                page: pages[0]?.key ?? null,
                url: '',
                on: true,
              },
            ],
          })
        }
      >
        <Plus aria-hidden />
        {t('platform.footer_editor.add_link', { name: c.title })}
      </Button>
    </div>
  )
}

function LinkEditor({
  link: l,
  errorKey,
  errors,
  handle,
  pages,
  first,
  last,
  onChange,
  onMove,
  onRemove,
}: {
  link: FooterLink
  errorKey: string
  errors: Errors
  handle: React.ReactNode
  pages: FooterData['pages']
  first: boolean
  last: boolean
  onChange: (l: FooterLink) => void
  onMove: (by: -1 | 1) => void
  onRemove: () => void
}) {
  const { t } = useTranslation()
  const choice = l.page ?? URL_CHOICE
  const fieldErrors = ['label', 'page', 'url']
    .map((k) => errors[`${errorKey}.${k}`])
    .filter(Boolean)
  return (
    <div className='space-y-2'>
      <div className='flex flex-wrap items-end gap-2'>
        {handle}
        <div className='min-w-28 flex-1 space-y-1'>
          <Label htmlFor={`ft-link-${l.id}`}>
            {t('platform.footer_editor.link_label')}
          </Label>
          <Input
            id={`ft-link-${l.id}`}
            value={l.label}
            maxLength={60}
            aria-invalid={!!errors[`${errorKey}.label`]}
            className='min-h-11 sm:min-h-9'
            onChange={(e) => onChange({ ...l, label: e.target.value })}
          />
        </div>
        <div className='w-full space-y-1 sm:w-auto sm:min-w-36 sm:flex-1'>
          <Label htmlFor={`ft-target-${l.id}`}>
            {t('platform.footer_editor.link_target')}
          </Label>
          <Select
            value={choice}
            onValueChange={(val) =>
              onChange(
                val === URL_CHOICE
                  ? { ...l, page: null }
                  : { ...l, page: val, url: '' }
              )
            }
          >
            <SelectTrigger
              id={`ft-target-${l.id}`}
              className='min-h-11 w-full sm:min-h-9'
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {pages.map((p) => (
                <SelectItem key={p.key} value={p.key}>
                  {p.label}
                </SelectItem>
              ))}
              <SelectItem value={URL_CHOICE}>
                {t('platform.footer_editor.web_address')}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className='ms-auto flex items-center gap-1 sm:min-h-9'>
          <Switch
            checked={l.on}
            onCheckedChange={(on) => onChange({ ...l, on })}
            aria-label={t('platform.footer_editor.show_link', {
              name: l.label,
            })}
            className='me-1'
          />
          <MoveButtons
            name={l.label}
            first={first}
            last={last}
            onMove={onMove}
          />
          <Button
            variant='ghost'
            size='icon'
            className='size-11 text-destructive sm:size-8'
            onClick={onRemove}
            aria-label={t('platform.footer_editor.remove_link', {
              name: l.label,
            })}
          >
            <Trash2 />
          </Button>
        </div>
      </div>
      {choice === URL_CHOICE && (
        <div className='space-y-1'>
          <Label htmlFor={`ft-url-${l.id}`}>
            {t('platform.footer_editor.link_url')}
          </Label>
          <Input
            id={`ft-url-${l.id}`}
            type='url'
            value={l.url}
            maxLength={500}
            placeholder='https://'
            aria-invalid={!!errors[`${errorKey}.url`]}
            className='min-h-11 sm:min-h-9'
            onChange={(e) => onChange({ ...l, url: e.target.value })}
          />
        </div>
      )}
      {fieldErrors.map((m) => (
        <p key={m} className='text-sm text-destructive' role='alert'>
          {m}
        </p>
      ))}
    </div>
  )
}

function MoveButtons({
  name,
  first,
  last,
  onMove,
}: {
  name: string
  first: boolean
  last: boolean
  onMove: (by: -1 | 1) => void
}) {
  const { t } = useTranslation()
  return (
    <>
      <Button
        variant='ghost'
        size='icon'
        className='size-11 sm:size-8'
        disabled={first}
        onClick={() => onMove(-1)}
        aria-label={t('platform.footer_editor.move_up', { name })}
      >
        <ArrowUp />
      </Button>
      <Button
        variant='ghost'
        size='icon'
        className='size-11 sm:size-8'
        disabled={last}
        onClick={() => onMove(1)}
        aria-label={t('platform.footer_editor.move_down', { name })}
      >
        <ArrowDown />
      </Button>
    </>
  )
}

function Toggle({
  id,
  label,
  checked,
  onChange,
}: {
  id: string
  label: string
  checked: boolean
  onChange: (on: boolean) => void
}) {
  return (
    <div className='flex min-h-11 items-center justify-between gap-3'>
      <Label htmlFor={id}>{label}</Label>
      <Switch id={id} checked={checked} onCheckedChange={onChange} />
    </div>
  )
}

function Field({
  id,
  label,
  hint,
  type = 'text',
  placeholder,
  value,
  error,
  onChange,
}: {
  id: string
  label: string
  hint?: string
  type?: 'text' | 'url' | 'email' | 'tel'
  placeholder?: string
  value: string
  error?: string
  onChange: (v: string) => void
}) {
  return (
    <div className='space-y-1'>
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        type={type}
        value={value}
        placeholder={placeholder}
        aria-invalid={!!error}
        aria-describedby={hint ? `${id}-hint` : undefined}
        className='min-h-11 sm:min-h-9'
        onChange={(e) => onChange(e.target.value)}
      />
      {hint && (
        <p id={`${id}-hint`} className='text-xs text-muted-foreground'>
          {hint}
        </p>
      )}
      {error && (
        <p className='text-sm text-destructive' role='alert'>
          {error}
        </p>
      )}
    </div>
  )
}

/** The saved draft as guests would see it: phone or computer, light or dark. */
function FooterPreview({ frame, dirty }: { frame: number; dirty: boolean }) {
  const { t } = useTranslation()
  const [device, setDevice] = useState<'phone' | 'desktop'>('phone')
  const [scheme, setScheme] = useState<'light' | 'dark'>('light')
  const src = previewUrl(scheme, 'draft', frame)
  const title = t('platform.footer_editor.preview')
  return (
    <div className='min-w-0 lg:sticky lg:top-16 lg:self-start'>
      <Card>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          <CardDescription>
            {dirty
              ? t('platform.footer_editor.preview_unsaved')
              : t('platform.footer_editor.preview_hint')}
          </CardDescription>
        </CardHeader>
        <CardContent className='space-y-3'>
          <div className='flex flex-wrap gap-2'>
            <Choice
              label={t('platform.footer_editor.phone')}
              icon={<Smartphone aria-hidden />}
              on={device === 'phone'}
              onClick={() => setDevice('phone')}
            />
            <Choice
              label={t('platform.footer_editor.desktop')}
              icon={<Monitor aria-hidden />}
              on={device === 'desktop'}
              onClick={() => setDevice('desktop')}
            />
            <span className='w-2' />
            <Choice
              label={t('platform.footer_editor.light')}
              icon={<Sun aria-hidden />}
              on={scheme === 'light'}
              onClick={() => setScheme('light')}
            />
            <Choice
              label={t('platform.footer_editor.dark')}
              icon={<Moon aria-hidden />}
              on={scheme === 'dark'}
              onClick={() => setScheme('dark')}
            />
          </div>
          <div
            className={cn(device === 'phone' && 'mx-auto max-w-[390px]')}
            data-testid='footer-preview'
          >
            <ScaledFrame
              key={device}
              src={src}
              title={title}
              width={device === 'phone' ? 390 : 1280}
              height={device === 'phone' ? 780 : 640}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function Choice({
  label,
  icon,
  on,
  onClick,
}: {
  label: string
  icon: React.ReactNode
  on: boolean
  onClick: () => void
}) {
  return (
    <Button
      variant={on ? 'secondary' : 'ghost'}
      size='sm'
      className='min-h-11 sm:min-h-8'
      aria-pressed={on}
      onClick={onClick}
    >
      {icon}
      {label}
    </Button>
  )
}
