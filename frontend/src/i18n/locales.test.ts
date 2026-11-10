import am from '@/locales/am/common.json'
import ar from '@/locales/ar/common.json'
import en from '@/locales/en/common.json'
import so from '@/locales/so/common.json'
import { describe, expect, it } from 'vitest'

type Tree = { [key: string]: string | Tree }

function keys(tree: Tree, prefix = ''): string[] {
  return Object.entries(tree)
    .filter(([key]) => key !== '_status')
    .flatMap(([key, value]) =>
      typeof value === 'string'
        ? [`${prefix}${key}`]
        : keys(value, `${prefix}${key}.`)
    )
    .sort()
}

describe('locale files', () => {
  const english = keys(en as Tree)

  it.each([
    ['so', so],
    ['am', am],
    ['ar', ar],
  ])('%s has exactly the English keys', (_code, locale) => {
    expect(keys(locale as Tree)).toEqual(english)
  })

  it('English is the reviewed source language', () => {
    expect((en as Tree)._status).toBeUndefined()
  })
})
