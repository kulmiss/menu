import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'

type TenantState = {
  /** Restaurant chosen in the switcher (remembered on this device). */
  currentTenantId: number | null
  setCurrentTenantId: (id: number | null) => void
}

export const useTenantStore = create<TenantState>()(
  persist(
    (set) => ({
      currentTenantId: null,
      setCurrentTenantId: (currentTenantId) => set({ currentTenantId }),
    }),
    {
      name: 'current_tenant',
      storage: createJSONStorage(() => localStorage),
    }
  )
)
