import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { branchesQuery } from '@/features/branches/api'
import { tr } from '../text'
import { type Menu } from '../types'
import { type MenuContext } from '../use-menu-context'

/** The address of the printable menu with the chosen options. */
function pdfUrl(
  tenantId: number,
  o: {
    menuId: number | null
    branchId: number | null
    photos: boolean
    columns: 1 | 2
  }
) {
  const q = new URLSearchParams({
    photos: o.photos ? '1' : '0',
    columns: String(o.columns),
  })
  if (o.menuId) q.set('menu_id', String(o.menuId))
  if (o.branchId) q.set('branch_id', String(o.branchId))
  return `${api.defaults.baseURL}${tenantUrl(tenantId, 'menu-pdf')}?${q}`
}

/** "Download PDF menu": a printable A4 menu made on the server (owners and managers). */
export function PdfMenuDialog({
  ctx,
  menus,
  open,
  onOpenChange,
}: {
  ctx: MenuContext
  menus: Menu[]
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const branches = useQuery({ ...branchesQuery(ctx.tenantId), enabled: open })
  const [menuId, setMenuId] = useState<number | null>(null)
  const [branchId, setBranchId] = useState<number | null>(null)
  const [photos, setPhotos] = useState(false)
  const [columns, setColumns] = useState<1 | 2>(2)
  const active = (branches.data ?? []).filter((b) => b.is_active)
  const select =
    'h-11 w-full rounded-md border bg-background px-3 text-sm sm:h-9'

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader className='text-start'>
          <DialogTitle>{t('menu.pdf.title')}</DialogTitle>
          <DialogDescription>{t('menu.pdf.intro')}</DialogDescription>
        </DialogHeader>
        <div className='grid gap-4'>
          <div className='grid gap-1'>
            <Label htmlFor='pdf-menu'>{t('menu.pdf.menu')}</Label>
            <select
              id='pdf-menu'
              className={select}
              value={menuId ?? ''}
              onChange={(e) =>
                setMenuId(e.target.value ? Number(e.target.value) : null)
              }
            >
              <option value=''>{t('menu.pdf.all_menus')}</option>
              {menus.map((m) => (
                <option key={m.id} value={m.id}>
                  {tr(m.name, ctx.locale)}
                </option>
              ))}
            </select>
          </div>
          {active.length > 1 && (
            <div className='grid gap-1'>
              <Label htmlFor='pdf-branch'>{t('menu.pdf.branch')}</Label>
              <select
                id='pdf-branch'
                className={select}
                value={branchId ?? ''}
                onChange={(e) =>
                  setBranchId(e.target.value ? Number(e.target.value) : null)
                }
              >
                {active.map((b) => (
                  <option key={b.id} value={b.is_default ? '' : b.id}>
                    {tr(b.name, ctx.locale)}
                  </option>
                ))}
              </select>
            </div>
          )}
          <Label className='flex min-h-11 items-center justify-between gap-4 font-normal'>
            {t('menu.pdf.photos')}
            <Switch checked={photos} onCheckedChange={setPhotos} />
          </Label>
          <fieldset className='grid gap-2'>
            <legend className='mb-1 text-sm font-medium'>
              {t('menu.pdf.columns')}
            </legend>
            <div className='flex gap-2'>
              {([1, 2] as const).map((n) => (
                <Button
                  key={n}
                  type='button'
                  variant={columns === n ? 'default' : 'outline'}
                  aria-pressed={columns === n}
                  className='min-h-11 flex-1 sm:min-h-9'
                  onClick={() => setColumns(n)}
                >
                  {t('menu.pdf.columns_n', { count: n })}
                </Button>
              ))}
            </div>
          </fieldset>
        </div>
        <DialogFooter>
          <Button asChild className='min-h-11 sm:min-h-9'>
            <a
              href={pdfUrl(ctx.tenantId, { menuId, branchId, photos, columns })}
              download
            >
              <Download /> {t('menu.pdf.download')}
            </a>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
