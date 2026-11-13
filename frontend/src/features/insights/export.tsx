import {
  queryOptions,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query'
import { ChevronDown, Download, FileSpreadsheet, FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api, tenantUrl } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { downloadUrl } from '@/features/branches/api'

/** Export of what Insights shows, for the chosen period and branch (QUESTIONS #180). */
export function ExportMenu(props: {
  tenantId: number
  days: number
  branch: number | null
}) {
  const { t } = useTranslation()
  const q = new URLSearchParams({ days: String(props.days) })
  if (props.branch) q.set('branch_id', String(props.branch))
  const url = (path: string, extra = '') =>
    downloadUrl(props.tenantId, `insights/${path}?${extra}${q}`)
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant='outline' className='min-h-9'>
          <Download /> {t('insights.export.button')}{' '}
          <ChevronDown className='opacity-60' />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-64'>
        <DropdownMenuLabel>
          {t('insights.export.for', { count: props.days })}
        </DropdownMenuLabel>
        <DropdownMenuItem asChild className='min-h-11'>
          <a href={url('report.pdf')} download>
            <FileText /> {t('insights.export.pdf')}
          </a>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        {(['daily', 'qr', 'dishes'] as const).map((k) => (
          <DropdownMenuItem key={k} asChild className='min-h-11'>
            <a href={url('export.csv', `table=${k}&`)} download>
              <FileSpreadsheet /> {t(`insights.export.csv_${k}`)}
            </a>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

const settingsQuery = (t: number) =>
  queryOptions({
    queryKey: ['tenant', t, 'insights-settings'],
    queryFn: async () =>
      (
        await api.get<{ data: { monthly_email: boolean } }>(
          tenantUrl(t, 'insights/settings')
        )
      ).data.data,
  })

/** The monthly report by e-mail: on the 1st, last month's PDF to the owners. */
export function MonthlyReportCard(props: {
  tenantId: number
  canEdit: boolean
}) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const settings = useQuery(settingsQuery(props.tenantId))
  const save = useMutation({
    mutationFn: async (on: boolean) =>
      (
        await api.put<{ data: { monthly_email: boolean } }>(
          tenantUrl(props.tenantId, 'insights/settings'),
          { monthly_email: on }
        )
      ).data.data,
    onSuccess: (data) => {
      qc.setQueryData(settingsQuery(props.tenantId).queryKey, data)
      toast.success(
        t(data.monthly_email ? 'insights.monthly.on' : 'insights.monthly.off')
      )
    },
  })
  return (
    <Card className='mt-4'>
      <CardHeader>
        <CardTitle>{t('insights.monthly.title')}</CardTitle>
        <CardDescription>{t('insights.monthly.hint')}</CardDescription>
      </CardHeader>
      <CardContent className='flex min-h-11 items-center gap-3'>
        <Switch
          id='insights-monthly'
          checked={settings.data?.monthly_email ?? false}
          disabled={!props.canEdit || !settings.data || save.isPending}
          onCheckedChange={(v) => save.mutate(v)}
        />
        <Label htmlFor='insights-monthly'>{t('insights.monthly.label')}</Label>
      </CardContent>
    </Card>
  )
}
