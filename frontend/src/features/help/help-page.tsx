import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, LifeBuoy, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  type HelpArticle,
  type HelpSource,
  helpQuery,
  searchArticles,
} from './api'
import { ArticleBody, WasHelpful } from './article'

/**
 * The Help centre: search, articles by topic, one article at a time. Used by
 * the restaurant admin (/admin/help) and the Super Admin guide (/platform/help).
 */
export function HelpCentre({
  source,
  slug,
  onOpen,
  extra,
}: {
  source: HelpSource
  slug?: string
  onOpen: (slug: string | undefined) => void
  extra?: React.ReactNode
}) {
  const { t } = useTranslation()
  const q = useQuery(helpQuery(source))
  const [query, setQuery] = useState('')
  const found = useMemo(
    () => (q.data ? searchArticles(q.data.articles, query) : []),
    [q.data, query]
  )

  if (q.isPending) {
    return (
      <div className='grid gap-3'>
        <Skeleton className='h-10 w-full max-w-xl' />
        <Skeleton className='h-40 w-full' />
      </div>
    )
  }
  if (q.isError) {
    return (
      <div role='alert' className='rounded-md border p-4 text-sm'>
        {t('help.load_error')}{' '}
        <Button variant='link' className='px-0' onClick={() => q.refetch()}>
          {t('help.try_again')}
        </Button>
      </div>
    )
  }
  const lib = q.data
  const article = slug ? lib.articles.find((a) => a.slug === slug) : undefined

  if (slug) {
    return (
      <div className='max-w-3xl'>
        <Button
          variant='ghost'
          size='sm'
          className='-ms-2 mb-2 min-h-11 sm:min-h-8'
          onClick={() => onOpen(undefined)}
        >
          <ArrowLeft />
          {t('help.all_articles')}
        </Button>
        {article ? (
          <article aria-labelledby='help-title'>
            <p className='text-sm text-muted-foreground'>
              {lib.topics.find((x) => x.key === article.topic)?.title}
            </p>
            <h1 id='help-title' className='text-2xl font-bold tracking-tight'>
              {article.title}
            </h1>
            <ArticleBody article={article} onOpen={onOpen} />
            <WasHelpful
              source={source}
              slug={article.slug}
              voted={lib.voted[article.slug]}
            />
          </article>
        ) : (
          <p>{t('help.not_found')}</p>
        )}
      </div>
    )
  }

  const byTopic = lib.topics
    .map((topic) => ({
      ...topic,
      list: found.filter((a) => a.topic === topic.key),
    }))
    .filter((x) => x.list.length > 0)

  return (
    <div className='grid gap-6'>
      <div className='flex flex-wrap items-center gap-3'>
        <label className='relative w-full max-w-xl'>
          <span className='sr-only'>{t('help.search')}</span>
          <Search
            className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground'
            aria-hidden
          />
          <Input
            type='search'
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t('help.search')}
            className='min-h-11 ps-9'
          />
        </label>
        {extra}
      </div>
      {query.trim() !== '' && (
        <p className='text-sm text-muted-foreground' role='status'>
          {t('help.results', { count: found.length })}
        </p>
      )}
      {found.length === 0 ? (
        <div className='rounded-lg border p-6 text-center'>
          <LifeBuoy className='mx-auto mb-2 size-6 text-muted-foreground' />
          <p className='font-medium'>{t('help.nothing')}</p>
          <Button
            variant='link'
            onClick={() => {
              const contact = lib.articles.find(
                (a) => a.slug === 'contact-support'
              )
              onOpen(contact?.slug)
            }}
          >
            {t('help.contact')}
          </Button>
        </div>
      ) : (
        <div className='grid gap-6 lg:grid-cols-2'>
          {byTopic.map((topic) => (
            <section key={topic.key} aria-labelledby={`topic-${topic.key}`}>
              <h2
                id={`topic-${topic.key}`}
                className='mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase'
              >
                {topic.title}
              </h2>
              <ul className='divide-y rounded-lg border'>
                {topic.list.map((a) => (
                  <ArticleLink key={a.slug} article={a} onOpen={onOpen} />
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </div>
  )
}

function ArticleLink({
  article,
  onOpen,
}: {
  article: HelpArticle
  onOpen: (slug: string) => void
}) {
  return (
    <li>
      <button
        type='button'
        className='block min-h-11 w-full px-4 py-3 text-start hover:bg-accent focus-visible:bg-accent focus-visible:outline-none'
        onClick={() => onOpen(article.slug)}
      >
        <span className='block font-medium'>{article.title}</span>
        {article.summary && (
          <span className='block text-sm text-muted-foreground'>
            {article.summary}
          </span>
        )}
      </button>
    </li>
  )
}
