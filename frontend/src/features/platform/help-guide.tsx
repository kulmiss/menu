import { Link, useNavigate, useSearch } from '@tanstack/react-router'
import { PencilLine } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { HelpCentre } from '@/features/help/help-page'
import { PageTitle } from './layout'

/** Super Admin → Guide: setup and daily use (backend resources/help/platform). */
export function HelpGuidePage() {
  const { t } = useTranslation()
  const { a } = useSearch({ strict: false }) as { a?: string }
  const navigate = useNavigate()
  return (
    <>
      <PageTitle title={t('platform.nav.help')} hint={t('platform.help.hint')}>
        <Button asChild variant='outline' className='min-h-11 sm:min-h-9'>
          <Link to='/platform/$section' params={{ section: 'help-articles' }}>
            <PencilLine />
            {t('platform.nav.help-articles')}
          </Link>
        </Button>
      </PageTitle>
      <HelpCentre
        source={{ audience: 'platform' }}
        slug={a}
        onOpen={(slug) => {
          void navigate({
            to: '/platform/$section',
            params: { section: 'help' },
            search: slug ? { a: slug } : {},
          })
          window.scrollTo({ top: 0 })
        }}
      />
    </>
  )
}
