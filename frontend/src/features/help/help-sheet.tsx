import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useRouterState } from '@tanstack/react-router'
import { ArrowLeft, BookOpen } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { type HelpSource, articleFor, helpQuery } from './api'
import { ArticleBody, WasHelpful } from './article'

/** The article for the current screen, beside the page; links open more articles in place. */
export function HelpSheet({
  source,
  open,
  onOpenChange,
}: {
  source: HelpSource
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const location = useRouterState({ select: (s) => s.location })
  const q = useQuery(helpQuery(source))
  // Articles opened from links, for this screen only (the button remounts the sheet each time).
  const [trail, setTrail] = useState<string[]>([])

  const lib = q.data
  const here = lib
    ? articleFor(lib.articles, location.pathname, location.hash)
    : undefined
  const current = trail.length
    ? lib?.articles.find((a) => a.slug === trail[trail.length - 1])
    : here
  const all =
    source.audience === 'restaurant'
      ? { to: '/admin/help' as const }
      : { to: '/platform/$section' as const, params: { section: 'help' } }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='w-full overflow-y-auto sm:max-w-lg'>
        <SheetHeader>
          <SheetTitle>{current?.title ?? t('help.title')}</SheetTitle>
          <SheetDescription>
            {current?.summary ?? t('help.no_article')}
          </SheetDescription>
        </SheetHeader>
        <div className='px-4 pb-6'>
          {trail.length > 0 && (
            <Button
              variant='ghost'
              size='sm'
              className='-ms-2 min-h-11 sm:min-h-8'
              onClick={() => setTrail((x) => x.slice(0, -1))}
            >
              <ArrowLeft />
              {t('help.back')}
            </Button>
          )}
          {q.isPending ? (
            <Skeleton className='h-48 w-full' />
          ) : q.isError ? (
            <p role='alert'>{t('help.load_error')}</p>
          ) : current ? (
            <>
              <ArticleBody
                narrow
                article={current}
                onOpen={(slug) => setTrail((x) => [...x, slug])}
              />
              <WasHelpful
                source={source}
                slug={current.slug}
                voted={lib?.voted[current.slug]}
              />
            </>
          ) : null}
          <Button asChild variant='outline' className='mt-6 min-h-11 w-full'>
            <Link
              {...all}
              search={current ? { a: current.slug } : {}}
              onClick={() => onOpenChange(false)}
            >
              <BookOpen />
              {t('help.open_centre')}
            </Link>
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
