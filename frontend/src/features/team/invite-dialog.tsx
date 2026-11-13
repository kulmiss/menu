import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2, Send } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { applyFieldErrors } from '@/lib/api'
import { toast } from '@/lib/toast'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { type Role } from '@/features/auth/api'
import { useInvite } from './api'

const schema = z.object({
  email: z.email('validation.email'),
  role: z.enum(['manager', 'staff']),
  can_edit_prices: z.boolean(),
})

type Values = z.infer<typeof schema>

type InviteDialogProps = {
  tenantId: number
  roles: Role[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function InviteDialog({
  tenantId,
  roles,
  open,
  onOpenChange,
}: InviteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: '',
      role: roles.includes('staff') ? 'staff' : 'manager',
      can_edit_prices: false,
    },
  })
  const invite = useInvite(tenantId, (error) => {
    applyFieldErrors(error, form.setError, ['email', 'role'])
  })

  // eslint-disable-next-line react-hooks/incompatible-library
  const role = form.watch('role')

  function onSubmit(values: Values) {
    invite.mutate(
      {
        ...values,
        can_edit_prices: values.role === 'staff' && values.can_edit_prices,
      },
      {
        onSuccess: () => {
          toast.success(t('team.invite_sent', { email: values.email }))
          form.reset()
          onOpenChange(false)
        },
      }
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader className='text-start'>
          <DialogTitle>{t('team.invite_title')}</DialogTitle>
          <DialogDescription>{t('team.invite_intro')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            id='invite-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='grid gap-4'
          >
            <FormField
              control={form.control}
              name='email'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('auth.email')}</FormLabel>
                  <FormControl>
                    <Input type='email' autoComplete='off' {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='role'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('team.role')}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className='min-h-11 w-full sm:min-h-9'>
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {roles.map((r) => (
                        <SelectItem key={r} value={r}>
                          {t(`roles.${r}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    {t(`team.role_hint.${field.value}`)}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            {role === 'staff' && (
              <FormField
                control={form.control}
                name='can_edit_prices'
                render={({ field }) => (
                  <FormItem className='flex items-center justify-between gap-4'>
                    <FormLabel>{t('team.can_edit_prices')}</FormLabel>
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                      />
                    </FormControl>
                  </FormItem>
                )}
              />
            )}
          </form>
        </Form>
        <DialogFooter>
          <Button
            type='submit'
            form='invite-form'
            className='min-h-11 sm:min-h-9'
            disabled={invite.isPending}
          >
            {invite.isPending ? <Loader2 className='animate-spin' /> : <Send />}
            {t('team.send_invite')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
