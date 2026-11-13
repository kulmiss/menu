import { fakeApi } from '@/test-utils/fake-api'
import { renderWithQuery } from '@/test-utils/render'
import { afterEach, describe, expect, it } from 'vitest'
import { userEvent } from 'vitest/browser'
import { ExportMenu, MonthlyReportCard } from './export'

let fake: ReturnType<typeof fakeApi>

describe('Insights export', () => {
  afterEach(() => fake.restore())

  it('offers the PDF report and three CSV tables for the chosen period and branch', async () => {
    fake = fakeApi({})
    const screen = await renderWithQuery(
      <ExportMenu tenantId={1} days={7} branch={3} />
    )
    await userEvent.click(screen.getByRole('button', { name: /Export/ }))
    await expect.element(screen.getByText('Last 7 days')).toBeVisible()
    const href = (name: string) =>
      new URL(
        screen.getByRole('menuitem', { name }).element().getAttribute('href') ??
          '',
        location.href
      )
    const pdf = href('Report (PDF)')
    expect(pdf.pathname).toBe('/api/v1/t/1/insights/report.pdf')
    expect(Object.fromEntries(pdf.searchParams)).toEqual({
      days: '7',
      branch_id: '3',
    })
    const qr = href('Scans per QR code and table (CSV)')
    expect(qr.pathname).toBe('/api/v1/t/1/insights/export.csv')
    expect(Object.fromEntries(qr.searchParams)).toEqual({
      table: 'qr',
      days: '7',
      branch_id: '3',
    })
    expect(
      href('Views and scans per day (CSV)').searchParams.get('table')
    ).toBe('daily')
    expect(
      href('Dish views and “My order” adds (CSV)').searchParams.get('table')
    ).toBe('dishes')
  })

  it('switches the monthly e-mail on', async () => {
    fake = fakeApi({
      'GET /t/1/insights/settings': {
        data: { data: { monthly_email: false } },
      },
      'PUT /t/1/insights/settings': { data: { data: { monthly_email: true } } },
    })
    const screen = await renderWithQuery(
      <MonthlyReportCard tenantId={1} canEdit />
    )
    const toggle = screen.getByRole('switch', {
      name: 'E-mail me the monthly report',
    })
    await expect.element(toggle).toBeEnabled()
    await userEvent.click(toggle)
    await expect
      .poll(() => fake.calls.find((c) => c.method === 'PUT')?.data)
      .toEqual({ monthly_email: true })
    await expect.element(toggle).toBeChecked()
  })

  it('is read-only when the restaurant can only read', async () => {
    fake = fakeApi({
      'GET /t/1/insights/settings': { data: { data: { monthly_email: true } } },
    })
    const screen = await renderWithQuery(
      <MonthlyReportCard tenantId={1} canEdit={false} />
    )
    const toggle = screen.getByRole('switch', {
      name: 'E-mail me the monthly report',
    })
    await expect.element(toggle).toBeChecked()
    await expect.element(toggle).toBeDisabled()
  })
})
