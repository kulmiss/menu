import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { type FooterContent, type FooterData, move } from './footer-data'
import { FooterEditor } from './footer-editor'

const draft = (): FooterContent => ({
  illustration: true,
  columns: [
    {
      id: 'product',
      title: 'Product',
      on: true,
      links: [
        {
          id: 'features',
          label: 'Features',
          page: 'features',
          url: '',
          on: true,
        },
        {
          id: 'pricing',
          label: 'Plans & prices',
          page: 'pricing',
          url: '',
          on: true,
        },
      ],
    },
    {
      id: 'legal',
      title: 'Legal',
      on: true,
      links: [
        { id: 'terms', label: 'Terms', page: 'terms', url: '', on: true },
      ],
    },
  ],
  contact: {
    on: true,
    title: 'Talk to us',
    whatsapp: '',
    phone: '',
    email: '',
    address: '',
    hours: '',
  },
  socials: { facebook: '', instagram: '' },
  app: { on: true, play_url: '' },
  trust: { on: false, text: 'Free plan forever' },
  bottom: { company: 'Menu', made_in: '', back_to_top: true },
})

const data = (over: Partial<FooterData> = {}): FooterData => ({
  draft: draft(),
  live: draft(),
  changed: false,
  pages: [
    { key: 'features', label: 'Features', path: '/#features' },
    { key: 'pricing', label: 'Plans & prices', path: '/#pricing' },
    { key: 'terms', label: 'Terms', path: '/terms' },
    { key: 'demo', label: 'See a demo menu', path: '/demo' },
  ],
  available: ['features', 'pricing', 'terms'],
  socials: ['facebook', 'instagram'],
  limits: { columns: 6, links: 12 },
  versions: [],
  ...over,
})

const answer = (d: FooterData) => ({ data: { data: d } })
let fake: ReturnType<typeof fakeApi>

describe('Super Admin → Footer', () => {
  afterEach(() => fake?.restore())

  it('moves an item up or down and keeps the list when it cannot move', () => {
    expect(move([1, 2, 3], 0, 1)).toEqual([2, 1, 3])
    expect(move([1, 2, 3], 2, -1)).toEqual([1, 3, 2])
    expect(move([1, 2, 3], 0, -1)).toEqual([1, 2, 3])
    expect(move([1, 2, 3], 2, 1)).toEqual([1, 2, 3])
  })

  it('saves texts, switches, the column order and a new link to a web address', async () => {
    fake = fakeApi({
      'PUT /super/footer': (r) =>
        answer(data({ draft: JSON.parse(r.body as string), changed: true })),
    })
    const screen = await renderWithQuery(<FooterEditor data={data()} />)

    // Nothing to save or publish yet.
    await expect
      .element(screen.getByRole('button', { name: 'Save draft' }))
      .toBeDisabled()
    await expect
      .element(screen.getByRole('button', { name: 'Publish' }))
      .toBeDisabled()

    await userEvent.fill(
      screen.getByLabelText('Made in …'),
      'Made in Addis Ababa'
    )
    await userEvent.click(screen.getByRole('switch', { name: 'Picture band' }))
    await userEvent.click(screen.getByRole('button', { name: 'Move Legal up' }))
    await userEvent.click(
      screen.getByRole('button', { name: 'Add a link to Product' })
    )
    const links = screen.getByLabelText('Link text')
    await userEvent.fill(links.nth(3), 'Our blog')
    await userEvent.click(screen.getByLabelText('Goes to').nth(3))
    await userEvent.click(
      screen.getByRole('option', { name: 'A web address…' })
    )
    await userEvent.fill(
      screen.getByLabelText('Web address'),
      'https://blog.example.com'
    )
    await expect
      .element(screen.getByTestId('footer-state'))
      .toHaveTextContent('not saved yet')
    await userEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toMatchObject({
        illustration: false,
        bottom: { made_in: 'Made in Addis Ababa' },
        columns: [
          { id: 'legal' },
          {
            id: 'product',
            links: [
              { id: 'features' },
              { id: 'pricing' },
              {
                label: 'Our blog',
                page: null,
                url: 'https://blog.example.com',
                on: true,
              },
            ],
          },
        ],
      })
    await expect
      .element(screen.getByTestId('footer-state'))
      .toHaveTextContent('Draft saved')
  })

  it('lists only the pages that exist, and shows the server’s message under the field', async () => {
    fake = fakeApi({
      'PUT /super/footer': {
        status: 422,
        data: {
          message: 'x',
          errors: {
            'columns.0.links.0.url': [
              'Use a safe link: https://, mailto: or tel:.',
            ],
          },
        },
      },
    })
    const screen = await renderWithQuery(<FooterEditor data={data()} />)

    await userEvent.click(screen.getByLabelText('Goes to').first())
    await expect
      .element(screen.getByRole('option', { name: 'Terms' }))
      .toBeVisible()
    expect(
      screen.getByRole('option', { name: 'See a demo menu' }).elements()
    ).toHaveLength(0)
    await userEvent.click(
      screen.getByRole('option', { name: 'A web address…' })
    )
    await userEvent.fill(
      screen.getByLabelText('Web address'),
      'javascript:alert(1)'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    await expect
      .element(screen.getByText('Use a safe link: https://, mailto: or tel:.'))
      .toBeVisible()
  })

  it('removes a column, publishes (saving first), and restores an old version in one click', async () => {
    const versions = [
      {
        id: 2,
        at: '2026-11-18T10:00:00Z',
        by: 'Owner',
        restored_from: null,
        live: true,
      },
      {
        id: 1,
        at: '2026-11-17T10:00:00Z',
        by: 'Owner',
        restored_from: null,
        live: false,
      },
    ]
    fake = fakeApi({
      'PUT /super/footer': (r) =>
        answer(
          data({ draft: JSON.parse(r.body as string), changed: true, versions })
        ),
      'POST /super/footer/publish': answer(
        data({
          versions: [
            { ...versions[0], id: 3 },
            { ...versions[0], live: false },
            versions[1],
          ],
        })
      ),
      'POST /super/footer/versions/1/restore': answer(data({ versions })),
    })
    const screen = await renderWithQuery(
      <FooterEditor data={data({ versions })} />
    )

    await userEvent.click(
      screen.getByRole('button', { name: 'Remove the column Legal' })
    )
    await userEvent.click(screen.getByRole('button', { name: 'Publish' }))
    await expect
      .poll(() => fake.apiCalls().map((c) => `${c.method} ${c.url}`))
      .toEqual(['PUT /super/footer', 'POST /super/footer/publish'])
    expect(
      (
        fake.calls.find((c) => c.method === 'PUT')
          ?.data as unknown as FooterContent
      ).columns.map((c) => c.id)
    ).toEqual(['product'])
    await expect.element(screen.getByText('Version 3')).toBeVisible()

    await userEvent.click(
      screen.getByRole('button', { name: 'Restore version 1' })
    )
    await expect
      .poll(() => fake.apiCalls().slice(-1)[0]?.url)
      .toBe('/super/footer/versions/1/restore')
  })

  it('previews the saved draft on a phone or a computer, light or dark', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(<FooterEditor data={data()} />)
    const frame = () =>
      screen.getByTestId('footer-preview').element().querySelector('iframe')!

    await expect
      .poll(() => frame()?.getAttribute('src'))
      .toContain('scheme=light&source=draft')
    expect(frame().style.width).toBe('390px')
    await userEvent.click(screen.getByRole('button', { name: 'Dark' }))
    await userEvent.click(screen.getByRole('button', { name: 'Computer' }))
    await expect
      .poll(() => frame()?.getAttribute('src'))
      .toContain('scheme=dark')
    await expect.poll(() => frame()?.style.width).toBe('1280px')
    await expect
      .element(screen.getByRole('button', { name: 'Computer' }))
      .toHaveAttribute('aria-pressed', 'true')
  })
})
