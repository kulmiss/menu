import { api, tenantUrl } from '@/lib/api'

export type ImportAction = 'create' | 'update' | 'unchanged' | 'error'

export type ImportRow = {
  line: number
  action: ImportAction
  menu: string
  category: string
  item: string
  changes: string[]
  errors: string[]
}

export type ImportSummary = {
  create: number
  update: number
  unchanged: number
  errors: number
  new_menus: number
  new_categories: number
  new_tags: number
}

export type ImportPreview = {
  token: string
  summary: ImportSummary
  rows: ImportRow[]
  expires_at: string
}

export async function previewImport(tenantId: number, file: File) {
  const form = new FormData()
  form.append('file', file)
  const { data } = await api.post<{ data: ImportPreview }>(
    tenantUrl(tenantId, 'import/preview'),
    form
  )
  return data.data
}

export async function commitImport(tenantId: number, token: string) {
  const { data } = await api.post<{ data: { summary: ImportSummary } }>(
    tenantUrl(tenantId, 'import/commit'),
    {
      token,
    }
  )
  return data.data.summary
}

/** Downloads the export or the empty template as a file. */
export async function downloadSheet(
  tenantId: number,
  kind: 'export' | 'template',
  format: 'csv' | 'xlsx'
) {
  const path = kind === 'export' ? 'export' : 'import/template'
  const response = await api.get<Blob>(tenantUrl(tenantId, path), {
    params: { format },
    responseType: 'blob',
  })
  const disposition = String(response.headers['content-disposition'] ?? '')
  const name =
    /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `menu.${format}`
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  document.body.append(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
