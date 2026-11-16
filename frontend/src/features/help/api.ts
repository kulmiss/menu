import {
  queryOptions,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { api, tenantUrl } from '@/lib/api'

/** Help centre (backend QUESTIONS #194). */
export type HelpAudience = 'restaurant' | 'platform'

export type HelpArticle = {
  slug: string
  topic: string
  title: string
  summary: string
  pages: string[]
  app: string[]
  keywords: string[]
  body: string
  html: string
  updated_at: string | null
}

export type HelpLibrary = {
  topics: { key: string; title: string }[]
  articles: HelpArticle[]
  voted: Record<string, boolean>
}

/** Where a reader's articles come from: one restaurant, or the Super Admin guide. */
export type HelpSource =
  { audience: 'restaurant'; tenantId: number } | { audience: 'platform' }

export const helpKey = (s: HelpSource) =>
  s.audience === 'restaurant'
    ? (['tenant', s.tenantId, 'help'] as const)
    : (['platform', 'help'] as const)

export const helpQuery = (s: HelpSource) =>
  queryOptions({
    queryKey: helpKey(s),
    queryFn: async () =>
      (
        await api.get<{ data: HelpLibrary }>(
          s.audience === 'restaurant'
            ? tenantUrl(s.tenantId, 'help')
            : '/platform/help'
        )
      ).data.data,
    staleTime: 5 * 60_000,
  })

export function useHelpFeedback(s: HelpSource) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { slug: string; helpful: boolean }) =>
      api.post('/help/feedback', { audience: s.audience, ...v }),
    onSuccess: (_, v) =>
      qc.setQueryData<HelpLibrary>(helpKey(s), (old) =>
        old ? { ...old, voted: { ...old.voted, [v.slug]: v.helpful } } : old
      ),
  })
}

/** The article for a screen: the longest `pages` entry that matches the path (and #tab). */
export function articleFor(
  articles: HelpArticle[],
  path: string,
  hash = ''
): HelpArticle | undefined {
  const here = path.replace(/\/+$/, '') || '/'
  let best: { a: HelpArticle; score: number } | undefined
  for (const a of articles) {
    for (const p of a.pages) {
      const [pagePath, pageHash] = p.split('#')
      const clean = pagePath.replace(/\/+$/, '') || '/'
      const samePage =
        here === clean || (clean !== '/admin' && here.startsWith(clean + '/'))
      if (!samePage) continue
      if (pageHash && pageHash !== hash.replace(/^#/, '')) continue
      const score = clean.length * 2 + (pageHash ? 1 : 0)
      if (!best || score > best.score) best = { a, score }
    }
  }
  return best?.a
}

const words = (s: string) =>
  s
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
    .split(/\s+/)
    .filter(Boolean)

/** Every word must appear somewhere; titles and keywords count most. */
export function searchArticles(
  articles: HelpArticle[],
  query: string
): HelpArticle[] {
  const terms = words(query)
  if (terms.length === 0) return articles
  const scored = articles.flatMap((a) => {
    const title = a.title.toLowerCase()
    const keys = a.keywords.join(' ').toLowerCase()
    const summary = a.summary.toLowerCase()
    const body = a.body.toLowerCase()
    let score = 0
    for (const w of terms) {
      const s =
        (title.includes(w) ? 6 : 0) +
        (keys.includes(w) ? 4 : 0) +
        (summary.includes(w) ? 2 : 0) +
        (body.includes(w) ? 1 : 0)
      if (s === 0) return []
      score += s
    }
    return [{ a, score }]
  })
  return scored.sort((x, y) => y.score - x.score).map((x) => x.a)
}
