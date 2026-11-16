import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Check, CircleAlert, ListChecks, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useSetupWizard } from '@/stores/setup-wizard-store'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { send } from './api'
import { CHECK_LINK, setupQ } from './setup-api'

/** Dashboard card: what the setup still needs (backend QUESTIONS #195). */
export default function SetupChecklist() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const open = useSetupWizard((s) => s.setOpen)
  const q = useQuery(setupQ())
  const s = q.data
  if (!s || s.card_hidden || s.missing === 0) return null
  const todo = s.checks.filter((c) => !c.done)
  return (
    <Card className='mb-4 border-amber-300 dark:border-amber-800'>
      <CardHeader className='flex flex-row items-start justify-between gap-2'>
        <div>
          <CardTitle className='flex items-center gap-2'>
            <ListChecks className='size-5' aria-hidden />
            {t('platform.setup.card.title')}
          </CardTitle>
          <p className='text-sm text-muted-foreground'>
            {t('platform.setup.card.hint', { count: todo.length })}
          </p>
        </div>
        <Button
          variant='ghost'
          size='icon'
          className='size-11 sm:size-9'
          aria-label={t('platform.setup.card.hide')}
          onClick={async () => {
            await send('put', 'setup', { card_hidden: true })
            await qc.invalidateQueries({ queryKey: setupQ().queryKey })
          }}
        >
          <X />
        </Button>
      </CardHeader>
      <CardContent className='grid gap-3'>
        <ul className='grid gap-1 sm:grid-cols-2'>
          {s.checks.map((c) => (
            <li key={c.key}>
              <a
                href={CHECK_LINK[c.key]}
                className='flex min-h-11 items-center gap-2 rounded-md px-2 hover:bg-accent'
              >
                {c.done ? (
                  <Check className='size-4 text-emerald-600' aria-hidden />
                ) : (
                  <CircleAlert className='size-4 text-amber-600' aria-hidden />
                )}
                <span
                  className={c.done ? 'text-muted-foreground line-through' : ''}
                >
                  <span className='sr-only'>
                    {c.done
                      ? t('platform.setup.card.done')
                      : t('platform.setup.card.todo')}{' '}
                  </span>
                  {t(`platform.setup.checks.${c.key}`)}
                  {c.optional && !c.done && (
                    <span className='text-muted-foreground'>
                      {' '}
                      · {t('platform.setup.card.optional')}
                    </span>
                  )}
                </span>
              </a>
            </li>
          ))}
        </ul>
        <div className='flex flex-wrap gap-2'>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={() => open(true)}
          >
            {t('platform.setup.card.open')}
          </Button>
          <Button asChild variant='link' className='min-h-11 sm:min-h-9'>
            <Link
              to='/platform/$section'
              params={{ section: 'help' }}
              search={{ a: 'go-live-checklist' }}
            >
              {t('platform.setup.card.guide')}
            </Link>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
