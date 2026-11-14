import { useSuspenseQuery } from '@tanstack/react-query'
import { useTenantStore } from '@/stores/tenant-store'
import { meQueryOptions, type Me, type Role, type Tenant } from './api'

/** The signed-in user. Only use below /admin (the route loads it first). */
export function useMe(): Me {
  return useSuspenseQuery(meQueryOptions).data
}

/**
 * The restaurant picked in the switcher, or the first one if the stored
 * choice is gone (removed from the team, restaurant deleted). Null when the
 * user belongs to no restaurant.
 */
export function pickTenant(
  tenants: Tenant[],
  storedId: number | null
): Tenant | null {
  return tenants.find((t) => t.id === storedId) ?? tenants[0] ?? null
}

export function useCurrentTenant(): Tenant | null {
  const me = useMe()
  const storedId = useTenantStore((s) => s.currentTenantId)
  return pickTenant(me.tenants, storedId)
}

/**
 * A plan feature of the current restaurant (with the Super Admin's
 * switches); missing = on, like the server's defaults.
 */
export function useFeature(key: string): boolean {
  return useCurrentTenant()?.features?.[key] !== false
}

/** Role in the current restaurant (null without one). */
export function useRole(): Role | null {
  return useCurrentTenant()?.membership?.role ?? null
}

/** Roles a member may invite, change or remove (mirrors App\Enums\Role). */
export function manageableRoles(role: Role | null): Role[] {
  if (role === 'owner') return ['manager', 'staff']
  if (role === 'manager') return ['staff']
  return []
}

export function isManagerOrOwner(role: Role | null): boolean {
  return role === 'owner' || role === 'manager'
}
