// Every control in the shared UI kit can be tapped in a 44 × 44 px area on a
// phone (< 640 px), and keeps its compact desktop size from 640 px up.
// Bug (2026-11-17): on phones inputs, selects, buttons and tabs were 32–36 px
// tall and switches/checkboxes 16–18 px, across the whole admin.
import '@/styles/index.css'
import { afterEach, describe, expect, it } from 'vitest'
import { render } from 'vitest-browser-react'
import { page } from 'vitest/browser'
import { PasswordInput } from '@/components/password-input'
import { Button } from './button'
import { Checkbox } from './checkbox'
import { Dialog, DialogContent, DialogTitle } from './dialog'
import { Input } from './input'
import { RadioGroup, RadioGroupItem } from './radio-group'
import { Select, SelectTrigger, SelectValue } from './select'
import { Switch } from './switch'
import { Tabs, TabsList, TabsTrigger } from './tabs'

/** True when a finger 21 px from the centre in every direction still lands on `e`. */
function tappable(e: Element) {
  e.scrollIntoView({ block: 'center' })
  const r = e.getBoundingClientRect()
  const cx = r.left + r.width / 2
  const cy = r.top + r.height / 2
  return [
    [0, -21],
    [0, 21],
    [-21, 0],
    [21, 0],
  ].every(([dx, dy]) => {
    const hit = document.elementFromPoint(cx + dx, cy + dy)
    return hit !== null && (hit === e || e.contains(hit))
  })
}

function Kit() {
  return (
    <div className='grid gap-8 p-8'>
      <Button>Save</Button>
      <Button size='sm'>Small</Button>
      <Button size='lg'>Large</Button>
      <Button size='icon' aria-label='More'>
        +
      </Button>
      <Input aria-label='Name' />
      <PasswordInput aria-label='Password' />
      <Select>
        <SelectTrigger aria-label='Kind'>
          <SelectValue placeholder='Pick' />
        </SelectTrigger>
      </Select>
      <Switch aria-label='Available' />
      <Checkbox aria-label='Select row' />
      <RadioGroup aria-label='Size'>
        <RadioGroupItem value='a' aria-label='Small size' />
      </RadioGroup>
      <Tabs defaultValue='a'>
        <TabsList>
          <TabsTrigger value='a'>One</TabsTrigger>
          <TabsTrigger value='b'>Two</TabsTrigger>
        </TabsList>
      </Tabs>
    </div>
  )
}
const controls = () => [
  ...document.querySelectorAll(
    'button, input, [role=switch], [role=checkbox], [role=radio], [role=tab], [role=combobox]'
  ),
]
const name = (e: Element) =>
  e.getAttribute('aria-label') || e.textContent || e.tagName

afterEach(() => page.viewport(414, 896))

describe('tap targets', () => {
  it('on a 360 px phone every control has a 44 × 44 px tap area', async () => {
    await page.viewport(360, 800)
    await render(<Kit />)
    const all = controls()
    expect(all.length).toBeGreaterThanOrEqual(13)
    for (const e of all) expect(tappable(e), name(e)).toBe(true)
  })

  it('the dialog close button has a 44 × 44 px tap area on a phone', async () => {
    await page.viewport(390, 800)
    await render(
      <Dialog open>
        <DialogContent aria-describedby={undefined}>
          <DialogTitle>Edit dish</DialogTitle>
        </DialogContent>
      </Dialog>
    )
    const close = document.querySelector('[data-slot=dialog-close]')!
    // after the open animation
    await expect.poll(() => tappable(close)).toBe(true)
  })

  it('from 640 px up the controls keep their compact size', async () => {
    await page.viewport(1280, 800)
    await render(<Kit />)
    const h = (sel: string) =>
      Math.round(document.querySelector(sel)!.getBoundingClientRect().height)
    expect(h('input[aria-label=Name]')).toBe(36)
    expect(h('button:not([role])')).toBe(36)
    expect(h('[role=combobox]')).toBe(36)
    expect(h('[role=switch]')).toBe(18)
    expect(h('[role=checkbox]')).toBe(16)
  })
})
