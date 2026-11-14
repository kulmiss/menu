import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import {
  CreditCard,
  FileText,
  Loader2,
  Store,
  Tag,
  UserRound,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

type Hit = {
  id: number
  title: string
  subtitle: string | null
  tenant_id: number | null
  color?: string
}
type Results = Record<
  'restaurants' | 'owners' | 'invoices' | 'payments' | 'tags',
  Hit[]
>

const GROUPS = [
  ['restaurants', Store],
  ['owners', UserRound],
  ['invoices', FileText],
  ['payments', CreditCard],
  ['tags', Tag],
] as const

/** Super Admin search: Ctrl/⌘ + K (owner, 2026-11-13). Loaded on first use. */
export default function GlobalSearch({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const [debounced, setDebounced] = useState('')
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(q.trim()), 200)
    return () => clearTimeout(timer)
  }, [q])
  const results = useQuery({
    queryKey: ['platform', 'search', debounced],
    queryFn: async () =>
      (
        await api.get<{ data: Results }>(
          `/platform/search?q=${encodeURIComponent(debounced)}`
        )
      ).data.data,
    enabled: debounced.length >= 2,
    staleTime: 30_000,
    placeholderData: (prev) => prev,
  })
  const data = debounced.length >= 2 ? results.data : undefined
  const empty = data && GROUPS.every(([key]) => (data[key] ?? []).length === 0)

  function go(group: (typeof GROUPS)[number][0], hit: Hit) {
    onOpenChange(false)
    setQ('')
    if (group === 'tags') {
      void navigate({
        to: '/platform/$section',
        params: { section: 'restaurants' },
        search: { tag: hit.id },
      })
    } else if (hit.tenant_id) {
      void navigate({
        to: '/platform/$section',
        params: { section: 'restaurants' },
        search: { open: hit.tenant_id },
      })
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogHeader className='sr-only'>
        <DialogTitle>{t('platform.search.title')}</DialogTitle>
        <DialogDescription>{t('platform.search.hint')}</DialogDescription>
      </DialogHeader>
      <DialogContent className='overflow-hidden p-0' showCloseButton={false}>
        <Command
          shouldFilter={false}
          className='**:data-[slot=command-input-wrapper]:h-12 **:[[cmdk-group-heading]]:px-2 **:[[cmdk-group-heading]]:text-muted-foreground **:[[cmdk-item]]:py-2.5'
        >
          <CommandInput
            value={q}
            onValueChange={setQ}
            placeholder={t('platform.search.placeholder')}
            aria-label={t('platform.search.title')}
          />
          <CommandList className='max-h-[60vh]'>
            {debounced.length < 2 ? (
              <p className='p-4 text-sm text-muted-foreground'>
                {t('platform.search.type')}
              </p>
            ) : results.isFetching && !data ? (
              <p className='flex items-center gap-2 p-4 text-sm text-muted-foreground'>
                <Loader2 className='size-4 animate-spin' />{' '}
                {t('platform.search.searching')}
              </p>
            ) : empty ? (
              <CommandEmpty>
                {t('platform.search.none', { q: debounced })}
              </CommandEmpty>
            ) : null}
            {data &&
              GROUPS.map(([key, Icon]) =>
                (data[key] ?? []).length === 0 ? null : (
                  <CommandGroup key={key} heading={t(`platform.search.${key}`)}>
                    {data[key].map((hit) => (
                      <CommandItem
                        key={`${key}-${hit.id}`}
                        value={`${key}-${hit.id}`}
                        onSelect={() => go(key, hit)}
                        className='min-h-11'
                      >
                        <Icon
                          className='text-muted-foreground'
                          style={hit.color ? { color: hit.color } : undefined}
                          aria-hidden
                        />
                        <span className='flex min-w-0 flex-col'>
                          <span className='truncate font-medium'>
                            {hit.title}
                          </span>
                          {hit.subtitle && (
                            <span className='truncate text-xs text-muted-foreground'>
                              {hit.subtitle}
                            </span>
                          )}
                        </span>
                      </CommandItem>
                    ))}
                  </CommandGroup>
                )
              )}
          </CommandList>
        </Command>
      </DialogContent>
    </Dialog>
  )
}
