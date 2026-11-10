import i18n from '@/i18n'
import { afterEach, describe, expect, it } from 'vitest'
import { render } from 'vitest-browser-react'
import { userEvent } from 'vitest/browser'
import { DirectionProvider } from '@/context/direction-provider'
import { LanguageSwitch } from './language-switch'

describe('LanguageSwitch', () => {
  afterEach(async () => {
    await i18n.changeLanguage('en')
    document.documentElement.setAttribute('dir', 'ltr')
  })

  it('switches to Arabic and sets right-to-left direction', async () => {
    const screen = await render(
      <DirectionProvider>
        <LanguageSwitch />
      </DirectionProvider>
    )

    await userEvent.click(screen.getByRole('button', { name: 'Language' }))
    await userEvent.click(screen.getByText('Arabic'))

    expect(i18n.language).toBe('ar')
    await expect.poll(() => document.documentElement.dir).toBe('rtl')
    expect(document.documentElement.lang).toBe('ar')
  })

  it('marks machine-translated languages as drafts', async () => {
    const screen = await render(
      <DirectionProvider>
        <LanguageSwitch />
      </DirectionProvider>
    )

    await userEvent.click(screen.getByRole('button', { name: 'Language' }))

    await expect.element(screen.getByText('Somali')).toBeInTheDocument()
    expect(screen.getByText('(draft)').elements().length).toBe(3)
  })
})
