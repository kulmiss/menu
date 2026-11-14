import { ExternalLink, Mail, MessageCircle, Sparkles } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { useCurrentTenant } from '@/features/auth/use-me'

/** What a screen the plan doesn't include shows instead (QUESTIONS #184). */
export function UpgradePage({
  feature,
}: {
  feature: 'insights' | 'promotions'
}) {
  const { t } = useTranslation()
  const up = useCurrentTenant()?.upgrade
  return (
    <>
      <AdminHeader />
      <Main>
        <Card className='mx-auto mt-6 max-w-xl'>
          <CardHeader>
            <Sparkles className='size-6 text-primary' aria-hidden />
            <CardTitle className='text-xl'>
              {t(`upgrade.title.${feature}`)}
            </CardTitle>
            <CardDescription className='text-base'>
              {t(`upgrade.text.${feature}`)}
            </CardDescription>
          </CardHeader>
          <CardContent className='grid gap-3'>
            <p className='text-sm text-muted-foreground'>
              {t('upgrade.contact')}
            </p>
            <div className='flex flex-wrap gap-2'>
              {up?.whatsapp && (
                <Button asChild className='min-h-11'>
                  <a href={up.whatsapp} target='_blank' rel='noopener'>
                    <MessageCircle aria-hidden /> {t('upgrade.whatsapp')}
                  </a>
                </Button>
              )}
              {up?.email && (
                <Button
                  asChild
                  variant={up.whatsapp ? 'outline' : 'default'}
                  className='min-h-11'
                >
                  <a href={`mailto:${up.email}`}>
                    <Mail aria-hidden /> {t('upgrade.email')}
                  </a>
                </Button>
              )}
              <Button asChild variant='ghost' className='min-h-11'>
                <a href='/#pricing' target='_blank' rel='noopener'>
                  <ExternalLink aria-hidden /> {t('upgrade.plans')}
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </Main>
    </>
  )
}
