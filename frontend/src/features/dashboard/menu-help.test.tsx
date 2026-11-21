import { fakeApi, invalid } from '@/test-utils/fake-api'
import { meFixture, tenantFixture } from '@/test-utils/fixtures'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { userEvent } from 'vitest/browser'
import MenuHelp from './menu-help'

vi.mock('@tanstack/react-router', async (orig) =>
  (await import('@/test-utils/router-mock')).routerMock(await orig())
)

let fake: ReturnType<typeof fakeApi>
const me = meFixture({ tenants: [tenantFixture()] })
/** A tiny real PNG (1 × 1), so the browser can read and shrink it. */
const png = () =>
  new File(
    [
      Uint8Array.from(
        atob(
          'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ),
        (c) => c.charCodeAt(0)
      ),
    ],
    'page 1.png',
    { type: 'image/png' }
  )

describe('Add my menu for me (QUESTIONS #235)', () => {
  afterEach(() => fake.restore())

  it('sends photos and a note, shrunk to JPEG, then shows that we got it', async () => {
    let posted: FormData | null = null
    fake = fakeApi({
      'GET /restaurant/1/menu-requests': {
        data: { data: { enabled: true, requests: [] } },
      },
      'POST /restaurant/1/menu-requests': (req) => {
        posted = req.body as FormData
        return { status: 201, data: { data: { id: 5, status: 'new' } } }
      },
    })
    const screen = await renderWithQuery(<MenuHelp tenantId={1} />, { me })

    await userEvent.click(
      screen.getByRole('button', { name: 'Send photos of my menu' })
    )
    await userEvent.click(screen.getByRole('button', { name: /Send 0 files/ }))
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('Choose at least one photo or PDF.')

    await userEvent.upload(
      screen.getByLabelText('Photos or PDF of your menu'),
      [png(), new File(['%PDF-1.4'], 'drinks.pdf', { type: 'application/pdf' })]
    )
    await expect.element(screen.getByText('page 1.png')).toBeVisible()
    await userEvent.fill(
      screen.getByLabelText(/Anything we should know/),
      'Drinks on the back'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Send 2 files' }))
    await expect.poll(() => posted !== null).toBe(true)
    const files = posted!.getAll('files[]') as File[]
    expect(files.map((f) => [f.name, f.type])).toEqual([
      ['page 1.jpg', 'image/jpeg'],
      ['drinks.pdf', 'application/pdf'],
    ])
    expect(posted!.get('note')).toBe('Drinks on the back')
  })

  it('shows the state of an open request, the server message on errors, and hides when switched off or done', async () => {
    fake = fakeApi({
      'GET /restaurant/1/menu-requests': {
        data: {
          data: {
            enabled: true,
            requests: [
              {
                id: 2,
                status: 'working',
                files: 3,
                note: null,
                created_at: '2026-11-19T08:00:00+03:00',
              },
            ],
          },
        },
      },
      'POST /restaurant/1/menu-requests': invalid({
        files: 'You already sent 3 menus we are still working on.',
      }),
    })
    const screen = await renderWithQuery(<MenuHelp tenantId={1} />, { me })
    await expect
      .element(screen.getByRole('status'))
      .toHaveTextContent('We are typing in your menu now.')
    await userEvent.click(
      screen.getByRole('button', { name: 'Send photos of my menu' })
    )
    await userEvent.upload(
      screen.getByLabelText('Photos or PDF of your menu'),
      [png()]
    )
    await userEvent.click(screen.getByRole('button', { name: 'Send 1 file' }))
    await expect
      .element(screen.getByRole('alert'))
      .toHaveTextContent('You already sent 3 menus')
    fake.restore()

    for (const data of [
      { enabled: false, requests: [] },
      {
        enabled: true,
        requests: [
          {
            id: 3,
            status: 'done',
            files: 1,
            note: null,
            created_at: '2026-11-19T08:00:00+03:00',
          },
        ],
      },
    ]) {
      fake = fakeApi({ 'GET /restaurant/1/menu-requests': { data: { data } } })
      const hidden = await renderWithQuery(<MenuHelp tenantId={1} />, { me })
      await new Promise((r) => setTimeout(r, 300))
      expect(hidden.container.textContent).not.toContain('Add my menu for me')
      fake.restore()
    }
    fake = fakeApi({})
  })
})
