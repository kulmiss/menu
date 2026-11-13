import { useCurrentTenant } from '@/features/auth/use-me'

/** What the editor needs about the restaurant and the person's rights. */
export function useMenuContext() {
  const tenant = useCurrentTenant()
  if (!tenant) throw new Error('The menu editor needs a current restaurant.')
  const role = tenant.membership?.role ?? null
  return {
    tenantId: tenant.id,
    /** Menu text is edited in the restaurant's main language. */
    locale: tenant.default_locale,
    currency: tenant.currency_code,
    canManage: role === 'owner' || role === 'manager',
    canEditPrices: tenant.membership?.can_edit_prices ?? false,
    canWrite: tenant.subscription.allows_writes,
  }
}

export type MenuContext = ReturnType<typeof useMenuContext>
