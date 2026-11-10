import { Trans, useTranslation } from 'react-i18next'
import { Logo } from '@/assets/logo'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { LazyLanguageSwitch as LanguageSwitch } from '@/components/lazy-language-switch'
import { ThemeSwitch } from '@/components/theme-switch'

type AuthLayoutProps = {
  children: React.ReactNode
}

export function AuthLayout({ children }: AuthLayoutProps) {
  const { t } = useTranslation()
  return (
    <div className='relative container grid min-h-svh max-w-none items-center justify-center'>
      <div className='absolute end-2 top-2 flex items-center gap-1'>
        <LanguageSwitch />
        <ThemeSwitch />
      </div>
      <main
        id='content'
        className='mx-auto flex w-full flex-col justify-center space-y-2 py-14 sm:p-8'
      >
        <div className='mb-4 flex items-center justify-center'>
          <Logo className='me-2' />
          <h1 className='text-xl font-medium'>{t('app.name')}</h1>
        </div>
        {children}
      </main>
    </div>
  )
}

type AuthCardProps = {
  title: string
  description?: React.ReactNode
  children: React.ReactNode
  footer?: React.ReactNode
}

/** One auth screen: a card with a title, the form and an optional footer. */
export function AuthCard({
  title,
  description,
  children,
  footer,
}: AuthCardProps) {
  return (
    <AuthLayout>
      <Card className='w-full max-w-sm gap-4 sm:min-w-sm'>
        <CardHeader>
          <CardTitle className='text-lg tracking-tight'>{title}</CardTitle>
          {description && <CardDescription>{description}</CardDescription>}
        </CardHeader>
        <CardContent>{children}</CardContent>
        {footer && <CardFooter className='justify-center'>{footer}</CardFooter>}
      </Card>
    </AuthLayout>
  )
}

const linkClass = 'underline underline-offset-4 hover:text-primary'

/** "By continuing you agree to the Terms and Privacy Policy." */
export function LegalNote() {
  return (
    <p className='px-4 text-center text-sm text-balance text-muted-foreground'>
      <Trans
        i18nKey='auth.legal'
        components={{
          terms: <a href='/terms' className={linkClass} />,
          privacy: <a href='/privacy' className={linkClass} />,
        }}
      />
    </p>
  )
}

export const authLinkClass = `text-nowrap ${linkClass}`
