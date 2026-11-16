import { lazy, Suspense, useState } from 'react'
import { CircleHelp } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { type HelpSource } from './api'

// The sheet and the articles load only when someone asks for help.
const HelpSheet = lazy(() =>
  import('./help-sheet').then((m) => ({ default: m.HelpSheet }))
)

/** The "?" in the top bar: opens the article for this screen. */
export function HelpButton({ source }: { source: HelpSource | null }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  // Each opening starts again from this screen's article.
  const [opened, setOpened] = useState(0)
  if (!source) return null
  return (
    <>
      <Button
        variant='ghost'
        size='icon'
        className='size-11 sm:size-9'
        aria-label={t('help.this_page')}
        title={t('help.this_page')}
        onClick={() => {
          setOpened((n) => n + 1)
          setOpen(true)
        }}
      >
        <CircleHelp />
      </Button>
      {opened > 0 && (
        <Suspense fallback={null}>
          <HelpSheet
            key={opened}
            source={source}
            open={open}
            onOpenChange={setOpen}
          />
        </Suspense>
      )}
    </>
  )
}
