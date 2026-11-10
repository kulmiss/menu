import { Check, Languages } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import {
  isDraftLanguage,
  LANGUAGES,
  languageDir,
  persistLanguage,
  type LanguageCode,
} from '@/i18n'
import { cn } from '@/lib/utils'
import { useDirection } from '@/context/direction-provider'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export function LanguageSwitch() {
  const { t, i18n } = useTranslation()
  const { setDir } = useDirection()

  const choose = (code: LanguageCode) => {
    void i18n.changeLanguage(code)
    persistLanguage(code)
    setDir(languageDir(code))
  }

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          variant='ghost'
          size='icon'
          className='size-11 rounded-full sm:size-9'
          aria-label={t('language.label')}
        >
          <Languages className='size-[1.2rem]' />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end'>
        {LANGUAGES.map(({ code }) => (
          <DropdownMenuItem
            key={code}
            lang={code}
            className='min-h-11 sm:min-h-8'
            onClick={() => choose(code)}
          >
            {t(`language.${code}`)}
            {isDraftLanguage(code) && (
              <span className='text-xs text-muted-foreground'>
                ({t('language.draft')})
              </span>
            )}
            <Check
              size={14}
              className={cn('ms-auto', i18n.language !== code && 'hidden')}
            />
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
