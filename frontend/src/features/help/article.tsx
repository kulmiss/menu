import { ThumbsDown, ThumbsUp } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { type HelpArticle, type HelpSource, useHelpFeedback } from './api'
import './help.css'

/**
 * One article. The HTML comes from our server (Markdown with raw HTML
 * escaped), so it is safe to insert. `help:slug` links open another article;
 * screenshots come as a phone and a desktop picture, and only the one that
 * fits the screen is shown.
 */
export function ArticleBody({
  article,
  onOpen,
  narrow = false,
}: {
  article: HelpArticle
  onOpen: (slug: string) => void
  /** In the side sheet: always the phone picture. */
  narrow?: boolean
}) {
  return (
    <div
      className={narrow ? 'help-prose help-preview' : 'help-prose'}
      onClick={(e) => {
        const a = (e.target as HTMLElement).closest('a')
        const href = a?.getAttribute('href') ?? ''
        if (href.startsWith('help:')) {
          e.preventDefault()
          onOpen(href.slice(5))
        }
      }}
      // Server-rendered; raw HTML in articles is escaped (HelpRenderer::html).
      dangerouslySetInnerHTML={{ __html: article.html }}
    />
  )
}

/** "Was this helpful?" — one answer per person; the Super Admin sees the counts. */
export function WasHelpful({
  source,
  slug,
  voted,
}: {
  source: HelpSource
  slug: string
  voted: boolean | undefined
}) {
  const { t } = useTranslation()
  const send = useHelpFeedback(source)
  return (
    <div className='mt-8 flex flex-wrap items-center gap-3 border-t pt-4'>
      <p className='text-sm font-medium' id={`helpful-${slug}`}>
        {voted === undefined ? t('help.helpful') : t('help.thanks')}
      </p>
      <div
        className='flex gap-2'
        role='group'
        aria-labelledby={`helpful-${slug}`}
      >
        {([true, false] as const).map((yes) => (
          <Button
            key={String(yes)}
            variant={voted === yes ? 'default' : 'outline'}
            size='sm'
            className='min-h-11 sm:min-h-8'
            aria-pressed={voted === yes}
            disabled={send.isPending}
            onClick={() => send.mutate({ slug, helpful: yes })}
          >
            {yes ? <ThumbsUp /> : <ThumbsDown />}
            {yes ? t('help.yes') : t('help.no')}
          </Button>
        ))}
      </div>
    </div>
  )
}
