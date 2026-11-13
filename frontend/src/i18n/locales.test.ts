import common from '@/locales/en/common.json'
import platform from '@/locales/en/platform.json'
import screens from '@/locales/en/screens.json'
import { describe, expect, it } from 'vitest'

type Tree = { [key: string]: string | Tree }

const en = { ...common, ...screens, platform } as Tree

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
  it('English is the reviewed source language', () => {
    expect((en as Tree)._status).toBeUndefined()
  })
})

describe('translation keys used in code', () => {
  // Raw source of every module (tests excluded), read at build time by Vite.
  const sources = import.meta.glob(
    ['/src/**/*.{ts,tsx}', '!/src/**/*.test.*'],
    {
      query: '?raw',
      import: 'default',
      eager: true,
    }
  ) as Record<string, string>

  const english = new Set(keys(en as Tree))
  const KEY =
    /\bt\(\s*'([a-z_]+(?:\.[a-z_]+)+)'|i18nKey='([a-z_.]+)'|'((?:validation|errors)\.[a-z_.]+)'/g

  it('exist in the English file', () => {
    const missing: string[] = []
    let found = 0
    for (const [file, source] of Object.entries(sources)) {
      for (const match of source.matchAll(KEY)) {
        const key = match[1] ?? match[2] ?? match[3]
        found++
        if (!english.has(key)) missing.push(`${file}: ${key}`)
      }
    }
    expect(missing).toEqual([])
    // Guard against the scan silently matching nothing.
    expect(found).toBeGreaterThan(100)
  })
})
