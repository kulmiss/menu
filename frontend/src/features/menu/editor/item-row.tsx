import { ChevronRight, EyeOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
import { useFeature } from '@/features/auth/use-me'
import { formatMoney } from '../money'
import { tr } from '../text'
import { type ItemSummary } from '../types'
import { type MenuContext } from '../use-menu-context'

type Props = {
  item: ItemSummary
  ctx: MenuContext
  handle: React.ReactNode
  selecting: boolean
  selected: boolean
  onSelect: (selected: boolean) => void
  onOpen: () => void
  onSoldOut: (soldOut: boolean) => void
}

/** One item in the editor list. The sold-out switch is always one tap away. */
export function ItemRow({
  item,
  ctx,
  handle,
  selecting,
  selected,
  onSelect,
  onOpen,
  onSoldOut,
}: Props) {
  const { t, i18n } = useTranslation()
  const soldOutOn = useFeature('sold_out')
  const name = tr(item.name, ctx.locale)
  const price =
    item.variants_count > 0
      ? t('menu.from_price', {
          price: formatMoney(
            item.from_price_minor,
            ctx.currency,
            i18n.language
          ),
        })
      : formatMoney(item.price_minor, ctx.currency, i18n.language)
  const canOpen = ctx.canManage || ctx.canEditPrices

  return (
    <div
      className={cn(
        'flex items-center gap-1 py-1.5 pe-1',
        !item.is_visible && 'opacity-60'
      )}
    >
      {handle}
      {selecting && (
        <Checkbox
          checked={selected}
          onCheckedChange={(v) => onSelect(v === true)}
          aria-label={t('menu.select_item', { name })}
          className='mx-2 size-5'
        />
      )}
      <button
        type='button'
        className='flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-start hover:bg-accent disabled:hover:bg-transparent'
        onClick={selecting ? () => onSelect(!selected) : onOpen}
        disabled={!selecting && !canOpen}
      >
        {item.media && (
          <img
            src={item.media.urls['320']}
            alt=''
            loading='lazy'
            className='size-10 shrink-0 rounded object-cover'
            style={{ backgroundColor: item.media.dominant_color ?? undefined }}
          />
        )}
        <span className='min-w-0 flex-1'>
          <span
            className={cn(
              'block truncate font-medium',
              item.is_sold_out && 'line-through decoration-1'
            )}
          >
            {name}
          </span>
          <span className='flex flex-wrap items-center gap-1 text-sm text-muted-foreground'>
            <span className='tabular-nums'>{price}</span>
            {!item.is_visible && (
              <Badge variant='outline' className='gap-1'>
                <EyeOff className='size-3' aria-hidden /> {t('menu.hidden')}
              </Badge>
            )}
            {item.is_new && (
              <Badge variant='secondary'>{t('menu.badge_new')}</Badge>
            )}
            {item.is_popular && (
              <Badge variant='secondary'>{t('menu.badge_popular')}</Badge>
            )}
          </span>
        </span>
        {canOpen && !selecting && (
          <ChevronRight
            className='size-4 shrink-0 text-muted-foreground rtl:rotate-180'
            aria-hidden
          />
        )}
      </button>
      {soldOutOn && (
        <label className='flex min-h-11 shrink-0 cursor-pointer items-center gap-2 px-1 text-xs text-muted-foreground'>
          <span
            className={cn(
              'hidden sm:inline',
              item.is_sold_out && 'font-medium text-destructive'
            )}
          >
            {item.is_sold_out ? t('menu.sold_out') : t('menu.available')}
          </span>
          <Switch
            checked={!item.is_sold_out}
            disabled={!ctx.canWrite}
            onCheckedChange={(available) => onSoldOut(!available)}
            aria-label={t('menu.available_named', { name })}
          />
        </label>
      )}
    </div>
  )
}
