import { renderWithQuery } from '@/test-utils/render'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { page, userEvent } from 'vitest/browser'
import { type Col, DataTable, type Page } from '.'

type Row = { id: number; name: string; status: 'open' | 'closed'; n: number }
const rows: Row[] = Array.from({ length: 30 }, (_, i) => ({
  id: i + 1,
  name: `Cafe ${String(i + 1).padStart(2, '0')}`,
  status: i % 3 === 0 ? 'closed' : 'open',
  n: 30 - i,
}))
const columns: Col<Row>[] = [
  {
    id: 'name',
    label: 'Name',
    primary: true,
    sort: 'name',
    sortValue: (r) => r.name,
    text: (r) => r.name,
    cell: (r) => r.name,
  },
  {
    id: 'status',
    label: 'Status',
    text: (r) => r.status,
    cell: (r) => r.status,
  },
  {
    id: 'n',
    label: 'Count',
    sortValue: (r) => r.n,
    text: (r) => r.n,
    cell: (r) => r.n,
    align: 'end',
  },
]
const filters = [
  {
    id: 'status',
    label: 'Status',
    options: [
      { value: 'open', label: 'Open' },
      { value: 'closed', label: 'Closed' },
    ],
    match: (r: Row, v: string[]) => v.includes(r.status),
  },
]

describe('DataTable', () => {
  beforeEach(async () => {
    window.history.replaceState(null, '', '/admin/test')
    localStorage.clear()
    await page.viewport(1280, 900)
  })
  afterEach(() => window.history.replaceState(null, '', '/'))

  it('searches, filters, sorts and pages in the browser, keeping it all in the address', async () => {
    const screen = await renderWithQuery(
      <DataTable<Row>
        id='t'
        rows={rows}
        columns={columns}
        filters={filters}
        rowId={(r) => r.id}
      />
    )
    await expect.element(screen.getByText('1–25 of 30')).toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'Next page' }))
    await expect.element(screen.getByText('26–30 of 30')).toBeVisible()
    expect(window.location.search).toBe('?page=2')

    await userEvent.click(screen.getByRole('button', { name: 'Status' }))
    await userEvent.click(
      screen.getByRole('menuitemcheckbox', { name: 'Closed' })
    )
    await expect.element(screen.getByText('1–10 of 10')).toBeVisible()
    expect(window.location.search).toBe('?status=closed') // back to page 1

    await userEvent.click(screen.getByRole('button', { name: 'Count' }))
    expect(
      screen.container.querySelector('tbody tr td:nth-child(3)')?.textContent
    ).toBe('3')
    await userEvent.click(screen.getByRole('button', { name: 'Count' }))
    expect(
      screen.container.querySelector('tbody tr td:nth-child(3)')?.textContent
    ).toBe('30')
    expect(new URLSearchParams(window.location.search).get('sort')).toBe('-n')

    await userEvent.fill(screen.getByRole('searchbox'), 'Cafe 07')
    await expect.element(screen.getByText('1–1 of 1')).toBeVisible()
    await userEvent.click(
      screen.getByRole('button', { name: 'Clear filters' }).first()
    )
    await expect.element(screen.getByText('1–25 of 30')).toBeVisible()
    expect(window.location.search).toBe('?sort=-n')
  })

  it('reads its state from the address on load and shows an empty state', async () => {
    window.history.replaceState(
      null,
      '',
      '/admin/test?q=nothing-like-this&status=open'
    )
    const screen = await renderWithQuery(
      <DataTable<Row>
        id='t'
        rows={rows}
        columns={columns}
        filters={filters}
        rowId={(r) => r.id}
      />
    )
    await expect
      .element(screen.getByText('Nothing matches these filters.'))
      .toBeVisible()
    await expect
      .element(screen.getByRole('searchbox'))
      .toHaveValue('nothing-like-this')
  })

  it('asks the server for each page with the same parameters, and exports the filtered view', async () => {
    const fetch = vi.fn(async (p: URLSearchParams): Promise<Page<Row>> => {
      const pageNo = Number(p.get('page'))
      return {
        data: rows.slice((pageNo - 1) * 10, pageNo * 10),
        meta: {
          total: 30,
          page: pageNo,
          per_page: 10,
          pages: 3,
          totals: { ETB: 4200 },
        },
      }
    })
    const hrefs: string[] = []
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        hrefs.push(this.getAttribute('href') ?? '')
      })
    window.history.replaceState(null, '', '/admin/test?per=10&status=open')
    const screen = await renderWithQuery(
      <DataTable<Row>
        id='t'
        fetch={fetch}
        csvUrl={(p) => `/api/x?format=csv&${p}`}
        columns={columns}
        filters={filters}
        rowId={(r) => r.id}
        summary={(m) => `Total ${m.totals?.ETB}`}
      />
    )
    await expect.element(screen.getByText('Total 4200')).toBeVisible()
    expect(fetch.mock.calls[0][0].toString()).toBe('page=1&per=10&status=open')
    await userEvent.click(screen.getByRole('button', { name: 'Name' }))
    await userEvent.click(screen.getByRole('button', { name: 'Last page' }))
    await expect.element(screen.getByText('21–30 of 30')).toBeVisible()
    expect(fetch.mock.calls[fetch.mock.calls.length - 1][0].toString()).toBe(
      'page=3&per=10&sort=name&status=open'
    )

    await userEvent.click(screen.getByRole('button', { name: 'Export CSV' }))
    expect(hrefs).toEqual(['/api/x?format=csv&sort=name&status=open'])
    click.mockRestore()
  })

  it('selects rows for bulk actions, hides columns, and shows cards on phones', async () => {
    const bulk = vi.fn()
    const screen = await renderWithQuery(
      <DataTable<Row>
        id='t'
        rows={rows}
        columns={columns}
        rowId={(r) => r.id}
        bulk={(selected, clear) => (
          <button
            type='button'
            onClick={() => {
              bulk(selected.map((r) => r.id))
              clear()
            }}
          >
            Archive
          </button>
        )}
      />
    )
    await userEvent.click(
      screen.getByRole('checkbox', { name: 'Select all rows on this page' })
    )
    await expect.element(screen.getByText('25 selected')).toBeVisible()
    await userEvent.click(screen.getByRole('button', { name: 'Archive' }))
    expect(bulk.mock.calls[0][0]).toHaveLength(25)
    await expect
      .element(screen.getByText('25 selected'))
      .not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'View' }))
    await userEvent.click(
      screen.getByRole('menuitemcheckbox', { name: 'Count' })
    )
    await userEvent.keyboard('{Escape}')
    expect(screen.container.querySelector('thead')?.textContent).not.toContain(
      'Count'
    )
    expect(JSON.parse(localStorage.getItem('dt:t:columns') ?? '{}')).toEqual({
      n: false,
    })

    await page.viewport(375, 800)
    await expect
      .element(screen.getByRole('list', { name: 'Rows' }))
      .toBeVisible()
    expect(screen.container.querySelector('table')).toBeNull()
  })
})
