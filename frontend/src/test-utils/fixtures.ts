import { type Me, type Role, type Tenant } from '@/features/auth/api'

export function tenantFixture(
  overrides: Partial<Tenant> = {},
  role: Role = 'owner'
): Tenant {
  return {
    id: 1,
    slug: 'buna-bread',
    name: 'Buna & Bread',
    timezone: 'Africa/Addis_Ababa',
    default_locale: 'en',
    currency_code: 'ETB',
    subscription: {
      state: 'trial',
      allows_writes: true,
      shows_banner: false,
      stage_ends_at: null,
    },
    membership: { id: 10, role, can_edit_prices: role !== 'staff' },
    ...overrides,
  }
}

export function meFixture(overrides: Partial<Me> = {}): Me {
  return {
    id: 100,
    name: 'Selam Tesfaye',
    email: 'selam@example.com',
    locale: 'en',
    email_verified: true,
    two_factor_enabled: false,
    is_super_admin: false,
    tenants: [tenantFixture()],
    ...overrides,
  }
}
