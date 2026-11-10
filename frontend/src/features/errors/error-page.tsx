import { useNavigate, useRouter } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

type ErrorPageProps = {
  code?: string
  /** i18n keys under `errors.page.<kind>` (title + text). */
  kind: 'not_found' | 'forbidden' | 'general'
  className?: string
}

export function ErrorPage({ code, kind, className }: ErrorPageProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { history } = useRouter()
  return (
    <div className={cn('min-h-svh w-full', className)}>
      <main
        id='content'
        className='m-auto flex min-h-svh w-full flex-col items-center justify-center gap-2 p-6 text-center'
      >
        {code && (
          <h1 className='text-[7rem] leading-tight font-bold'>{code}</h1>
        )}
        <span className='font-medium'>{t(`errors.page.${kind}.title`)}</span>
        <p className='max-w-sm text-muted-foreground'>
          {t(`errors.page.${kind}.text`)}
        </p>
        <div className='mt-6 flex gap-4'>
          <Button
            variant='outline'
            className='min-h-11 sm:min-h-9'
            onClick={() => history.go(-1)}
          >
            {t('errors.go_back')}
          </Button>
          <Button
            className='min-h-11 sm:min-h-9'
            onClick={() => navigate({ to: '/admin' })}
          >
            {t('errors.home')}
          </Button>
        </div>
      </main>
    </div>
  )
}

export const NotFoundError = () => <ErrorPage code='404' kind='not_found' />
export const GeneralError = () => <ErrorPage code='500' kind='general' />
