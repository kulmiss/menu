import { createFileRoute } from '@tanstack/react-router'
import { optionalId } from '@/lib/search-params'
import { MenuEditorPage } from '@/features/menu/editor'

export const Route = createFileRoute('/admin/menu/')({
  validateSearch: (
    search: Record<string, unknown>
  ): { menu?: number; item?: number } => ({
    menu: optionalId(search.menu),
    item: optionalId(search.item),
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
