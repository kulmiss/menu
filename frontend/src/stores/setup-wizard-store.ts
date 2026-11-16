import { create } from 'zustand'

/** Whether the Super Admin opened the setup wizard by hand (Settings → Setup wizard). */
export const useSetupWizard = create<{
  open: boolean
  setOpen: (open: boolean) => void
}>()((set) => ({ open: false, setOpen: (open) => set({ open }) }))
