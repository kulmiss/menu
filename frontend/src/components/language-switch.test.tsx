import { LANGUAGES } from '@/i18n'
import { describe, expect, it } from 'vitest'
import { render } from 'vitest-browser-react'
import { DirectionProvider } from '@/context/direction-provider'
import { LanguageSwitch } from './language-switch'

describe('LanguageSwitch', () => {
  it('stays hidden while English is the only language', async () => {
    expect(LANGUAGES.map((l) => l.code)).toEqual(['en'])

    const screen = await render(
      <DirectionProvider>
        <LanguageSwitch />
      </DirectionProvider>
    )

    expect(screen.container.querySelector('button')).toBeNull()
  })
})
