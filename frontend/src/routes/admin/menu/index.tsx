import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { MenuEditorPage } from '@/features/menu/editor'

export const Route = createFileRoute('/admin/menu/')({
  validateSearch: z.object({
    menu: z.coerce.number().int().positive().optional(),
    item: z.coerce.number().int().positive().optional(),
  }),
  component: function MenuRoute() {
    const { menu, item } = Route.useSearch()
    const navigate = Route.useNavigate()
    return (
      <MenuEditorPage
        menuId={menu}
        itemId={item}
        onSelectMenu={(id) =>
          navigate({ search: { menu: id || undefined }, replace: true })
        }
        onOpenItem={(id) => navigate({ search: (s) => ({ ...s, item: id }) })}
      />
    )
  },
})
